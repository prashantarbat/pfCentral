<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The application menu, defined once.
 *
 * Both the sidebar and the top navbar render from this array. Before it existed the two surfaces
 * each carried their own hardcoded list, and they had already drifted apart: "Device Groups" was
 * reachable from the navbar and not from the sidebar, "Users Management" was in both but pointed at
 * a page that could not render, and the four pages the config pipeline added (jobs, verification,
 * search, availability) were in the sidebar only. A page added to one surface could silently be
 * missing from the other forever.
 *
 * An entry is either
 *
 *   a page   ['label', 'icon', 'path', 'permission'?]
 *   a group  ['group', 'icon', 'items' => [page, ...]]
 *
 * and a group renders only when at least one of its pages does, so tightening a permission can never
 * leave an empty heading behind. Item keys:
 *
 *   label       what the operator reads
 *   path        relative to base_url(), or an absolute URL when 'external'
 *   icon        Font Awesome class
 *   permission  a Shield permission, or null for "anyone signed in"
 *   setting     an integration switch in core_config ('librenms', 'grafana'); the entry only
 *               renders while that integration is enabled in Core Settings
 *   match       extra prefixes that count as "this page" (a details page belongs to its list)
 *   external    opens in a new tab — a page that replaces the application chrome; never counts as the
 *               page you are on, because you are not looking at it in this window
 *   badge       a live-counter hook, e.g. 'drift' -> the 'js-drift-target' class
 *
 * The permission on each item is the one the page's own controller (or its data endpoints) enforces,
 * because a menu entry that leads to a page whose data is refused is worse than no entry at all.
 */
final class Navigation
{
    /** @var array<int, array<string, mixed>> */
    private array $sections;

    private string $currentUrl;

    /**
     * @param array<int, array<string, mixed>> $sections typically from definition(), already filtered
     */
    public function __construct(array $sections, string $currentUrl)
    {
        $this->sections   = $sections;
        $this->currentUrl = $currentUrl;
    }

