<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * One searchable fact extracted from a stored device configuration.
 *
 * Written only by ConfigIndexer (which rebuilds a device's rows wholesale) and read only by
 * ConfigSearchService, so there is exactly one definition of what a fact looks like.
 */
class ConfigIndexModel extends Model
{
    protected $table            = 'config_index';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'pf_id',
        'device_name',
        'section',
        'xml_path',
        'label',
        'value_text',
        'value_norm',
        'value_hash',
        'is_secret',
        'captured_at',
        'backup_id',
        'indexed_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'indexed_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
}
