<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One outage at one branch.
 *
 * An open row (`ended_at` NULL) means the branch is down now; the service closes it rather than opening a
 * second one, so `count()` over this table is a count of *outages* rather than of observations.
 */
class BranchAvailabilityEventModel extends Model
{
    /** A probe failed: the branch did not answer on any address we know for it. */
    public const KIND_UNREACHABLE = 'unreachable';

    /** No address to probe, and the device stopped checking in: inferred, and labelled as inferred. */
    public const KIND_SILENT = 'silent';

    protected $table            = 'branch_availability_events';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'pf_id',
        'device_name',
        'location_id',
        'kind',
        'started_at',
        'ended_at',
        'duration_seconds',
        'evidence',
        'job_id',
        'job_gap_minutes',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = '';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /** How an outage was found, in words that do not overstate the evidence. */
    public static function kindLabel(string $kind): string
    {
        return $kind === self::KIND_SILENT
            ? 'inferred from silence'
            : 'probe failed';
    }
}
