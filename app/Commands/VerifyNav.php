<?php

namespace App\Commands;

use App\Support\Navigation;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Proves the menu, on both surfaces, from the one definition.
 *
 * The unit tests pin the matching rules. What only rendering the two views can show is the thing this
 * task was about: that the sidebar and the navbar show the *same* menu, that the entry for the page you
 * are on is the one marked active — including when you are three segments deep in a detail view — and
 * that a page the menu links to can actually render its own view.
 *
 * Nothing is written to the database; there is nothing to clean up.
 */
class VerifyNav extends BaseCommand
{
    protected $group       = 'Config';
    protected $name        = 'verify:nav';
    protected $description = 'Harness: the shared menu — groups, links, permissions, active state, both surfaces.';
    protected $usage       = 'verify:nav';

    /** @var list<string> */
    private array $pass = [];

    /** @var list<string> */
    private array $fail = [];

    /** Renders as if every permission were granted. */
    private const EVERYTHING = 'everything';

    /** What the permission matrix grants a manager. */
    private const MANAGER = ['devices.view', 'dashboard.view'];

    public function run(array $params)
    {
        CLI::write('The application menu, rendered twice from one definition', 'yellow');
        CLI::newLine();

        $this->sectionA();
        $this->sectionB();
        $this->sectionC();
        $this->sectionD();
        $this->sectionE();
        $this->sectionF();

        CLI::newLine();
        CLI::write('  PASS ' . count($this->pass) . '   FAIL ' . count($this->fail), $this->fail === [] ? 'green' : 'red');

        foreach ($this->fail as $message) {
            CLI::write('   - ' . $message, 'red');
        }

        return $this->fail === [] ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // ------------------------------------------------------------------ A: the definition

    private function sectionA(): void
    {
        CLI::write('A. Every entry is well formed, and every link goes somewhere', 'yellow');

        $definition = Navigation::definition();
        $routes     = (string) file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $permissions = array_keys(config('AuthGroups')->permissions);

        $paths = [];

        foreach ($definition as $section) {
            $label = (string) ($section['label'] ?? $section['group'] ?? '');

            $this->check('"' . $label . '" has a label and an icon', $label !== '' && ($section['icon'] ?? '') !== '');

            foreach ($section['items'] ?? [$section] as $item) {
                $paths[] = $item['path'];

                // An off-site URL is somebody else's route, and a static file is nobody's action — but a
                // new-tab link to one of our own pages still has to resolve.
                if (! Navigation::isPage($item)) {
                    continue;
                }

                $this->check('/' . $item['path'] . ' is routed (' . $item['label'] . ')', str_contains($routes, "'" . $item['path'] . "'"));

                $target = $this->routeTarget((string) $item['path']);

                $this->check(
                    '/' . $item['path'] . ' resolves to a real action (' . ($target ?? 'nothing') . ')',
                    $target !== null && $this->actionExists($target)
                );

                if (isset($item['permission'])) {
                    $this->check(
                        $item['label'] . ' is gated on a permission Shield knows (' . $item['permission'] . ')',
                        in_array($item['permission'], $permissions, true)
                    );
                }
            }
        }

        $this->check('no page is listed twice', $paths === array_values(array_unique($paths)));
        $this->check('the menu is not empty', count($paths) > 10);
    }

    // ------------------------------------------------------------------ B: the sidebar

    private function sectionB(): void
    {
        CLI::write('B. The sidebar renders the whole menu, with the page you are on marked', 'yellow');

        $nav  = Navigation::forCurrentUser(static fn (): bool => true, base_url('configjobs/detail/7'));
        $html = $this->render('frontend/sidebar', $nav);

        $this->check('the sidebar renders at all', $html !== null);
        $html ??= '';

        $this->check('no template syntax is left in the output', ! str_contains($html, '<?'));

        foreach ($this->flat($nav) as $item) {
            $this->check(
                'the sidebar has exactly one link to ' . $item['label'],
                substr_count($html, 'href="' . $nav->href($item) . '"') === 1
            );
        }

        foreach ($nav->sections() as $section) {
            if (empty($section['items'])) {
                // A page of its own is a top-level entry, and those are rendered in capitals.
                $this->check('the sidebar labels it "' . $section['label'] . '"', str_contains($html, strtoupper($section['label'])));

                continue;
            }

            foreach ($section['items'] as $item) {
                $this->check('the sidebar labels it "' . $item['label'] . '"', str_contains($html, esc($item['label'])));
            }
        }

        foreach ($this->groups($nav) as $section) {
            $this->check('the group heading ' . strtoupper($section['group']) . ' is present', str_contains($html, strtoupper($section['group'])));
        }

        $this->check('a detail page of Config Jobs marks Config Jobs active', $this->isActive($html, base_url('configjobs')));
        $this->check('and it does not mark any other page active', $this->isActive($html, base_url('devices')) === false && $this->isActive($html, base_url('users')) === false);
        $this->check('its group is open and marked', str_contains($html, 'nav-item has-treeview menu-open'));
        $this->check('exactly one group is open', substr_count($html, 'nav-item has-treeview menu-open') === 1);
        $this->check('the drift counter is attached to the Config Jobs entry', str_contains($this->classOf($html, base_url('configjobs')), 'js-drift-target'));

        CLI::newLine();
        CLI::write('   menu as the sidebar renders it:', 'blue');
        $this->printTree($nav);
        CLI::newLine();
    }

    // ------------------------------------------------------------------ C: who sees what

    private function sectionC(): void
    {
        CLI::write('C. A permission hides the page, and then the group left with nothing', 'yellow');

        $manager = $this->render('frontend/sidebar', Navigation::forCurrentUser(
            static fn (string $permission): bool => in_array($permission, self::MANAGER, true),
            base_url('loggedlive')
        ));

        $this->check('a manager renders the sidebar', $manager !== null);
        $manager ??= '';

        $this->check('a manager sees the device list', str_contains($manager, base_url('devices')) && str_contains($manager, base_url('pflist')));
        $this->check('a manager does not see the admin-only availability report', ! str_contains($manager, base_url('availability')));
        $this->check('a manager does not see the config pipeline', ! str_contains($manager, base_url('configjobs')) && ! str_contains($manager, base_url('configpush')));
        $this->check('the Configuration group is gone rather than empty', ! str_contains($manager, 'CONFIGURATION'));
        $this->check('the Administration group is gone rather than empty', ! str_contains($manager, 'ADMINISTRATION'));
        $this->check('and the Monitoring group is still there', str_contains($manager, 'MONITORING'));

        $user = $this->render('frontend/sidebar', Navigation::forCurrentUser(
            static fn (string $permission): bool => $permission === 'dashboard.view',
            base_url('loggedlive')
        ));

        $this->check('a plain user gets the dashboard and nothing else', $user !== null
            && str_contains((string) $user, 'LIVE DASHBOARD')
            && ! str_contains((string) $user, 'MONITORING')
            && substr_count((string) $user, 'nav-link') === 1);

        $guest = $this->render('frontend/sidebar', Navigation::forCurrentUser(static fn (): bool => false, base_url('')));

        $this->check('a visitor with no session gets no menu items', $guest !== null && ! str_contains((string) $guest, 'nav-link'));
    }

    // ------------------------------------------------------------------ D: the navbar

    private function sectionD(): void
    {
        CLI::write('D. The navbar shows the same menu, not a second opinion', 'yellow');

        $nav      = Navigation::forCurrentUser(static fn (): bool => true, base_url('configjobs/detail/7'));
        $html     = $this->renderNavbar($nav);
        $sidebar  = $this->render('frontend/sidebar', $nav);
        $expected = array_values(array_unique(array_map(static fn (array $item): string => $nav->href($item), $this->flat($nav))));
        sort($expected);

        if ($html === null) {
            $this->check('the navbar renders', false);

            return;
        }


        $this->check('every page of the menu is in the navbar', $this->hrefSet($html, $expected) === $expected);
        $this->check('and every page of the menu is in the sidebar', $this->hrefSet((string) $sidebar, $expected) === $expected);
        $this->check(
            'the two surfaces link to exactly the same set of pages',
            $this->hrefSet($html, $expected) === $this->hrefSet((string) $sidebar, $expected)
        );

        foreach ($nav->sections() as $section) {
            if (empty($section['items'])) {
                // A top-level link is rendered in the navbar's own capitals.
                $this->check('the navbar labels "' . $section['label'] . '" the same way', str_contains($html, strtoupper($section['label'])));

                continue;
            }

            foreach ($section['items'] as $item) {
                $this->check('the navbar labels "' . $item['label'] . '" the same way', str_contains($html, esc($item['label'])));
            }
        }

        foreach ($this->groups($nav) as $section) {
            $this->check('the navbar has a ' . strtoupper($section['group']) . ' dropdown', str_contains($html, strtoupper($section['group'])));
        }

        $this->check('the navbar marks the active page too', $this->isActive($html, base_url('configjobs')));
        $this->check('the navbar carries the drift counter as well', str_contains($this->classOf($html, base_url('configjobs')), 'js-drift-target'));
        $this->check('off-site links open in a new tab and say so', str_contains($html, 'target="_blank" rel="noopener"'));

        CLI::newLine();
        CLI::write('   the same menu as the navbar renders it (marker = the page you are on):', 'blue');
        $this->printTree($nav);
        CLI::newLine();
    }

    // ------------------------------------------------------------------ E: the pages behind the links

    private function sectionE(): void
    {
        CLI::write('E. The pages the menu links to can render', 'yellow');

        // Read with the comments taken out: the notes that explain which model and permission this page
        // *used* to name are the reason nobody puts them back, so documentation is not mistaken for code.
        $source = $this->withoutComments((string) file_get_contents(ROOTPATH . 'app/Controllers/UsersController.php'));
        $index  = $this->methodBody($source, 'index');

        // These ask the same questions they always did, of the rebuild rather than of the old shape: the
        // permission is checked in actor() and the chrome is applied in render(), so an index() that
        // calls both is what "the page is gated and framed" now looks like.
        $this->check('the users list renders the view that exists', str_contains($source, "'Auth/Users/index'") && str_contains($source, 'private function render('));
        $this->check('and that view is on disk', is_file(ROOTPATH . 'app/Views/Auth/Users/index.php'));
        $this->check('the users list no longer names a model this project does not have', ! str_contains($source, 'UsersModel'));
        $this->check('the users list no longer requires a permission Shield does not know', ! str_contains($source, 'view_users'));
        $this->check('the users list requires the permission its own view requires', str_contains($source, "can('users.manage')") && str_contains($index, '$this->actor()'));
        $this->check('the users list renders inside the application chrome', str_contains($source, "view('frontend/header'") && str_contains($source, "view('frontend/sidebar'"));

        $adminView = (string) file_get_contents(ROOTPATH . 'app/Views/Auth/Users/index.php');
        $service   = (string) file_get_contents(ROOTPATH . 'app/Services/Users/UserAccountService.php');

        // Shield keeps group membership in its own table, so the list's roles come from one query over
        // it rather than from a per-row `getGroups()` — which is what the list used to do per user.
        $this->check('the users view reads roles the way Shield stores them', str_contains($adminView, '$roles') && str_contains($service, 'auth_groups_users'));
    }

    // ------------------------------------------------------------------ F: one definition

    private function sectionF(): void
    {
        CLI::write('F. Neither surface writes its own menu any more', 'yellow');

        $sidebar = (string) file_get_contents(ROOTPATH . 'app/Views/frontend/sidebar.php');
        $navbar  = (string) file_get_contents(ROOTPATH . 'app/Views/frontend/header.php');

        $this->check('the sidebar renders the shared menu', str_contains($sidebar, 'Navigation::forCurrentUser'));
        $this->check('the navbar renders the shared menu', str_contains($navbar, 'Navigation::forCurrentUser'));

        foreach ($this->flat(Navigation::forCurrentUser(static fn (): bool => true, base_url(''))) as $item) {
            $hardcoded = "base_url('" . $item['path'] . "')";

            $this->check('the sidebar does not hardcode ' . $item['path'], ! str_contains($sidebar, $hardcoded));
            $this->check('the navbar does not hardcode ' . $item['path'], ! str_contains($navbar, $hardcoded));
        }

        $this->check('the navbar no longer builds a menu out of raw permission checks', ! str_contains($navbar, "can('devices.manage')"));
    }

    // ------------------------------------------------------------------ helpers

    /**
     * The navbar, as a page renders it.
     *
     * The whole header cannot be rendered in a CLI process: its CSRF bootstrap builds a token from a
     * web request that does not exist here, and stubbing one breaks the URL helpers the menu is built
     * from. So the navbar block itself is taken out of the header and evaluated on its own — the same
     * markup, the same code path, the same variable, with only the page around it missing.
     */
    private function renderNavbar(Navigation $nav): ?string
    {
        $source = (string) file_get_contents(ROOTPATH . 'app/Views/frontend/header.php');
        $anchor = strpos($source, 'The top navigation is the sidebar');

        if ($anchor === false) {
            CLI::write('   could not find the navbar block in the header', 'red');

            return null;
        }

        // The block's own end marker, because the menu contains a nested endforeach that a plain
        // search would stop at, halfway through the group loop.
        $endMarker = '<?php /* end of the shared application menu';
        $start     = strrpos(substr($source, 0, (int) $anchor), '<?php');
        $end       = strpos($source, $endMarker, (int) $anchor);
        $close     = $end === false ? false : strpos($source, '?>', $end);

        if ($start === false || $end === false || $close === false) {
            CLI::write('   the navbar block is not delimited as expected', 'red');

            return null;
        }

        $block = substr($source, $start, $close - $start + 2);

        try {
            ob_start();
            eval('?>' . $block);

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            CLI::write('   could not render the navbar block: ' . $e->getMessage(), 'red');

            return null;
        }
    }

    /** @param array<string, mixed> $extra */
    private function render(string $view, Navigation $nav, array $extra = []): ?string
    {
        try {
            return view($view, array_merge(['first_name' => 'Harness', 'nav' => $nav], $extra));
        } catch (\Throwable $e) {
            CLI::write('   could not render ' . $view . ': ' . $e->getMessage(), 'red');

            return null;
        }
    }

    /** The anchor for a URL carries the active class? */
    private function isActive(string $html, string $href): bool
    {
        return str_contains($this->classOf($html, $href), 'active');
    }

    /**
     * The class attribute of the anchor for a URL — empty when the page has no such link.
     *
     * Both surfaces put the attributes in their own order (the sidebar writes href first, the navbar
     * class first), so this finds the tag that *contains* the href and then reads its class.
     */
    private function classOf(string $html, string $href): string
    {
        $href = preg_quote($href, '/');

        if (preg_match('/<a\b(?=[^>]*href="' . $href . '")[^>]*>/s', $html, $tag) !== 1) {
            return '';
        }

        return preg_match('/class="([^"]*)"/', $tag[0], $class) === 1 ? $class[1] : '';
    }

    /**
     * Which of the expected links this page actually links to, deduplicated and sorted.
     *
     * @param list<string> $expected
     *
     * @return list<string>
     */
    private function hrefSet(string $html, array $expected): array
    {
        $found = [];

        foreach ($expected as $href) {
            if (str_contains($html, 'href="' . $href . '"')) {
                $found[] = $href;
            }
        }

        return $found;
    }

    /**
     * `'UsersController::index'` -> does that class and method exist?
     */
    private function actionExists(string $target): bool
    {
        [$class, $method] = array_pad(explode('::', ltrim($target, '\\')), 2, '');

        if (! str_contains($class, '\\')) {
            $class = 'App\\Controllers\\' . $class;
        }

        return class_exists($class) && method_exists($class, (string) $method);
    }

    /**
     * The controller action a menu path is served by, as the routes are written today.
     *
     * Two spellings cover this app: a direct GET, and a group whose '/' action is the page.
     */
    private function routeTarget(string $path): ?string
    {
        $routes = (string) file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $quoted = preg_quote($path, '/');

        // The group form is tried first, and that order matters: `devices/groups` is written in the
        // routes file as a bare `GET('groups', ...)` inside the devices group, and taking it for the
        // top-level `/groups` page would have this harness verifying the wrong action.
        if (preg_match("/\\\$routes->group\('" . $quoted . "',[^{]*\{(.*?)\n\}\),?\s*;?/s", $routes, $matches) === 1
            && preg_match("/\\\$routes->(?:get|GET)\('\/',\s*'([^']+)'/", $matches[1], $inner) === 1) {
            return $inner[1];
        }

        if (preg_match("/\\\$routes->(?:get|GET)\('" . $quoted . "',\s*'([^']+)'/", $routes, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /** The body of one public method, so an assertion can be about this action and not the file. */
    /** A PHP file with its comments removed, so a note about old code is not read as old code. */
    private function withoutComments(string $source): string
    {
        return (string) preg_replace('~ /\*.*?\*/ | ^[ \t]*//[^\n]* ~msx', ' ', $source);
    }

    private function methodBody(string $source, string $method): string
    {
        if (preg_match('/public function ' . preg_quote($method, '/') . '\(.*?\n    \}/s', $source, $matches) !== 1) {
            return '';
        }

        return $matches[0];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function flat(Navigation $nav): array
    {
        $items = [];

        foreach ($nav->sections() as $section) {
            foreach ($section['items'] ?? [$section] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function groups(Navigation $nav): array
    {
        return array_values(array_filter($nav->sections(), static fn (array $section): bool => isset($section['items'])));
    }

    private function printTree(Navigation $nav): void
    {
        foreach ($nav->sections() as $section) {
            $marker = $nav->isGroupActive($section) ? '>' : ' ';
            CLI::write('   ' . $marker . ' ' . strtoupper($section['label'] ?? $section['group']), 'blue');

            foreach ($section['items'] ?? [] as $item) {
                $active = $nav->isActive($item) ? '*' : ' ';

                CLI::write('       ' . $active . ' ' . $item['label'] . '  ->  ' . $nav->href($item), $nav->isActive($item) ? 'green' : 'white');
            }
        }
    }

    private function check(string $description, bool $ok): void
    {
        if ($ok) {
            $this->pass[] = $description;
            CLI::write('   PASS  ' . $description, 'green');

            return;
        }

        $this->fail[] = $description;
        CLI::write('   FAIL  ' . $description, 'red');
    }
}
