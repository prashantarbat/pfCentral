<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One row per drift that somebody has to know about.
 *
 * `status`, `notified_at`, `notify_attempts` and `resolved_*` are deliberately separate: an alert can
 * be open but not yet emailed (mail is down), emailed but still open (the device is still wrong), or
 * resolved by a human who has decided the difference is intentional. Collapsing those into one state
 * would lose the question the table exists to answer — "is anything drifting that nobody has been told
 * about?"
 */
class ConfigAlertModel extends Model
{
    public const STATUS_OPEN     = 'open';
    public const STATUS_RESOLVED = 'resolved';

    /** The change was found undone while the verification window was still watching it. */
    public const KIND_REVERT = 'revert';

    /** The device stopped matching a change whose window had already closed. */
    public const KIND_DRIFT = 'drift';

    /**
     * One change, undone on several devices inside its window: a fleet pattern rather than N branch
     * problems. It speaks for its member reverts (`grouped_into`) and stands for all of them at once.
     */
    public const KIND_REVERT_PATTERN = 'revert_pattern';

    /**
     * Whether this row is the loud kind — a change that came undone while it was being watched.
     *
     * A fleet pattern is a revert for everything that matters downstream (it is loud, it is not a slow
     * drift, and nobody needs to be told twice), so it answers yes here. `isPattern()` is what the
     * callers that must tell the two apart ask.
     */
    public static function isRevert(?string $kind): bool
    {
        $kind = self::normaliseKind($kind);

        return $kind === self::KIND_REVERT || $kind === self::KIND_REVERT_PATTERN;
    }

    /** Whether this row stands for several devices that undid the same change. */
    public static function isPattern(?string $kind): bool
    {
        return trim((string) $kind) === self::KIND_REVERT_PATTERN;
    }

    /**
     * The vocabulary, and the one direction an unknown value may be read in.
     *
     * Anything unrecognised is an ordinary drift, never a revert: shouting "reverted" on evidence we do
     * not have would be worse than saying nothing, because the loud wording is only worth reading while it
     * stays rare. The known loud kinds are preserved exactly as stored.
     */
    public static function normaliseKind(?string $kind): string
    {
        $kind = trim((string) $kind);

        return in_array($kind, [self::KIND_REVERT, self::KIND_REVERT_PATTERN], true) ? $kind : self::KIND_DRIFT;
    }

    protected $table            = 'config_alerts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'job_id',
        'target_id',
        'pf_id',
        'device_name',
        'fingerprint',
        'status',
        'summary',
        'detail',
        'kind',
        // The rollback job this alert's revert was answered with, when one was queued. It is what
        // lets the alert (and its email) say "we are putting this device back" instead of the
        // "nothing was changed or rolled back by us" that was true before auto-rollback existed.
        'rollback_job_id',
        // The pattern alert a member revert belongs to, and (on the pattern row itself) how many
        // devices are in it right now.
        'grouped_into',
        'device_count',
        'raised_at',
        'notify_attempts',
        'notified_at',
        'notified_to',
        'notify_error',
        // When this row's email becomes due. Null means "as soon as possible"; a revert is deferred for one
        // read cycle so a fleet-wide revert can be recognised as one change before anything is sent.
        'notify_after',
        'resolved_at',
        'resolved_by',
        'resolution',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'fingerprint' => 'required|exact_length[40]',
        'status'      => 'permit_empty|in_list[open,resolved]',
        'kind'        => 'permit_empty|in_list[drift,revert,revert_pattern]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];
}
