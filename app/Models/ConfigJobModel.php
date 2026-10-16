<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One deployment request: the rendered payload plus the target spec that produced it.
 */
class ConfigJobModel extends Model
{
    public const STATUS_DRAFT     = 'draft';
    /** Queued for a second approver: the runner will never pick this up. */
    public const STATUS_AWAITING_APPROVAL = 'awaiting_approval';
    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELED  = 'canceled';

    /** Statuses the runner may act on. */
    public const RUNNABLE_STATUSES = [self::STATUS_PENDING, self::STATUS_RUNNING];

    /** Statuses that mean the job is over. */
    public const TERMINAL_STATUSES = [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELED];

    /** Installs a complete <pfsense> document over the device's configuration. */
    public const MODE_REPLACE = 'replace';
    /** Merges one config section into the device's configuration, additively. */
    public const MODE_MERGE = 'merge';

    protected $table            = 'config_jobs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'batch_id',
        'template_id',
        'rendered_payload',
        'payload_type',
        'payload_mode',
        // How long this change is watched for, and the minute offsets that choice resolved to at
        // creation. The schedule is frozen here because `job_targets.watch_checks` indexes into it.
        'watch_profile',
        'watch_schedule',
        'target_spec',
        'status',
        'requires_approval',
        'requested_by',
        'approved_by',
        'approved_at',
        'canary_pf_id',
        // Set when this job exists to re-apply an earlier job's change to the devices that drifted.
        'reconcile_of_job_id',
        // Set when this job exists to *undo* an earlier job on one device: its payload is that
        // device's pre-push snapshot, restored because the change was found reverted. Same payload
        // type, same transports, same verification — the opposite direction.
        'rollback_of_job_id',
        'last_error',
        'created_at',
        'updated_at',
        'started_at',
        'finished_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    /** target_spec is a MySQL JSON column; cast it to/from an array. */
    protected array $casts = [
        'target_spec'    => 'json-array',
        'watch_schedule' => 'json-array',
    ];
    protected array $castHandlers = [];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'batch_id'     => 'required|max_length[36]',
        'payload_type' => 'required|in_list[xml,script]',
        'payload_mode' => 'required|in_list[replace,merge]',
        'status'       => 'required|in_list[draft,awaiting_approval,pending,running,completed,failed,canceled]',
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
