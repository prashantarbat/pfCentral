<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One row per device per job: this is the state machine that makes a fleet-wide push
 * resumable when the runner is interrupted.
 *
 * A target moves queued -> (running | delivered) -> applied | failed, and may also end up
 * skipped (never attempted: a halted canary rollout, or a canceled job).
 *
 * `delivered` is the asynchronous case introduced by the Phase 2 agent transport: the job is armed
 * and waiting for the device to collect it. It is deliberately OPEN, not finished — a device that
 * collects a job and then vanishes must not leave the job looking complete.
 *
 * `verify_status` is a second, independent axis: after a target is applied, its configuration is read
 * back and checked against what the job intended. A drifted target stays `applied`, because the push
 * did succeed — verification answers "is it still like that?", not "did it work?", and conflating the
 * two would rewrite the outcome of a job that did what it was asked.
 *
 * The `watch_*` columns are that axis for the first hour after a change: `watch_next_at` is when the
 * next re-check is due, `watch_until` when the window closes, and `watch_checks` how many have run.
 * A null `watch_next_at` means nothing is outstanding — either the device was never watched, or the
 * window has finished, or it was cut short by finding drift, which is the whole point of watching.
 */
class JobTargetModel extends Model
{
    /** The device has not been checked since it was applied. */
    public const VERIFY_PENDING = 'pending';
    /** Re-applying the job would change nothing: the intended state is present. */
    public const VERIFY_MATCHED = 'matched';
    /** The device does not look like the job intended — a later change undid it, or something failed. */
    public const VERIFY_DRIFTED = 'drifted';
    /** The configuration could not be read (unreachable, no transport that can read, or no payload). */
    public const VERIFY_UNAVAILABLE = 'unavailable';
    /** A script payload has no intended configuration to compare against. */
    public const VERIFY_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_QUEUED      = 'queued';
    public const STATUS_RUNNING     = 'running';
    public const STATUS_DELIVERED   = 'delivered';
    public const STATUS_APPLIED     = 'applied';
    public const STATUS_FAILED      = 'failed';
    public const STATUS_SKIPPED     = 'skipped';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    /**
     * Targets that still need work. 'running' means an attempt died mid-flight; 'delivered' means
     * armed for a device that has not reported back yet. Both are reclaimable once stale.
     */
    public const OPEN_STATUSES = [self::STATUS_QUEUED, self::STATUS_RUNNING, self::STATUS_DELIVERED];

    /** Targets nobody is working on any more. */
    public const CLOSED_STATUSES = [
        self::STATUS_APPLIED,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
        self::STATUS_ROLLED_BACK,
    ];

    /** Targets a cancel/halt may abandon: armed but not yet taken, or never started. */
    public const UNTOUCHED_STATUSES = [self::STATUS_QUEUED, self::STATUS_DELIVERED];

    protected $table            = 'job_targets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'job_id',
        'pf_id',
        'status',
        'is_canary',
        'attempts',
        'delivered_at',
        'applied_at',
        'result_log',
        'config_before',
        'config_after',
        'backup_id',
        'backup_file',
        // The rollback job this target's device was handed to, once a revert was found inside the
        // verification window. Non-null means "this device has already been rolled back for this
        // drift", which is what stops a second rollback being queued and what keeps a reconciler
        // from re-applying the change the rollback deliberately removed.
        'rollback_job_id',
        'error',
        'verify_status',
        'verified_at',
        'verify_detail',
        'verify_attempts',
        'watch_started_at',
        'watch_until',
        'watch_next_at',
        'watch_checks',
        'watch_hash',
        'watch_via',
        'duration_ms',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'job_id' => 'required|is_natural_no_zero',
        'pf_id'  => 'required|is_natural_no_zero',
        'status'        => 'required|in_list[queued,running,delivered,applied,failed,skipped,rolled_back]',
        'verify_status' => 'permit_empty|in_list[pending,matched,drifted,unavailable,not_applicable]',
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
