<?php

namespace App\Controllers;

use App\Models\CoreConfigModel;
use App\Services\Users\UserAccountService;
use App\Services\Users\UserRules;
use CodeIgniter\Shield\Entities\User;

/**
 * User accounts.
 *
 * Every action here is about *somebody else's* account, and every one of them is gated on
 * `users.manage` — the permission the page's own view requires, and the one the menu uses to decide
 * whether to show the entry at all. (This controller used to require `view_users`, `create_user`,
 * `edit_user` and `delete_user`, none of which exist in this app's Shield vocabulary, which is why an
 * administrator was redirected away from their own users page.)
 *
 * The work is in {@see UserAccountService}: the rules, the guards against removing the last account
 * that can manage users, and the Shield calls. This class decides only how an answer is delivered —
 * JSON for the modals that expect it, a redirect with a flash message for the ordinary forms — so a
 * browser with JavaScript and one without get the same result from the same code path.
 */
class UsersController extends BaseController
{
    protected $helpers = ['form', 'url'];

    private UserAccountService $accounts;
    private CoreConfigModel $coreConfig;

    public function __construct()
    {
        $this->accounts   = new UserAccountService();
        $this->coreConfig = new CoreConfigModel();
    }

    /** The list: every account, its role, its state, and what can be done to it. */
    public function index()
    {
        $actor = $this->actor();

        if ($actor === null) {
            return $this->refuse();
        }

        $data = $this->pageData('Users Management');

        $data['users']  = $this->accounts->accounts();
        $data['roles']  = $this->accounts->roles();
        $data['groups'] = $this->accounts->assignableRoles();

        // The view disables the controls the service would refuse, so a rule is visible before it is
        // broken: the last account that can manage users cannot be banned, deactivated or removed, and
        // nobody can do any of that to their own account.
        $data['managers']      = $this->accounts->managerCount();
        $data['managerGroups'] = UserRules::groupsWith('users.manage');

        return $this->render('Auth/Users/index', $data);
    }

    /** One account, as a page. */
    public function show(int $id)
    {
        $actor = $this->actor();

        if ($actor === null) {
            return $this->refuse();
        }

        $user = $this->accounts->find($id);

        if ($user === null) {
            return $this->refuse('That account does not exist.', 'users');
        }

        $data = $this->pageData(trim($user->first_name . ' ' . $user->last_name) ?: 'Account');

        $data['user']   = $user;
        $data['roles']  = $user->getGroups() ?? [];
        $data['groups'] = $this->accounts->assignableRoles();

        return $this->render('Auth/Users/show', $data);
    }

    /** Create an account: the Add modal. */
    public function add()
    {
        $actor = $this->actor();

        if ($actor === null) {
            return $this->refuse();
        }

        $outcome = $this->accounts->create($this->request->getPost(), $actor);

        return $this->answer($outcome, 'users');
    }

    /**
     * Edit an account.
     *
     * Two callers, one path: the list's edit modal posts here and expects JSON, the profile page posts
     * here as an ordinary form and expects to land back on the profile.
     */
    public function update(int $id)
    {
        $actor = $this->actor();

        if ($actor === null) {
            return $this->refuse();
        }

        $user = $this->accounts->find($id);

        if ($user === null) {
            return $this->refuse('That account does not exist.', 'users');
        }

        $outcome = $this->accounts->update($user, $this->request->getPost(), $actor);

        return $this->answer($outcome, 'users/show/' . $id);
    }

    /** Remove an account: deactivated, banned and soft-deleted, with its history kept. */
    public function delete(int $id)
    {
        $actor = $this->actor();

        if ($actor === null) {
            return $this->refuse();
        }

        $user = $this->accounts->find($id);

        if ($user === null) {
            return $this->answer(['ok' => false, 'message' => 'That account does not exist.', 'errors' => [], 'user' => null], 'users');
        }

        return $this->answer($this->accounts->delete($user, $actor), 'users');
    }

