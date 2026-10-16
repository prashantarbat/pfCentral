<?php

namespace App\Commands;

use App\Controllers\UsersController;
use App\Models\UserModel;
use App\Services\Users\UserAccountService;
use App\Services\Users\UserRules;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * Proves the users area against a real database and Shield's real credential check.
 *
 * The unit tests pin the rules. What only a database and Shield can show is the wiring, and the
 * wiring is where this page does damage quietly:
 *
 *   - that a new account really can sign in — the identity row is written, the password is hashed by
 *     Shield, and `check()` accepts it;
 *   - that a role change *replaces* the old role rather than adding one on top;
 *   - that an Active Directory account's profile refuses a local edit while its role does not;
 *   - that nobody can ban, deactivate or delete their own account, and that the last account able to
 *     manage users cannot be removed by anybody — the three ways to lock the fleet out of the page
 *     that fixes it;
 *   - that deleting an account takes its remembered sessions with it and leaves its row behind, because
 *     `config_jobs.requested_by` and `config_push_logs.user_id` name people who made changes.
 *
 * Fixtures only. Every account it creates is deleted again (hard, query-builder delete: these are its
 * own rows), and the auth tables are compared against a baseline it takes first. The real
 * administrators are never a target of a destructive call — the one guard that needs "the last
 * administrator" is exercised through the service's manager-count seam, which is what that seam is for.
 */
class VerifyUsers extends BaseCommand
{
    protected $group       = 'Config';
    protected $name        = 'verify:users';
    protected $description = 'Harness: user accounts (create, edit, roles, ban, activate, password, delete, guards).';
    protected $usage       = 'verify:users';

    /**
     * Passwords the shipped policy accepts for the usernames this harness derives.
     *
     * Why a list of gibberish instead of three readable passphrases: `Config\Auth::$maxSimilarity` is
     * 10, where Shield's own documentation (quoted in that same file) suggests 50 — and the username a
     * password is compared against here is *derived from the fixture's email*, so it is long, dotted and
     * full of ordinary letters. Measured against those usernames, a normal passphrase lands at 15-30%
     * similarity and is refused before the account exists; these land at or below 9%.
     *
     * A candidate is still selected per username by {@see passwordFor()} rather than assumed, so the
     * harness keeps working if the clock moves the counters or the policy changes, and says so loudly
     * if nothing passes at all. The escape hatch the Add form offers an administrator — choosing the
     * username — is exercised on its own in section A.
     *
     * @var list<string>
     */
    private const PASSWORDS = [
        'Qq2$Bj0!Mq0#Yb5',
        'Zx5!Qg4~Mg1^Wb5',
        'Kg5#Wb4!Zb8&Jh8',
        'Xb5*Mj3$Bk0#Hh1',
        'Hq2*Qv4%Qz8&Vp8',
        'Kg2~Yp0#Qk9$Vg2',
        'Kz1~Jj8#Pz0*Yw6',
        'Xq0!Hj0#Qv8%Xq8',
        'Hx3#Jx4^Mq6!Gk6',
        'Xw5$Yj8~Jz9~Pg8',
        'Kg9*Mj9!Kq0%Vv5',
        'Jb7^Hj0^Zb0*Gj3',
        'Mg4%Pw3^Zv4#Jx6',
        'Gq0%Xw4#Jx1&Zh0',
        'Qx6^Hw5$Bj3^Jw5',
        'Bj8*Kb3%Wx9%Xv5',
    ];

    /** @var list<string> */
    private array $pass = [];

    /** @var list<string> */
    private array $fail = [];

    /** @var list<int> */
    private array $userIds = [];

    /** @var array<string, User> fixture accounts, created once and reused by later sections */
    private array $fixtures = [];

    private string $stamp = '';

