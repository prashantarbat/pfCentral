<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Per-device credentials for the device-initiated (agent) transport.
 *
 * Not used by the Phase 1 SSH transport yet; the table exists so device enrolment can
 * start being captured now and Phase 2 only has to add the pickup endpoint.
 */
class DeviceAgentModel extends Model
{
    protected $table            = 'device_agents';
    protected $primaryKey       = 'pf_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'pf_id',
        'device_key',
        'auth_token',
        'last_seen_at',
        'agent_version',
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

    protected $validationRules      = [];
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