    /**
     * Every entry of the application menu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definition(): array
    {
        return [
            [
                'label'      => 'Live Dashboard',
                'icon'       => 'fas fa-tachometer-alt',
                'path'       => 'loggedlive',
                'permission' => 'dashboard.view',
            ],
            [
                'group' => 'Monitoring',
                'icon'  => 'fas fa-network-wired',
                'items' => [
                    [
                        'label' => 'Device Cards',
                        'icon'  => 'fas fa-desktop',
                        'path'  => 'devices',
                        'permission' => 'devices.view',
                    ],
                    [
                        'label' => 'Device List',
                        'icon'  => 'fas fa-list',
                        'path'  => 'pflist',
                        'permission' => 'devices.view',
                        // A device's own pages (details, backups, SSH client) are this list, opened.
                        'match' => ['pfsense'],
                    ],
                    [
                        'label' => 'Branch Availability',
                        'icon'  => 'fas fa-heartbeat',
                        'path'  => 'availability',
                        'permission' => 'admin.access',
                    ],
                    [
                        'label' => 'Compliance Audit',
                        'icon'  => 'fas fa-shield-alt',
                        'path'  => 'compliance',
                        'permission' => 'admin.access',
                    ],
                    [
                        'label' => 'Network Map',
                        'icon'  => 'fas fa-map-marked-alt',
                        'path'  => 'map',
                        'permission' => 'devices.view',
                        // Draws weathermap overlays from LibreNMS data — hidden with it.
                        'setting' => 'librenms',
                        // Its own full page, with its own <html>: opening it in a tab keeps the menu you
                        // came from, which is what the navbar's "Google Map Live" link used to do.
                        'external' => true,
                    ],
                    [
                        'label' => 'Live Topology',
                        'icon'  => 'fas fa-project-diagram',
                        'path'  => 'topology',
                        'permission' => 'devices.view',
                        // Merges LibreNMS device inventory into the live graph — hidden with it.
                        'setting' => 'librenms',
                        'external' => true,
                    ],
                ],
            ],
            [
                'group' => 'Configuration',
                'icon'  => 'fas fa-sliders-h',
                'items' => [
                    // One entry for one page. This was two — "New Device Install" and "By Location
                    // Config" — for two variants of the same generator, which left every operator to
                    // work out which of the two described the job they had.
                    [
                        'label' => 'Device Config Generator',
                        'icon'  => 'fas fa-file-code',
                        'path'  => 'config-generator',
                        'permission' => 'devices.manage',
                    ],
                    [
                        'label' => 'SSH Config Push',
                        'icon'  => 'fas fa-terminal',
                        'path'  => 'configpush',
                        // The page is viewable by anyone signed in, but every action it offers
                        // (deploy, preview, rollback, approve) is admin-only: showing the page to a
                        // manager would be a dead end, so the entry follows the endpoints.
                        'permission' => 'admin.access',
                    ],
                    [
                        'label' => 'Config Jobs',
                        'icon'  => 'fas fa-stream',
                        'path'  => 'configjobs',
                        'permission' => 'admin.access',
                        'badge' => 'drift',
                    ],
                    [
                        'label' => 'Verification Report',
                        'icon'  => 'fas fa-clipboard-check',
                        'path'  => 'configreports',
                        'permission' => 'admin.access',
                    ],
                    [
                        'label' => 'Fleet Config Search',
                        'icon'  => 'fas fa-search',
                        'path'  => 'configsearch',
                        'permission' => 'admin.access',
                    ],
                ],
            ],
            [
                'group' => 'Administration',
                'icon'  => 'fas fa-user-cog',
                'items' => [
                    [
                        'label' => 'Users Management',
                        'icon'  => 'fas fa-users',
                        'path'  => 'users',
                        // What UsersController::index and the users view itself require.
                        'permission' => 'users.manage',
                    ],
                    [
                        'label' => 'Roles & Permissions',
                        'icon'  => 'fas fa-user-shield',
                        'path'  => 'roles',
                        'permission' => 'admin.access',
                    ],
                    [
                        'label' => 'Sites & Locations',
                        'icon'  => 'fas fa-map-marker-alt',
                        'path'  => 'location',
                        'permission' => 'admin.access',
                    ],
                    [
                        'label' => 'Documentation',
                        'icon'  => 'fas fa-book',
                        'path'  => 'docs',
                        'permission' => 'admin.access',
                    ],

                    [
                        'label' => 'Core Settings',
                        'icon'  => 'fas fa-cogs',
                        'path'  => 'settings',
                        'permission' => 'admin.access',
                    ],
                ],
            ],
            [
                'label'      => 'Network Diagram',
                'icon'       => 'fas fa-sitemap',
                'path'       => 'graphify-out/network_diagram.html',
                'permission' => 'devices.view',
                'external'   => true,
            ],
        ];
    }

    /**
     * The menu as one particular user sees it.
     *
     * Both arguments exist for the tests and the verify:nav harness: passing a capability callback and
     * a URL renders the menu for a synthetic operator instead of the signed-in one.
     */
    public static function forCurrentUser(?callable $can = null, ?string $currentUrl = null): self
    {
        $can ??= static function (string $permission): bool {
            $user = function_exists('auth') ? auth()->user() : null;

            return $user !== null && $user->can($permission);
        };

        return new self(self::filter(self::definition(), $can), $currentUrl ?? current_url());
    }

    /**
     * Drops the pages a user may not open, and then the groups left with nothing in them.
     *
     * @param array<int, array<string, mixed>> $sections
     * @param callable(string): bool           $can
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filter(array $sections, callable $can): array
    {
        $visible = [];

        foreach ($sections as $section) {
            if (! isset($section['items'])) {
                if (self::allowed($section, $can)) {
                    $visible[] = $section;
                }

                continue;
            }

            $items = array_values(array_filter(
                $section['items'],
                static fn (array $item): bool => self::allowed($item, $can)
            ));

            if ($items === []) {
                continue;
            }

            $section['items'] = $items;
            $visible[]        = $section;
        }

        return $visible;
    }

    /** @return array<int, array<string, mixed>> */
    public function sections(): array
    {
        return $this->sections;
    }

