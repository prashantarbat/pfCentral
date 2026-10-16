<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Persists the outcome of each per-device config push attempt.
 *
 * Before this existed, a push that failed halfway through a fleet lived only in the
 * browser terminal and was lost the moment the tab closed.
 */
class ConfigPushLogModel extends Model
{
    protected $table            = 'config_push_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'batch_id',
        'pf_id',
        'device_name',
        'ip',
        'target_type',
        'payload_type',
        'status',
        'message',
        'log',
        'backup_id',
        'backup_file',
        'user_id',
        'duration_ms',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
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
