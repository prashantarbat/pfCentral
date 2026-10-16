<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Exceptions\AuthorizationException;
use CodeIgniter\Shield\Exceptions\ValidationException;
use Throwable;

/**
 * User accounts, on Shield's own models and vocabulary.
 *
 * Everything the users page can do to an account lives here, so the controller stays a thin HTTP
 * layer and each decision can be reasoned about in one place. Three of them are worth stating up
 * front, because the page could previously do harm with them:
 *
 *  - **Deletion is a soft delete plus a ban.** `config_jobs.requested_by`, `config_push_logs.user_id`
 *    and `config_templates.created_by` all name a user id: the change pipeline's history is a record
 *    of *who* requested and approved each change, and a hard delete would leave that history pointing
 *    at nothing. The account leaves every list, cannot sign in, and its rows stay resolvable.
 *  - **Nobody may remove the last user administrator**, including themselves — the guards are on
 *    self-deactivation, self-ban, self-deletion and on dropping the last account that holds
 *    `users.manage`, because that is the account which could undo the mistake.
 *  - **A new account's username is the administrator's to choose.** Left blank it is derived from the
 *    email address, which is the convenient default; typed, it is validated and checked for
 *    uniqueness like any other login name. It is offered because Shield compares a new password
 *    against the username, and this app configures that comparison strictly — see
 *    {@see resolveUsername()}.
 *  - **A state change is applied to a freshly read row, and a password is judged against the name the
 *    account will have.** Shield mutates the object it is given and CI4 writes only the attributes that
 *    object reports as changed, so toggling a stale copy can report success while writing nothing; and
 *    a save that renames an account and sets its password must measure the password against the new
 *    name, which is the one it will sign in with.
 *  - **An Active Directory account's profile and password are not editable here.** They are synced
 *    from AD at sign-in, so accepting a local edit would be accepting a change that is silently
 *    thrown away at the next login. Its role is editable: the app maps roles from AD at sign-in, and
 *    an administrator overriding one until the next sync is an explicit, documented warning in the
 *    view.
 *
 * @phpstan-type Outcome array{ok: bool, message: string, errors: array<string, string>, user: ?User}
 */
class UserAccountService
{
    /** Shield stores email/password logins here; the app's logins are all this type today. */
    private const IDENTITY_TYPE = 'email_password';

    private UserModel $users;
    private BaseConnection $db;

    /** @var callable(): int */
    private $managerCounter;

    /**
     * @param callable(): int|null $managerCount a seam for verify:users, which has to exercise the
     *                                           last-manager guard on a database that already holds real
     *                                           administrators. Production counts the real rows.
     */
    public function __construct(?UserModel $users = null, ?callable $managerCount = null)
    {
        $this->users = $users ?? model(UserModel::class);
        $this->db    = db_connect();

        $this->managerCounter = $managerCount ?? fn (): int => $this->countManagers();
    }

    // ------------------------------------------------------------------ reading

    /**
     * Every account, with its identities loaded in one query rather than one per row.
     *
     * @return list<User>
     */
    public function accounts(): array
    {
        return $this->users->withIdentities()->orderBy('users.id', 'asc')->findAll();
    }

    public function find(int $id): ?User
    {
        return $this->users->withIdentities()->findById($id);
    }

    /**
     * Every account's roles, in one query: the list needs them for each row, and asking Shield per
     * user would be a query per row on a page that is already the heaviest in the app.
     *
     * @return array<int, list<string>>
     */
    public function roles(): array
    {
        $rows  = $this->db->table('auth_groups_users')->select('user_id, group')->get()->getResultArray();
        $roles = [];

        foreach ($rows as $row) {
            $roles[(int) $row['user_id']][] = (string) $row['group'];
        }

        return $roles;
    }

    /** @return list<string> */
    public function assignableRoles(): array
    {
        return array_map(static fn ($group): string => (string) $group, array_keys(config('AuthGroups')->groups));
    }

