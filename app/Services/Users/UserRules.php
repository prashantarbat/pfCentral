<?php

declare(strict_types=1);

namespace App\Services\Users;

/**
 * The rules of account management, with nothing in them that needs a database.
 *
 * Everything here is a decision or a normalisation, so it can be stated once, tested on its own, and
 * worded in the operator's language rather than in a form-error code. Two of these rules exist
 * because the users page used to be able to do damage that nothing stopped:
 *
 *   - an administrator could deactivate, ban or delete **their own** account, or the last account
 *     that can manage users, and lock everybody out of the page that fixes it;
 *   - and the mobile field carried a browser pattern that rejected the numbers the table already
 *     held (`+919850366577`, 13 characters against `maxlength="10"`), so the edit form for those
 *     users could not be submitted at all.
 */
final class UserRules
{
    /** The `users.username` column, and the length the app's own username rule allows. */
    private const USERNAME_LIMIT = 30;

    /** The groups that carry a permission, read from the app's own permission matrix. */
    public static function groupsWith(string $permission, ?array $matrix = null): array
    {
        $matrix ??= config('AuthGroups')->matrix;
        $groups   = [];

        foreach ($matrix as $group => $permissions) {
            if (in_array($permission, (array) $permissions, true)) {
                $groups[] = (string) $group;
            }
        }

        return $groups;
    }

    /**
     * The number the table stores, or null when what was typed cannot be one.
     *
     * A blank answer is allowed — AD users arrive without a mobile and several rows are empty — but
     * anything that types a value has to be a real Indian mobile, and `9850366577`, `09850366577`,
     * `919850366577` and `+919850366577` are all the same number, stored the way the table already
     * stores it.
     */
    public static function mobile(string $input): ?string
    {
        if (trim($input) === '') {
            return '';
        }

        $digits = (string) preg_replace('/[^0-9+]/', '', trim($input));

        if ($digits === '') {
            return null;
        }

        $cleanDigits = ltrim($digits, '+');
        if (strlen($cleanDigits) >= 7 && strlen($cleanDigits) <= 15) {
            return '+' . $cleanDigits;
        }

        return null;
    }

    /** The address, or null when it is not one. */
    public static function email(string $input): ?string
    {
        $email = trim($input);

        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }

    /**
     * A username for a locally created account.
     *
     * Shield can authenticate on a username, and the AD accounts already use the column for their
     * sAMAccountName, so a local account gets the part of its address before the @ (trimmed to the
     * column, which is 30 characters).
     */
    public static function usernameFrom(string $email): string
    {
        $stem = strstr($email, '@', true);
        $stem = $stem === false ? $email : $stem;
        // The app's own username rule allows letters, digits and dots (Config\Auth::usernameValidationRules),
        // so anything else becomes a dot rather than producing a name Shield would refuse to save.
        $stem = (string) preg_replace('/[^A-Za-z0-9.]+/', '.', $stem);
        $stem = strtolower(trim($stem, '.'));

        return substr($stem === '' ? 'user' : $stem, 0, self::USERNAME_LIMIT);
    }

    /** A username the app's own rules accept, or null when it is not one. */
    public static function username(string $input): ?string
    {
        $username = trim($input);

        if (strlen($username) < 3 || strlen($username) > 30) {
            return null;
        }

        return preg_match('/\A[a-zA-Z0-9.]+\z/', $username) === 1 ? $username : null;
    }

    /**
     * Why this account may not be touched by this actor, if it may not be.
     *
     * The self rule is not paternalism: deactivating, banning or deleting your own account removes
     * the only session that can undo it.
     */
    public static function refusalToChangeSelf(?int $actorId, int $targetId, string $action): ?string
    {
        if ($actorId === null || $actorId !== $targetId) {
            return null;
        }

        return 'You cannot ' . $action . ' your own account.';
    }

    /**
     * Why the last user administrator may not be removed, if they are.
     *
     * @param int $remaining the users who can still manage accounts, counting the target out
     */
    public static function refusalToRemoveLastManager(bool $targetManagesUsers, int $remaining, string $action): ?string
    {
        if (! $targetManagesUsers || $remaining > 0) {
            return null;
        }

        return 'That would leave nobody who can ' . $action . ': give somebody else the role first.';
    }

    /**
     * Why a sign-in must be refused, if it must be.
     *
     * Both of these are enforced for Shield's own sign-in already (the ban inside `attempt()`, the
     * removal by soft-delete hiding the account). The Active Directory path in LoginController signs the
     * user in directly and sees neither, so it asks this — the same wording, in one place, for the one
     * path that has to be reminded.
     */
    public static function signInRefusal(bool $banned, bool $removed): ?string
    {
        if ($banned) {
            return 'This account has been banned. Ask an administrator to restore it.';
        }

        if ($removed) {
            return 'This account was removed. An administrator has to create it again.';
        }

        return null;
    }

    /** Local passwords do not exist for an account that authenticates against Active Directory. */
    public static function refusalToSetPasswordForAdUser(bool $isAd): ?string
    {
        return $isAd
            ? 'This account signs in through Active Directory, so its password is changed there.'
            : null;
    }

    /**
     * What is wrong with the two password boxes, when anything is.
     *
     * Length and composition are Shield's business (`service('passwords')`), and are checked against
     * the account; this is only the part a validator cannot see.
     *
     * @return list<string>
     */
    public static function passwordProblems(string $password, string $confirmation, bool $required): array
    {
        $problems = [];

        if ($password === '' && $confirmation === '') {
            return $required ? ['A password is required for a new account.'] : [];
        }

        if ($password !== $confirmation) {
            $problems[] = 'The two passwords do not match.';
        }

        return $problems;
    }

    /**
     * A username that is free, given the ones already taken.
     *
     * The name is kept as it was asked for, and only shortened when a numeric suffix has to fit inside
     * the column: an earlier version always cut it to 27 characters, so a derived name that was not
     * colliding with anything still came back three characters shorter than it needed to be.
     *
     * @param list<string> $taken
     */
    public static function freeUsername(string $preferred, array $taken): string
    {
        $taken  = array_map(static fn ($name): string => strtolower((string) $name), $taken);
        $base   = substr($preferred, 0, self::USERNAME_LIMIT);
        $name   = $base;
        $suffix = 1;

        while (in_array(strtolower($name), $taken, true)) {
            $suffix++;
            $name = substr($base, 0, self::USERNAME_LIMIT - strlen((string) $suffix)) . $suffix;
        }

        return $name;
    }
}