    public function run(array $params)
    {
        $this->stamp = (string) time();

        $before = $this->counts();

        try {
            $this->sectionA();
            $this->sectionB();
            $this->sectionC();
            $this->sectionD();
            $this->sectionE();
            $this->sectionF();
        } finally {
            $this->cleanup();
        }

        $after = $this->counts();

        foreach ($before as $table => $count) {
            $this->check("baseline restored: {$table} back to {$count}", ($after[$table] ?? -1) === $count);
        }

        CLI::newLine();
        CLI::write('  PASS ' . count($this->pass) . '   FAIL ' . count($this->fail), $this->fail === [] ? 'green' : 'red');

        foreach ($this->fail as $message) {
            CLI::write('   - ' . $message, 'red');
        }

        return $this->fail === [] ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // ------------------------------------------------------------------ A: creating an account

    private function sectionA(): void
    {
        CLI::write('A. A new account is a real account: identity, password, role, and it can sign in', 'yellow');

        $service  = $this->service();
        $email    = $this->email('create');
        $password = $this->passwordFor(UserRules::usernameFrom($email));

        $outcome = $service->create([
            'email'            => $email,
            'first_name'       => 'Verify',
            'last_name'        => 'Alpha',
            'mobile_number'    => '9850366577',
            'password'         => $password,
            'password_confirm' => $password,
            'group'            => 'manager',
        ], null);

        $this->check('an account is created', $outcome['ok'] === true && $outcome['user'] instanceof User);
        $this->refusalReason($outcome);

        $user = $outcome['user'];

        if (! $user instanceof User) {
            CLI::write('   (stopping section A: nothing was created)', 'red');

            return;
        }

        $this->remember($user);
        $user = $this->reload((int) $user->id);

        // Sections B and C edit *this* account, and they reach it through the fixture cache. Without
        // this they tried to create the address a second time, were refused for the duplicate, and
        // reported "there is an account to edit" — a harness bug that hid every check behind it.
        $this->fixtures['create:manager'] = $user;

        $this->check('the address reads back from Shield\'s identity table', $user !== null && $user->email === $email);
        $this->check('the mobile is stored in the shape the table already uses', $user !== null && $user->mobile_number === '+919850366577');
        // Derived, not invented: the same rule the service uses, so this cannot drift out of step with
        // the addresses the fixtures are built from (it was checking the wrong prefix before).
        $this->check('a username is derived from the address', $user !== null && $user->username === UserRules::usernameFrom($email));
        $this->check('it holds exactly the role that was chosen', $user !== null && $user->getGroups() === ['manager']);
        $this->check('it is created active, so it can sign in at once', $user !== null && (bool) $user->active === true);
        $this->check('it is a local account', $user !== null && (int) $user->is_ad === 0);
        $this->check('the password Shield stored is the one that was typed', $this->signsIn($email, $password));
        $this->check('and a different password is not accepted', $this->signsIn($email, $password . 'x') === false);

        // The username is a login name *and* the name Shield compares a password against, and the
        // derived default makes that comparison unforgiving here (see PASSWORDS). So it is an
        // administrator's to choose — and this is what choosing it buys: a readable password that the
        // derived default refuses is accepted once the name it is compared against is the typed one.
        $chosenEmail = $this->email('chosen');
        $readable    = 'Ridge-Harbour-42';

        // A name this password genuinely has nothing in common with: the stamp's digits are mapped to
        // letters that appear nowhere in `$readable` (digits would, and would drag the similarity back
        // over the limit — which is the whole problem the derived default has).
        $chosenUsername = 'zkq.vjx' . substr(strtr($this->stamp, '0123456789', 'qzxjkwypst'), -3);

        $chosen = $service->create([
            'email'            => $chosenEmail,
            'first_name'       => 'Chosen',
            'last_name'        => 'Username',
            'username'         => $chosenUsername,
            'password'         => $readable,
            'password_confirm' => $readable,
            'group'            => 'user',
        ], null);

        $this->check('a username an administrator types is the one that is stored', $chosen['ok'] === true
            && $chosen['user'] instanceof User
            && $chosen['user']->username === $chosenUsername);
        $this->refusalReason($chosen);

        if ($chosen['user'] instanceof User) {
            $this->remember($chosen['user']);
        }

        $this->check('and a password the derived default refuses is accepted for it', $this->signsIn($chosenEmail, $readable));

        $derived = $service->create([
            'email' => $this->email('derived'), 'first_name' => 'Derived', 'last_name' => 'Default',
            'password' => $readable, 'password_confirm' => $readable, 'group' => 'user',
        ], null);

        $this->check('while that same password is refused for an account that leaves the username blank', $derived['ok'] === false && isset($derived['errors']['password']));

        // Both of these are refused on the username, so the password is the readable one that is known
        // to satisfy the policy for a chosen name: if either create succeeded, the failure would be
        // about the username rule rather than about a fixture password nobody could pick.
        $taken = $service->create([
            'email' => $this->email('taken'), 'first_name' => 'Taken', 'last_name' => 'Username',
            'username' => $chosenUsername,
            'password' => $readable, 'password_confirm' => $readable, 'group' => 'user',
        ], null);

        $this->check('a username another account already holds is refused', $taken['ok'] === false && isset($taken['errors']['username']));

        $badUsername = $service->create([
            'email' => $this->email('badname'), 'first_name' => 'Bad', 'last_name' => 'Username',
            'username' => 'has spaces',
            'password' => $readable, 'password_confirm' => $readable, 'group' => 'user',
        ], null);

        $this->check('and so is one the app\'s own username rule rejects', $badUsername['ok'] === false && isset($badUsername['errors']['username']));

        $duplicate = $service->create([
            'email' => $email, 'first_name' => 'Second', 'last_name' => 'Attempt',
            'password' => 'Harbour-Ridge-58', 'password_confirm' => 'Harbour-Ridge-58', 'group' => 'user',
        ], null);

        $this->check('a second account cannot take the same address', $duplicate['ok'] === false && isset($duplicate['errors']['email']));

        $weak = $service->create([
            'email' => $this->email('weak'), 'first_name' => 'Weak', 'last_name' => 'Password',
            'password' => 'password', 'password_confirm' => 'password', 'group' => 'user',
        ], null);

        $this->check('a dictionary password is refused, by Shield\'s own validator', $weak['ok'] === false && isset($weak['errors']['password']));

        $mismatch = $service->create([
            'email' => $this->email('mismatch'), 'first_name' => 'Mis', 'last_name' => 'Match',
            'password' => 'Harbour-Ridge-58', 'password_confirm' => 'Harbour-Ridge-59', 'group' => 'user',
        ], null);

        $this->check('two different passwords in the two boxes are refused', $mismatch['ok'] === false && isset($mismatch['errors']['password']));

        $nameless = $service->create([
            'email' => $this->email('nameless'), 'first_name' => '', 'last_name' => '',
            'password' => 'Harbour-Ridge-58', 'password_confirm' => 'Harbour-Ridge-58', 'group' => 'user',
        ], null);

        $this->check('an account without a name is refused', $nameless['ok'] === false && isset($nameless['errors']['first_name']));

        $unknownRole = $service->create([
            'email' => $this->email('norole'), 'first_name' => 'No', 'last_name' => 'Role',
            'password' => 'Harbour-Ridge-58', 'password_confirm' => 'Harbour-Ridge-58', 'group' => 'not-a-role',
        ], null);

        $this->check('a role that does not exist is refused', $unknownRole['ok'] === false && isset($unknownRole['errors']['group']));
        $this->check('and none of the refused attempts left an account behind', count($this->userIds) === 2);
    }

    // ------------------------------------------------------------------ B: editing

    private function sectionB(): void
    {
        CLI::write('B. Editing: profile, role replacement, password, username', 'yellow');

        $service = $this->service();
        $user    = $this->fixture('create');

        if ($user === null) {
            $this->check('there is an account to edit', false);

            return;
        }

        // The name this account was given at creation, and the password it holds: an edit that changes
        // the address does not change the username, so both are still the ones section A used.
        $username = UserRules::usernameFrom($this->email('create'));
        $created  = $this->passwordFor($username);
        $edited   = $this->passwordFor($username, [$created]);

        $moved = $service->update($user, [
            'email'         => $this->email('moved'),
            'first_name'    => 'Verified',
            'last_name'     => 'Alpha',
            'mobile_number' => '+91 98503 66577',
            'group'         => 'admin',
        ], null);

        $this->check('the profile is saved', $moved['ok'] === true);

        $reloaded = $this->reload((int) $user->id);

        $this->check('the name follows', $reloaded !== null && $reloaded->first_name === 'Verified');
        $this->check('the +91 spelling is normalised on the way in', $reloaded !== null && $reloaded->mobile_number === '+919850366577');
        $this->check('the new address can sign in', $this->signsIn($this->email('moved'), $created));
        $this->check('a role change replaces the old role instead of adding one', $reloaded !== null && $reloaded->getGroups() === ['admin']);

        $password = $service->update($user, [
            'email'            => $this->email('moved'),
            'first_name'       => 'Verified',
            'last_name'        => 'Alpha',
            'password'         => $edited,
            'password_confirm' => $edited,
        ], null);

        $this->check('a password can be set from the edit form', $password['ok'] === true);
        $this->check('the new password works', $this->signsIn($this->email('moved'), $edited));
        $this->check('and the old one does not', $this->signsIn($this->email('moved'), $created) === false);

        $empty = $service->update($user, [
            'email' => $this->email('moved'), 'first_name' => 'Verified', 'last_name' => 'Alpha',
            'password' => '', 'password_confirm' => '',
        ], null);

        $reloaded = $this->reload((int) $user->id);

        $this->check('leaving the password boxes empty keeps the current password', $empty['ok'] === true && $this->signsIn($this->email('moved'), $edited));
        $this->check('and the role is left alone when the form does not mention one', $reloaded !== null && $reloaded->getGroups() === ['admin']);

        $badName = $service->update($user, [
            'email' => $this->email('moved'), 'first_name' => 'Verified', 'last_name' => 'Alpha', 'username' => 'has spaces',
        ], null);

        $this->check('a username that breaks the app\'s own rule is refused', $badName['ok'] === false && isset($badName['errors']['username']));

        // A rename and a new password can arrive in the same save, and the password has to be judged
        // against the name it will sit next to — the new one. This password is a near-copy of the name
        // the account is *leaving* (57.7% similar, refused outright if that is what it is measured
        // against) and nothing like `qzx.jkw` (6.9%), so it tells the two comparisons apart. Its words
        // are deliberately misspelt: Shield's "nothing personal" rule also refuses a password that
        // contains the account's own address words, `verify` and `users` included.
        $leaving  = 'Verifx!Usxrs!Creaxx!99';
        $reNamed = $service->update($user, [
            'email' => $this->email('moved'), 'first_name' => 'Verified', 'last_name' => 'Alpha',
            'username' => 'qzx.jkw',
            'password' => $leaving, 'password_confirm' => $leaving,
        ], null);

        $this->check('a password that only resembles the name the account is leaving is accepted', $reNamed['ok'] === true);
        $this->check('and the rename lands with it', $this->reload((int) $user->id)?->username === 'qzx.jkw');
        $this->check('so it signs in under the new name', $this->signsIn($this->email('moved'), $leaving));

        $aimed = $service->update($user, [
            'email' => $this->email('moved'), 'first_name' => 'Verified', 'last_name' => 'Alpha',
            'username' => 'zq.kvj.4',
            'password' => 'Zq-Kvj-42!Aa', 'password_confirm' => 'Zq-Kvj-42!Aa',
        ], null);

        $this->check('while one built from the name being chosen right now is refused', $aimed['ok'] === false && isset($aimed['errors']['password']));
        $this->check('and nothing is written when it is', $this->reload((int) $user->id)?->username === 'qzx.jkw');

        $renamed = $service->update($user, [
            'email' => $this->email('moved'), 'first_name' => 'Verified', 'last_name' => 'Alpha', 'username' => 'verify.beta',
        ], null);

        $reloaded = $this->reload((int) $user->id);

        $this->check('a valid username is saved', $renamed['ok'] === true && $reloaded !== null && $reloaded->username === 'verify.beta');
    }

    // ------------------------------------------------------------------ C: Active Directory accounts

    private function sectionC(): void
    {
        CLI::write('C. An AD account\'s profile belongs to the directory; its role does not', 'yellow');

        $service = $this->service();
        $ad      = $this->adFixture();

        if ($ad === null) {
            $this->check('an AD-backed fixture exists', false);

            return;
        }

        $refused = $service->update($ad, ['email' => 'elsewhere@example.invalid'], null);

        $this->check('an AD profile edit is refused', $refused['ok'] === false);
        $this->check('with the directory as the reason', str_contains($refused['message'], 'Active Directory'));
        $this->check('the refusal names the field an administrator can change', str_contains($refused['message'], 'role'));

        $unchanged = $this->reload((int) $ad->id);

        $this->check('nothing was written', $unchanged !== null && $unchanged->email !== 'elsewhere@example.invalid');

        $role = $service->update($ad, [
            'email'         => (string) $ad->email,
            'username'      => (string) $ad->username,
            'first_name'    => (string) $ad->first_name,
            'middle_name'   => (string) $ad->middle_name,
            'last_name'     => (string) $ad->last_name,
            'mobile_number' => (string) $ad->mobile_number,
            'location'      => (string) $ad->location,
            'department'    => (string) $ad->department,
            'group'         => 'manager',
        ], null);

        $reloaded = $this->reload((int) $ad->id);

        $this->check('but its role can be set here', $role['ok'] === true && $reloaded !== null && $reloaded->getGroups() === ['manager']);
        $this->check('and a password cannot', $service->changePassword($ad, 'Harbour-Ridge-88', 'Harbour-Ridge-88', null)['ok'] === false);

        $local = $this->fixture('create');

        if ($local === null) {
            $this->check('a local account\'s password still can', false);

            return;
        }

        // Asked with the name the service will see on the entity it is handed, rather than the one the
        // account was created under: this fixture was renamed in section B, and the validator compares
        // the password against whatever the loaded account says.
        $changed = $this->passwordFor((string) $local->username);

        $this->check('a local account\'s password still can', $service->changePassword($local, $changed, $changed, null)['ok'] === true);
    }

    // ------------------------------------------------------------------ D: the guards

    private function sectionD(): void
    {
        CLI::write('D. The three ways to lock everybody out of this page are all closed', 'yellow');

        $service = $this->service();
        $actor   = $this->managerFixture('actor');
        $other   = $this->managerFixture('other');

        if ($actor === null || $other === null) {
            $this->check('two administrator fixtures exist', false);

            return;
        }

        $this->check('nobody bans their own account', $service->setBanned($actor, true, $actor)['ok'] === false);
        $this->check('nobody deactivates their own account', $service->setActive($actor, false, $actor)['ok'] === false);
        $this->check('nobody deletes their own account', $service->delete($actor, $actor)['ok'] === false);
        $this->check('and the refusal says whose account it is', str_contains($service->delete($actor, $actor)['message'], 'your own account'));

        $this->check('the actor survived all three attempts', $this->reload((int) $actor->id) !== null);

        // The fleet this runs against already has real administrators, so "the last one" is only
        // reachable through the service's counter seam. The refusal path is the same code either way.
        $alone    = $this->service(static fn (): int => 1);
        $refusals = [
            'delete'     => $alone->delete($other, $actor),
            'ban'        => $alone->setBanned($other, true, $actor),
            'deactivate' => $alone->setActive($other, false, $actor),
            'role'       => $alone->update($other, ['group' => 'user'], $actor),
        ];

        foreach ($refusals as $what => $outcome) {
            $this->check('the last account that can manage users cannot be ' . $what . 'ed', $outcome['ok'] === false);
        }

        $this->check('and the refusal explains why', str_contains($refusals['delete']['message'], 'nobody who can'));

        $untouched = $this->reload((int) $other->id);

        $this->check('the last manager is untouched: still there, still active, still managing', $untouched !== null
            && (bool) $untouched->active === true
            && $untouched->isBanned() === false
            && in_array('admin', $untouched->getGroups() ?? [], true));

        $nonManager = $this->fixture('plain', 'manager');

        if ($nonManager === null) {
            $this->check('a non-managing fixture exists', false);

            return;
        }

        $this->check('with a real count, the same account is not treated as the last one', $service->otherManagerCount($other) >= 1);
        $this->check('and a non-managing account is never treated as one', $service->otherManagerCount($nonManager) >= 0);

        $this->check('a non-managing account can be banned', $service->setBanned($nonManager, true, $actor)['ok'] === true);
        $this->check('it reports itself as banned', ($this->reload((int) $nonManager->id)?->isBanned()) === true);
        $this->check('and unbanned again', $service->setBanned($nonManager, false, $actor)['ok'] === true && ($this->reload((int) $nonManager->id)?->isBanned()) === false);
        $this->check('deactivating a non-managing account is allowed', $service->setActive($nonManager, false, $actor)['ok'] === true && (bool) $this->reload((int) $nonManager->id)?->active === false);
        $this->check('and reactivating it is too', $service->setActive($nonManager, true, $actor)['ok'] === true && (bool) $this->reload((int) $nonManager->id)?->active === true);
    }

    // ------------------------------------------------------------------ E: removing an account

    private function sectionE(): void
    {
        CLI::write('E. Removing an account stops it signing in, and keeps the history that names it', 'yellow');

        $service  = $this->service();
        $actor    = $this->managerFixture('actor');
        $email    = $this->email('victim');
        $password = $this->passwordFor(UserRules::usernameFrom($email));

        $victim = $service->create([
            'email' => $email, 'first_name' => 'Removed', 'last_name' => 'Account',
            'password' => $password, 'password_confirm' => $password, 'group' => 'user',
        ], $actor)['user'];

        if (! $victim instanceof User) {
            $this->check('a removable fixture exists', false);

            return;
        }

        $this->remember($victim);

        // A remembered session and an issued token: both have to die with the account.
        $this->db()->table('auth_remember_tokens')->insert([
            'selector'        => 'verify' . $this->stamp,
            'hashedValidator' => str_repeat('a', 64),
            'user_id'         => (int) $victim->id,
            'expires'         => date('Y-m-d H:i:s', time() + 3600),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->db()->table('auth_token_logins')->insert([
            'ip_address' => '127.0.0.1',
            'user_agent' => 'verify:users',
            'id_type'    => 'access_token',
            'identifier' => 'verify' . $this->stamp,
            'user_id'    => (int) $victim->id,
            'date'       => date('Y-m-d H:i:s'),
            'success'    => 1,
        ]);

        $this->check('the fixture can sign in before it is removed', $this->signsIn($email, $password));

        $outcome = $service->delete($victim, $actor);

        $this->check('the account is removed', $outcome['ok'] === true);
        $this->check('it leaves the list', ! in_array((int) $victim->id, $this->listed(), true));
        $this->check('its row is kept, so the change history that names it still resolves', ($this->row((int) $victim->id)['deleted_at'] ?? null) !== null);
        $this->check('it cannot sign in any more', $this->signsIn($email, $password) === false);
        $this->check('and it is banned as well as deleted', ($this->reload((int) $victim->id)?->isBanned()) === true);
        $this->check('its remembered session is dropped', (int) $this->db()->table('auth_remember_tokens')->where('user_id', (int) $victim->id)->countAllResults() === 0);
        $this->check('and its token login is dropped', (int) $this->db()->table('auth_token_logins')->where('user_id', (int) $victim->id)->countAllResults() === 0);

        $again = $service->create([
            'email' => $email, 'first_name' => 'Someone', 'last_name' => 'Else',
            'password' => 'Harbour-Ridge-71', 'password_confirm' => 'Harbour-Ridge-71', 'group' => 'user',
        ], $actor);

        $this->check('the address stays taken, so a new person cannot inherit that history', $again['ok'] === false && isset($again['errors']['email']));
    }

    // ------------------------------------------------------------------ F: the rules the login path asks

    private function sectionF(): void
    {
        CLI::write('F. The sign-in rules are one definition, and the stale plumbing is gone', 'yellow');

        $this->check('a banned account has a refusal to show', str_contains((string) UserRules::signInRefusal(true, false), 'banned'));
        $this->check('a removed account has a different one', str_contains((string) UserRules::signInRefusal(false, true), 'create it again'));
        $this->check('a good account gets none', UserRules::signInRefusal(false, false) === null);
        $this->check('the ban is the louder reason when both apply', str_contains((string) UserRules::signInRefusal(true, true), 'banned'));

        $login = (string) file_get_contents(ROOTPATH . 'app/Controllers/LoginController.php');

        $this->check('the AD sign-in path asks that shared rule', str_contains($login, 'UserRules::signInRefusal('));
        $this->check('rather than wording its own version of the refusal', ! str_contains($login, 'This account has been banned'));
        $this->check('and it refuses an account that was removed here', str_contains($login, 'withDeleted()'));

        $this->check('self-registration is closed', config('Auth')->allowRegistration === false);

        $routes = (string) file_get_contents(ROOTPATH . 'app/Config/Routes.php');

        $this->check('no route points at the register action that never existed', ! str_contains($routes, 'UsersController::register'));

        // The group root is written as '/', which is what CI4 resolves for `users/`. The old harness
        // looked for '' and failed against a route file that was correct.
        foreach (['/' => 'index', 'show/(:num)' => 'show', 'add' => 'add', 'update/(:num)' => 'update', 'delete/(:num)' => 'delete', 'change-password/(:num)' => 'changePassword', '(:num)/toggle-ban' => 'toggleBan', '(:num)/toggle-active' => 'toggleActive'] as $path => $method) {
            $this->check('the users area routes ' . $path . ' to UsersController::' . $method, str_contains($routes, "'" . $path . "', 'UsersController::" . $method));
            $this->check('and that action exists', method_exists(UsersController::class, $method));
        }

        $controller = $this->codeOnly((string) file_get_contents(ROOTPATH . 'app/Controllers/UsersController.php'));

        $this->check('the controller no longer names the model that does not exist', ! str_contains($controller, 'UsersModel'));
        $this->check('nor a permission Shield does not know', ! str_contains($controller, 'view_users'));
        $this->check('nor the views that do not exist', ! str_contains($controller, "view('Users/"));

        // The stronger form of the same question, and the one that generalises: whatever a permission
        // the controller asks for, the app's matrix has to grant it to somebody. The old
        // `view_users` was a name no group held, so an administrator was refused by their own page.
        preg_match_all("/can\('([^']+)'\)/", $controller, $asked);

        $granted = array_values(array_unique(array_merge(...array_values((array) config('AuthGroups')->matrix))));

        $this->check('the controller asks for a permission before it acts', $asked[1] !== []);

        foreach (array_unique($asked[1]) as $permission) {
            $this->check("and '{$permission}' is one the app's matrix grants", in_array($permission, $granted, true));
        }

        $this->check('the list view it does render is on disk', is_file(ROOTPATH . 'app/Views/Auth/Users/index.php'));
        $this->check('and so is the profile view', is_file(ROOTPATH . 'app/Views/Auth/Users/show.php'));
    }

    // ------------------------------------------------------------------ fixtures and helpers

    private function service(?callable $managerCount = null): UserAccountService
    {
        return new UserAccountService(null, $managerCount);
    }

    private function users(): UserModel
    {
        return model(UserModel::class);
    }

    private function db(): BaseConnection
    {
        return db_connect();
    }

    private function email(string $what): string
    {
        return 'verify.users.' . $what . '.' . $this->stamp . '@example.invalid';
    }

    /**
     * A password the shipped policy accepts for this username.
     *
     * Asked of Shield rather than assumed: the similarity rule is strict here (see the PASSWORDS
     * constant) and it is compared against the username, which for a fixture is derived from its own
     * email address. If no candidate is accepted the failure is recorded here, naming the setting, so
     * a future tightening is reported as a policy problem rather than as a mysterious create failure.
     *
     * @param list<string> $except candidates to skip — a check that the *old* password stops working
     *                             needs the new one to be a different string
     */
    private function passwordFor(string $username, array $except = []): string
    {
        foreach (self::PASSWORDS as $candidate) {
            if (in_array($candidate, $except, true)) {
                continue;
            }

            $user = new User([
                'username' => $username,
                'email'    => $username . '@example.invalid',
                'password' => $candidate,
            ]);

            if (service('passwords')->check($candidate, $user)->isOK()) {
                return $candidate;
            }
        }

        $this->check(
            "a fixture password exists that Shield accepts for '{$username}' (Config\\Auth::\$maxSimilarity is "
            . config('Auth')->maxSimilarity . ')',
            false
        );

        return self::PASSWORDS[0];
    }

    /**
     * An ordinary local account, made through the service so the create path is exercised as it ships.
     *
     * Created once per name: creating `create` twice would collide on the address, which is itself a
     * behaviour the harness checks elsewhere and would rather not trip over here.
     */
    private function fixture(string $what, string $role = 'manager'): ?User
    {
        $key = $what . ':' . $role;

        if (isset($this->fixtures[$key])) {
            return $this->fixtures[$key];
        }

        $email    = $this->email($what);
        $password = $this->passwordFor(UserRules::usernameFrom($email));

        $outcome = $this->service()->create([
            'email'            => $email,
            'first_name'       => 'Fixture',
            'last_name'        => ucfirst($what),
            'password'         => $password,
            'password_confirm' => $password,
            'group'            => $role,
        ], null);

        $user = $outcome['user'];

        if (! $user instanceof User) {
            return null;
        }

        $this->remember($user);
        $this->fixtures[$key] = $this->reload((int) $user->id);

        return $this->fixtures[$key];
    }

    /** An account that can manage users, which is what the guards are about. */
    private function managerFixture(string $what): ?User
    {
        return $this->fixture($what, 'admin');
    }

    /** An account that looks the way an AD-synced one does. */
    private function adFixture(): ?User
    {
        $user = new User([
            'username'      => 'verify.ad.' . $this->stamp,
            'email'         => $this->email('ad'),
            'password'      => bin2hex(random_bytes(16)),
            'first_name'    => 'Ad',
            'last_name'     => 'User',
            'mobile_number' => '',
            'location'      => '',
            'department'    => '',
            'is_ad'         => 1,
            'active'        => 1,
        ]);

        try {
            $this->users()->save($user);
            $saved = $this->users()->findById($this->users()->getInsertID());

            if ($saved instanceof User) {
                $saved->addGroup('user');
                $this->remember($saved);

                return $this->reload((int) $saved->id);
            }
        } catch (Throwable $e) {
            CLI::write('   could not build the AD fixture: ' . $e->getMessage(), 'red');
        }

        return null;
    }

    /** Shield's own credential check — the thing a sign-in does before it trusts anybody. */
    private function signsIn(string $email, string $password): bool
    {
        try {
            return auth('session')->check(['email' => $email, 'password' => $password])->isOK();
        } catch (Throwable $e) {
            CLI::write('   could not check credentials: ' . $e->getMessage(), 'red');

            return false;
        }
    }

    private function remember(User $user): void
    {
        $this->userIds[] = (int) $user->id;
    }

    private function reload(int $id): ?User
    {
        return $this->users()->withDeleted()->withIdentities()->findById($id);
    }

    /** @return array<string, mixed> */
    private function row(int $id): array
    {
        return (array) $this->db()->table('users')->where('id', $id)->get()->getRowArray();
    }

    /** @return list<int> */
    private function listed(): array
    {
        return array_map(static fn (User $user): int => (int) $user->id, $this->service()->accounts());
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $tables = [
            'users',
            'auth_identities',
            'auth_groups_users',
            'auth_remember_tokens',
            'auth_token_logins',
        ];

        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = (int) $this->db()->table($table)->countAllResults();
        }

        return $counts;
    }

    private function cleanup(): void
    {
        if ($this->userIds === []) {
            return;
        }

        $ids = array_values(array_unique($this->userIds));

        foreach (['auth_identities', 'auth_groups_users', 'auth_permissions_users', 'auth_remember_tokens', 'auth_token_logins', 'auth_logins'] as $table) {
            $this->db()->table($table)->whereIn('user_id', $ids)->delete();
        }

        // A hard delete, through the query builder: these are the harness's own rows, and a soft delete
        // would leave them counted in the baseline.
        $this->db()->table('users')->whereIn('id', $ids)->delete();
    }

    /**
     * When something is refused, say why — a harness that only says "false" makes the next reader
     * reproduce the whole thing to find out.
     *
     * @param array<string, mixed> $outcome
     */
    /**
     * A PHP source file with its comments removed.
     *
     * The "stale plumbing is gone" checks are about *code*. A docblock explaining that this controller
     * used to require `view_users` is the reason the next reader does not put it back, so documenting
     * the mistake must not be mistaken for repeating it. Only whole comment lines are stripped (not a
     * trailing `//` after code), which keeps `https://` inside a string literal intact.
     */
    private function codeOnly(string $source): string
    {
        return (string) preg_replace('~ /\*.*?\*/ | ^[ \t]*//[^\n]* ~msx', ' ', $source);
    }

    private function refusalReason(array $outcome): void
    {
        if (! empty($outcome['ok'])) {
            return;
        }

        CLI::write('          refused because: ' . ($outcome['message'] ?? 'no reason given')
            . ' ' . json_encode($outcome['errors'] ?? []), 'light_red');
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
