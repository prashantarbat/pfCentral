<?php

namespace App\Models;

use CodeIgniter\Model;

class PfStatusModel extends Model
{

    protected $table            = 'pf_status';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'pf_id',
        'ip',
        'port',
        'ssh_port',
        'mpls_ip',
        'ill_ip',
        'wan_ip',
        'status',
        'lightsquid_port',
        'darkstat_port',
        'bandwidthd',
        'restapi',
        'ntop_port',
        'os_name',
        'os_version',
        'hostname',
        'build_identifier',
        'uptime_duration',
        'users',
        'load_averages',
        'ping_latency',
        'cpu_usage',
        'total_memory',
        'available_memory',
        'disk_usage',
        'active_tunnel',
        'banner',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = false;

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