    public function currentUrl(): string
    {
        return $this->currentUrl;
    }

    /** Where an item points: an off-site URL as written, everything else through base_url(). */
    public function href(array $item): string
    {
        $path = (string) ($item['path'] ?? '');

        return self::isAbsolute($path) ? $path : base_url($path);
    }

    /** The extra attributes a new-tab link needs, and nothing at all for an ordinary one. */
    public function target(array $item): string
    {
        return empty($item['external']) ? '' : ' target="_blank" rel="noopener"';
    }

    /** The class a live counter looks for, e.g. ' js-drift-target' — empty when nothing counts it. */
    public function badgeHook(array $item): string
    {
        $badge = (string) ($item['badge'] ?? '');

        return $badge === '' ? '' : ' js-' . preg_replace('/[^a-z0-9]+/i', '-', $badge) . '-target';
    }

    /**
     * Is this entry a page of this application?
     *
     * Off-site links and static files (a served .html, a report) are neither routed nor ever "where
     * you are", and the checks that hold the menu to real routes ask this question rather than
     * re-deciding it from the string.
     */
    public static function isPage(array $item): bool
    {
        $path = (string) ($item['path'] ?? '');

        return ! str_contains($path, '://') && ! str_contains(basename($path), '.');
    }

    public function isActive(array $item): bool
    {
        return $this->isItemActive($item);
    }

    /** A group counts as "here" when the page you are on is one of its pages. */
    public function isGroupActive(array $group): bool
    {
        foreach ($group['items'] ?? [] as $item) {
            if ($this->isItemActive($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is the operator on this item's page?
     *
     * Prefix-aware on purpose: `/configjobs/detail/12` is the Config Jobs page, and a menu that
     * forgets it is a menu that says you are nowhere every time you open a detail view. The
     * comparison never crosses a path segment, so `configjobs` does not claim `configjobs-archive`.
     */
    public static function matches(string $currentUrl, string $path): bool
    {
        if ($path === '' || self::isAbsolute($path)) {
            return false;   // a link that leaves the application is never "where you are"
        }

        $target = self::pathOf(base_url($path));

        if ($target === '') {
            return false;
        }

        $current = self::pathOf($currentUrl);

        return $current === $target || str_starts_with($current, $target . '/');
    }

    private function isItemActive(array $item): bool
    {
        if (! empty($item['external'])) {
            return false;
        }

        $paths = array_merge([(string) ($item['path'] ?? '')], $item['match'] ?? []);

        foreach ($paths as $path) {
            if (self::matches($this->currentUrl, (string) $path)) {
                return true;
            }
        }

        return false;
    }

    /** @param callable(string): bool $can */
    private static function allowed(array $item, callable $can): bool
    {
        $permission = $item['permission'] ?? null;

        if ($permission !== null && $permission !== '' && ! (bool) $can((string) $permission)) {
            return false;
        }

        // Integration switches: an item tied to LibreNMS or Grafana disappears when that
        // integration is disabled in Core Settings, regardless of the viewer's permissions.
        $setting = $item['setting'] ?? null;

        if ($setting === 'librenms' && ! Monitoring::librenmsEnabled()) {
            return false;
        }

        if ($setting === 'grafana' && ! Monitoring::grafanaEnabled()) {
            return false;
        }

        return true;
    }

    private static function isAbsolute(string $path): bool
    {
        return preg_match('#^[a-z][a-z0-9+.\-]*://#i', $path) === 1;
    }

    /**
     * The path of a URL, however it was spelled: query strings, a trailing slash, repeated slashes and
     * an `index.php` in the path are all the same page as far as the menu is concerned.
     */
    private static function pathOf(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = (string) preg_replace('#/index\.php(?=/|$)#', '', $path);
        $path = (string) preg_replace('#/+#', '/', $path);

        return trim($path, '/');
    }
}