    /** Ban or unban: the control that stops a sign-in outright, AD included. */
    public function toggleBan(int $id)
    {
        return $this->toggle($id, 'ban');
    }

    /** Activate or deactivate: what Shield's own sign-in checks. */
    public function toggleActive(int $id)
    {
        return $this->toggle($id, 'active');
    }

    /** Set a new password for a local account. */
    public function changePassword(int $id)
    {
        $actor = $this->actor();

        if ($actor === null) {
            return $this->refuse();
        }

        $user = $this->accounts->find($id);

        if ($user === null) {
            return $this->answer(['ok' => false, 'message' => 'That account does not exist.', 'errors' => [], 'user' => null], 'users');
        }

        $outcome = $this->accounts->changePassword(
            $user,
            (string) $this->request->getPost('new_password'),
            (string) $this->request->getPost('password_confirm'),
            $actor
        );

        return $this->answer($outcome, 'users');
    }

    // ------------------------------------------------------------------ internals

    /** The signed-in account, or null when it may not manage users. */
    private function actor(): ?User
    {
        $user = auth()->user();

        return $user !== null && $user->can('users.manage') ? $user : null;
    }

    /**
     * Ban, unban, activate or deactivate one account.
     *
     * @param string $switch 'ban' or 'active'
     */
    private function toggle(int $id, string $switch)
    {
        $actor = $this->actor();

        if ($actor === null) {
            return $this->refuse();
        }

        $user = $this->accounts->find($id);

        if ($user === null) {
            return $this->answer(['ok' => false, 'message' => 'That account does not exist.', 'errors' => [], 'user' => null], 'users');
        }

        $outcome = $switch === 'ban'
            ? $this->accounts->setBanned($user, ! $user->isBanned(), $actor)
            : $this->accounts->setActive($user, ! $user->active, $actor);

        return $this->answer($outcome, 'users');
    }

    /**
     * Deliver an outcome in the shape the caller is waiting for.
     *
     * The two modal handlers in the list read different keys for failures (`errors` when editing,
     * `messages` when adding), so both are supplied — the alternative is a modal that silently shows
     * nothing when a rule refuses a save.
     *
     * @param array{ok: bool, message: string, errors: array<string, string>, user: ?User} $outcome
     */
    private function answer(array $outcome, string $redirectTo)
    {
        if ($this->request->isAJAX()) {
            $body = [
                'status'  => $outcome['ok'] ? 'success' : 'error',
                'message' => $outcome['message'],
            ];

            if (! $outcome['ok']) {
                $body['errors']   = $outcome['errors'] === [] ? ['error' => $outcome['message']] : $outcome['errors'];
                $body['messages'] = $body['errors'];
            }

            return $this->response->setStatusCode($outcome['ok'] ? 200 : 422)->setJSON($body);
        }

        $flash = $outcome['ok'] ? 'message' : 'error';

        return redirect()->to(base_url($redirectTo))->with($flash, $outcome['message']);
    }

    /**
     * Refuse: a 403 to a script, a redirect with the reason to a person.
     *
     * @return mixed
     */
    private function refuse(string $message = 'You are not allowed to manage users.', string $redirectTo = 'loggedlive')
    {
        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'   => 'error',
                'message'  => $message,
                'errors'   => ['error' => $message],
                'messages' => ['error' => $message],
            ]);
        }

        return redirect()->to(base_url($redirectTo))->with('error', $message);
    }

    /** @return array<string, mixed> */
    private function pageData(string $title): array
    {
        $appName = $this->coreConfig->where('name', 'app_name')->first()['value'] ?? 'My Application';

        return [
            'title'       => strip_tags($appName) . ' 🔸 ' . $title,
            'app_name'    => $appName,
            'app_version' => $this->coreConfig->where('name', 'app_version')->first()['value'] ?? '1.0.0',
            'first_name'  => auth()->user()?->first_name ?? 'Not Login',
        ];
    }

    /** @param array<string, mixed> $data */
    private function render(string $view, array $data): string
    {
        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view($view, $data)
            . view('frontend/footer', $data);
    }
}