    public function managerCount(): int
    {
        return ($this->managerCounter)();
    }

    /**
     * How many accounts *other than this one* can manage users — which is the question the guard asks.
     *
     * Distinct accounts, not group rows: a user in two managing groups is one administrator.
     */
    public function otherManagerCount(User $user): int
    {
        return $user->id === null ? $this->managerCount() : max(0, $this->managerCount() - 1);
    }

    private function countManagers(): int
    {
        $groups = UserRules::groupsWith('users.manage');

        if ($groups === []) {
            return 0;
        }

        // Raw, not selectCount(): the builder escapes the field name, so `selectCount('DISTINCT user_id')`
        // came out as a single quoted identifier and MySQL refused the statement outright — which meant
        // the guard meant to protect the last administrator threw instead of counting.
        $row = $this->db->table('auth_groups_users')
            ->select('COUNT(DISTINCT user_id) AS managers', false)
            ->whereIn('group', $groups)
            ->get()
            ->getRowArray();

        return (int) ($row['managers'] ?? 0);
    }

    /** Is this account one whose removal would lock the users page? */
    public function managesUsers(User $user): bool
    {
        foreach ($user->getGroups() ?? [] as $group) {
            if (in_array($group, UserRules::groupsWith('users.manage'), true)) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------ writing

    /**
     * Create a local account: profile, Shield identity (email + password) and one role.
     *
     * The account is created **active**: an administrator adding somebody deliberately is not the
     * self-registration path that needs an activation step, and with registration closed there is no
     * mail flow to fall back on.
     *
     * @param array<string, mixed> $input
     *
     * @return Outcome
     */
    public function create(array $input, ?User $actor): array
    {
        $input  = $this->strings($input);
        $errors = [];

        $email = UserRules::email($input['email'] ?? '');

        if ($email === null) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($this->emailTaken($email)) {
            $errors['email'] = 'That email address already belongs to an account.';
        }

        foreach (['first_name' => 'first name', 'last_name' => 'last name'] as $field => $label) {
            if (($input[$field] ?? '') === '') {
                $errors[$field] = 'A ' . $label . ' is required.';
            }
        }

        $mobile = UserRules::mobile($input['mobile_number'] ?? '');

        if ($mobile === null) {
            $errors['mobile_number'] = 'Enter a valid 7 to 15 digit phone number (e.g. +15550192831).';
        }

        $password     = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirm'] ?? '');

        foreach (UserRules::passwordProblems($password, $confirmation, true) as $problem) {
            $errors['password'] = $problem;
        }

        $role = (string) ($input['group'] ?? '');

        if (! in_array($role, $this->assignableRoles(), true)) {
            $errors['group'] = 'Choose one of the roles that exist.';
        }

        $username = $this->resolveUsername((string) ($input['username'] ?? ''), $email ?? '', $errors);

        // Empty strings rather than nulls: Shield's setters are typed, and this entity is only ever
        // saved when validation above has satisfied itself that both are real values.
        /** @var User $user */
        $user = new User([
            'username'      => $username,
            'email'         => $email ?? '',
            'password'      => $password,
            'first_name'    => $input['first_name'],
            'last_name'     => $input['last_name'],
            'mobile_number' => $mobile ?? '',
            'location'      => $input['location'] ?? '',
            'department'    => $input['department'] ?? '',
            'is_ad'         => 0,
            'active'        => 1,
        ]);

        // Checked against the account being created, so Shield's "nothing personal" rule can see the
        // name and address the password is not allowed to repeat.
        $strength = $this->passwordStrength($password, $user);

        if ($strength !== null) {
            $errors['password'] = $strength;
        }

        if ($errors !== []) {
            return $this->fail($errors);
        }

        try {
            $this->users->save($user);
            $user = $this->users->findById($this->users->getInsertID());

            if ($user === null) {
                return $this->fail(['email' => 'The account could not be read back after saving.']);
            }

            $user->addGroup($role);
        } catch (ValidationException $e) {
            return $this->fail(['email' => $e->getMessage()]);
        } catch (Throwable $e) {
            log_message('error', 'User creation failed: ' . $e->getMessage());

            return $this->fail(['email' => 'The account could not be created: ' . $e->getMessage()]);
        }

        return [
            'ok'      => true,
            'message' => $user->first_name . ' ' . $user->last_name . ' can sign in with ' . $email . '.',
            'errors'  => [],
            'user'    => $user,
        ];
    }

    /**
     * Update a profile, and optionally its password and its role.
     *
     * @param array<string, mixed> $input
     *
     * @return Outcome
     */
    public function update(User $user, array $input, ?User $actor): array
    {
        $input  = $this->strings($input);
        $errors = [];
        $isAd   = (bool) ($user->is_ad ?? false);

        if ($isAd) {
            $errors = $this->adProfileErrors($user, $input);
        }

        $email = UserRules::email($input['email'] ?? (string) $user->email);

        if (! $isAd) {
            if ($email === null) {
                $errors['email'] = 'Enter a valid email address.';
            } elseif ($this->emailTaken($email, (int) $user->id)) {
                $errors['email'] = 'That email address already belongs to another account.';
            }
        }

        $mobile = UserRules::mobile($input['mobile_number'] ?? (string) $user->mobile_number);

        if ($mobile === null) {
            $errors['mobile_number'] = 'Enter a valid 7 to 15 digit phone number (e.g. +15550192831).';
        }

        if ($isAd && $mobile !== null && $mobile !== (string) $user->mobile_number) {
            $errors['mobile_number'] = $this->adRefusal();
        }

        $username = null;

        if (! $isAd && ($input['username'] ?? '') !== '' && $input['username'] !== (string) $user->username) {
            $username = UserRules::username($input['username']);

            if ($username === null) {
                $errors['username'] = 'A username is 3 to 30 characters of letters, numbers and dots.';
            } elseif ($this->usernameTaken($username, (int) $user->id)) {
                $errors['username'] = 'That username already belongs to another account.';
            }
        }

        $password     = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirm'] ?? '');

        foreach (UserRules::passwordProblems($password, $confirmation, false) as $problem) {
            $errors['password'] = $problem;
        }

        if ($password !== '' && $isAd) {
            $errors['password'] = UserRules::refusalToSetPasswordForAdUser(true);
        }

        $role  = (string) ($input['group'] ?? '');
        $roles = $this->assignableRoles();

        if ($role !== '' && ! in_array($role, $roles, true)) {
            $errors['group'] = 'Choose one of the roles that exist.';
        }

        // The role that manages accounts is the one that can undo this page, so the last holder of it
        // cannot lose it — by their own hand or anybody else's.
        if ($role !== '' && $this->managesUsers($user) && ! in_array($role, UserRules::groupsWith('users.manage'), true)) {
            $refusal = UserRules::refusalToRemoveLastManager(true, $this->otherManagerCount($user), 'manage users');

            if ($refusal !== null) {
                $errors['group'] = $refusal;
            }
        }

        if ($password !== '') {
            // Checked against the name the account will have once this save is done, not the one it has
            // while the form is open. An edit can set both at once, and a password has to be measured
            // against the name it will sit next to — otherwise choosing a new username is a way around
            // the rule rather than the escape hatch it is meant to be.
            $probe = clone $user;

            if ($username !== null) {
                $probe->username = $username;
            }

            $strength = $this->passwordStrength($password, $probe);

            if ($strength !== null) {
                $errors['password'] = $strength;
            }
        }

        if ($errors !== []) {
            return $this->fail($errors);
        }

        try {
            if (! $isAd) {
                $user->email    = (string) $email;
                $user->username = $username ?? $user->username;
                $user->first_name    = $input['first_name'] ?? $user->first_name;
                $user->last_name     = $input['last_name'] ?? $user->last_name;
                $user->middle_name   = $input['middle_name'] ?? $user->middle_name;
                $user->location      = $input['location'] ?? $user->location;
                $user->department    = $input['department'] ?? $user->department;
            }

            $user->mobile_number = (string) $mobile;

            if ($password !== '') {
                $user->password = $password;
            }

            $this->users->save($user);

            if ($role !== '') {
                $user->syncGroups($role);
            }
        } catch (AuthorizationException $e) {
            return $this->fail(['group' => $e->getMessage()]);
        } catch (Throwable $e) {
            log_message('error', 'User update failed: ' . $e->getMessage());

            return $this->fail(['email' => 'The account could not be saved: ' . $e->getMessage()]);
        }

        return [
            'ok'      => true,
            'message' => 'Saved. ' . ($this->users->findById((int) $user->id)?->email ?? ''),
            'errors'  => [],
            'user'    => $this->users->findById((int) $user->id),
        ];
    }

    /**
     * Ban or unban an account.
     *
     * Banning is the control that stops a sign-in outright — including on the Active Directory path,
     * which is why the login controller checks it too.
     *
     * @return Outcome
     */
    public function setBanned(User $user, bool $banned, ?User $actor): array
    {
        // Shield's ban()/unBan() mutate whatever object they are given and save it, and CI4 writes only
        // the attributes an entity reports as *changed* against the values it was loaded with. The guard
        // and the write are therefore both about a freshly read copy: an account banned and unbanned
        // through one object can no longer be reported as updated when nothing was written.
        $target = $this->fresh($user);

        if ($banned) {
            $refusal = UserRules::refusalToChangeSelf($this->actorId($actor), (int) $user->id, 'ban')
                ?? UserRules::refusalToRemoveLastManager($this->managesUsers($target), $this->otherManagerCount($target), 'sign in');

            if ($refusal !== null) {
                return $this->fail(['ban' => $refusal]);
            }
        }

        try {
            $banned
                ? $target->ban('Banned from the pfCentral console.')
                : $target->unBan();
        } catch (Throwable $e) {
            log_message('error', 'User ban failed: ' . $e->getMessage());

            return $this->fail(['ban' => 'The account could not be updated.']);
        }

        return $this->done($banned ? 'Banned' : 'Unbanned');
    }

    /**
     * Activate or deactivate an account.
     *
     * Deactivating blocks Shield's own sign-in. It is deliberately *not* enforced on the AD path:
     * several accounts that sign in through AD today carry `active = 0`, so enforcing it there would
     * lock out people who are working. The ban is the control that stops everybody.
     *
     * @return Outcome
     */
    public function setActive(User $user, bool $active, ?User $actor): array
    {
        // Freshly read, for the reason setBanned() records.
        $target = $this->fresh($user);

        if (! $active) {
            $refusal = UserRules::refusalToChangeSelf($this->actorId($actor), (int) $user->id, 'deactivate')
                ?? UserRules::refusalToRemoveLastManager($this->managesUsers($target), $this->otherManagerCount($target), 'sign in');

            if ($refusal !== null) {
                return $this->fail(['active' => $refusal]);
            }
        }

        try {
            $active ? $target->activate() : $target->deactivate();
        } catch (Throwable $e) {
            log_message('error', 'User activation change failed: ' . $e->getMessage());

            return $this->fail(['active' => 'The account could not be updated.']);
        }

        return $this->done($active ? 'Activated' : 'Deactivated');
    }

    /**
     * Change a local password, checked against Shield's configured rules.
     *
     * @return Outcome
     */
    public function changePassword(User $user, string $password, string $confirmation, ?User $actor): array
    {
        $refusal = UserRules::refusalToSetPasswordForAdUser((bool) ($user->is_ad ?? false));

        if ($refusal !== null) {
            return $this->fail(['password' => $refusal]);
        }

        $errors = [];

        foreach (UserRules::passwordProblems($password, $confirmation, true) as $problem) {
            $errors['password'] = $problem;
        }

        if ($errors === []) {
            $strength = $this->passwordStrength($password, $user);

            if ($strength !== null) {
                $errors['password'] = $strength;
            }
        }

        if ($errors !== []) {
            return $this->fail($errors);
        }

        try {
            // Setting the plain password and saving lets Shield hash it into the identity, which is
            // also where the strength check reads the account from.
            $user->password = $password;
            $this->users->save($user);
        } catch (Throwable $e) {
            log_message('error', 'Password change failed: ' . $e->getMessage());

            return $this->fail(['password' => 'The password could not be changed.']);
        }

        return $this->done('Password changed for ' . ($user->email ?? ('#' . $user->id)));
    }

    /**
     * Take an account out of service.
     *
     * Rows are kept (see the class note): the account is banned, deactivated, its remembered sessions
     * are dropped, and it is soft-deleted so it leaves every list and cannot sign in again. Its email
     * address stays taken, deliberately — reusing it would put a new person's name on an old person's
     * change history.
     *
     * @return Outcome
     */
    public function delete(User $user, ?User $actor): array
    {
        // Freshly read, for the reason setBanned() records: a removal bans and deactivates the account
        // before it is soft-deleted, and those writes must land on the row that exists.
        $target = $this->fresh($user);

        $refusal = UserRules::refusalToChangeSelf($this->actorId($actor), (int) $user->id, 'delete')
            ?? UserRules::refusalToRemoveLastManager($this->managesUsers($target), $this->otherManagerCount($target), 'manage users');

        if ($refusal !== null) {
            return $this->fail(['delete' => $refusal]);
        }

        $id = (int) $user->id;

        try {
            $this->db->transStart();

            $target->ban('Account removed from the pfCentral console.');

            if ($target->active) {
                $target->deactivate();
            }

            // Remembered sessions and tokens must not survive the account.
            $this->db->table('auth_remember_tokens')->where('user_id', $id)->delete();
            $this->db->table('auth_token_logins')->where('user_id', $id)->delete();

            $this->users->delete($id);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->fail(['delete' => 'The account could not be removed.']);
            }
        } catch (Throwable $e) {
            log_message('error', 'User deletion failed: ' . $e->getMessage());

            return $this->fail(['delete' => 'The account could not be removed.']);
        }

        return $this->done('Deleted. The change history that names them is kept.');
    }

    // ------------------------------------------------------------------ internals

    /**
     * The login name to give a new account: the one the administrator typed, or one derived from the
     * address.
     *
     * Derived is the convenient default, but it is not a harmless one: Shield's NothingPersonalValidator
     * compares the chosen password against the username, and that validator is configured strictly here
     * (`Config\Auth::$maxSimilarity` is 10, where Shield's own documentation suggests 50). So an account
     * whose address is long can be refused a perfectly good password for a username nobody typed. An
     * administrator who meets that refusal can now answer it by choosing the username instead of by
     * weakening the password, which is what this parameter is for.
     *
     * @param array<string, string> $errors filled in with the reason, if there is one
     */
    private function resolveUsername(string $typed, string $email, array &$errors): string
    {
        if (trim($typed) === '') {
            return $this->availableUsername(UserRules::usernameFrom($email));
        }

        $username = UserRules::username($typed);

        if ($username === null) {
            $errors['username'] = 'A username is 3 to 30 characters of letters, numbers and dots.';

            return '';
        }

        if ($this->usernameTaken($username)) {
            $errors['username'] = 'That username already belongs to another account.';

            return '';
        }

        return $username;
    }

    /**
     * A reason the password is not acceptable, or null when Shield is happy with it.
     *
     * Shield's own configured validators decide this (composition, "nothing personal", dictionary), so
     * the rule an administrator meets here is the rule Shield enforces everywhere else.
     */
    private function passwordStrength(string $password, User $user): ?string
    {
        try {
            $result = service('passwords')->check($password, $user);
        } catch (Throwable $e) {
            log_message('error', 'Password validator failed: ' . $e->getMessage());

            return null;   // never block an account edit because a validator misbehaved
        }

        if ($result->isOK()) {
            return null;
        }

        $reason = $result->reason();

        return is_array($reason) ? implode(' ', $reason) : (string) $reason;
    }

    /**
     * Which of the profile fields an AD-backed account refuses, given what was submitted.
     *
     * Only *changes* are refused: the view renders the current values into a read-only form, and a
     * form that posts back exactly what it was given is not an attempt to edit anything.
     *
     * @param array<string, string> $input
     *
     * @return array<string, string>
     */
    private function adProfileErrors(User $user, array $input): array
    {
        $protected = [
            'email'       => (string) $user->email,
            'username'    => (string) $user->username,
            'first_name'  => (string) $user->first_name,
            'middle_name' => (string) $user->middle_name,
            'last_name'   => (string) $user->last_name,
            'location'    => (string) $user->location,
            'department'  => (string) $user->department,
        ];

        foreach ($protected as $field => $current) {
            $submitted = $input[$field] ?? null;

            if ($submitted !== null && $submitted !== $current) {
                return ['email' => $this->adRefusal()];
            }
        }

        return [];
    }

    private function adRefusal(): string
    {
        return 'This account authenticates through Active Directory: its profile is synchronised at sign-in and cannot be edited here. Its role can be.';
    }

    /** Does any account already own this address? Soft-deleted accounts count, so history stays honest. */
    private function emailTaken(string $email, ?int $exceptUserId = null): bool
    {
        $builder = $this->db->table('auth_identities')
            ->select('user_id')
            ->like('type', 'email', 'after')
            ->where('secret', $email);

        $row = $builder->get()->getRowArray();

        if ($row === null) {
            return false;
        }

        return $exceptUserId === null || (int) $row['user_id'] !== $exceptUserId;
    }

    private function usernameTaken(string $username, ?int $exceptUserId = null): bool
    {
        $builder = $this->db->table('users')->select('id')->where('username', $username);

        if ($exceptUserId !== null) {
            $builder->where('id !=', $exceptUserId);
        }

        return $builder->countAllResults() > 0;
    }

    /** A username based on the address that no account — including a deleted one — is already using. */
    private function availableUsername(string $preferred): string
    {
        $taken = array_column(
            $this->db->table('users')->select('username')->where('username IS NOT NULL')->get()->getResultArray(),
            'username'
        );

        return UserRules::freeUsername($preferred, $taken);
    }

    /**
     * The account as the database holds it, for a state change to be applied to.
     *
     * Falls back to the copy it was handed if the row cannot be re-read (it was removed since), so a
     * refusal still comes from the guards rather than from a missing row.
     */
    private function fresh(User $user): User
    {
        if ($user->id === null) {
            return $user;
        }

        return $this->users->withDeleted()->withIdentities()->findById((int) $user->id) ?? $user;
    }

    private function actorId(?User $actor): ?int
    {
        return $actor === null || $actor->id === null ? null : (int) $actor->id;
    }

    /** @param array<string, mixed> $input */
    private function strings(array $input): array
    {
        $clean = [];

        foreach ($input as $key => $value) {
            $clean[(string) $key] = is_scalar($value) ? trim((string) $value) : '';
        }

        return $clean;
    }

    /** @param array<string, string> $errors */
    private function fail(array $errors): array
    {
        return [
            'ok'      => false,
            'message' => (string) reset($errors),
            'errors'  => $errors,
            'user'    => null,
        ];
    }

    private function done(string $message): array
    {
        return [
            'ok'      => true,
            'message' => $message,
            'errors'  => [],
            'user'    => null,
        ];
    }
}
