<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * What we last saw of a branch, and since when.
 *
 * One row per branch, keyed by `pf_id`. The report reads this for "what is happening now"; the outage
 * history reads the events table for "what happened". Keeping the two apart is what stops the report from
 * having to scan a year of history to answer a question about the present.
 */
class BranchAvailabilityStateModel extends Model
{
    public const STATE_UP      = 'up';
    public const STATE_DOWN    = 'down';
    public const STATE_UNKNOWN = 'unknown';

    public const SOURCE_PROBE  = 'probe';
    public const SOURCE_AGENT  = 'agent';
    public const SOURCE_MANUAL = 'manual';

    protected $table            = 'branch_availability_state';
    protected $primaryKey       = 'pf_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'pf_id',
        'device_name',
        'location_id',
        'state',
        'since',
        'monitored_since',
        'last_probe_at',
        'last_probe_ok',
        'last_probe_ms',
        'last_probe_target',
        'failing_since',
        'consecutive_failures',
        'consecutive_successes',
        'last_checkin_at',
        'source',
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

    /** A word for the state, for a page or a log line. */
    public static function stateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_UP   => 'up',
            self::STATE_DOWN => 'down',
            default          => 'not observed',
        };
    }
}
