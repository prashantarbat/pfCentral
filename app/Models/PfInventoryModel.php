<?php

namespace App\Models;

use CodeIgniter\Model;

class PfInventoryModel extends Model
{

    protected $table            = 'pf_inventory';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['name', 'netgate_id', 'active', 'bypass', 'groups', 'man_ip', 'man_port', 'serial', 'api', 'location', 'tags', 'platform', 'notes', 'last_checked', 'username', 'password', 'devicekey'];

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
    protected $beforeInsert   = ['encryptCredentials', 'sanitizeAndDefaultFields'];
    protected $afterInsert    = [];
    protected $beforeUpdate   = ['encryptCredentials', 'sanitizeAndDefaultFields'];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = ['decryptCredentials'];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Sanitizes string fields (trims leading/trailing whitespace) and guarantees
     * that active status defaults to 1 so devices are never orphaned as inactive.
     */
    protected function sanitizeAndDefaultFields(array $data): array
    {
        if (empty($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        // Default active to 1 if not explicitly provided or empty
        if (!isset($data['data']['active']) || $data['data']['active'] === null || $data['data']['active'] === '') {
            $data['data']['active'] = 1;
        }

        // Clean up whitespace across key string attributes
        $stringFields = ['name', 'man_ip', 'man_port', 'serial', 'platform', 'groups', 'devicekey', 'netgate_id'];
        foreach ($stringFields as $field) {
            if (isset($data['data'][$field]) && is_string($data['data'][$field])) {
                $data['data'][$field] = trim($data['data'][$field]);
            }
        }

        return $data;
    }

    /**
     * Encrypt sensitive fleet credentials before saving to the database.
     */
    protected function encryptCredentials(array $data): array
    {
        if (empty($data['data']) || !is_array($data['data'])) {
            return $data;
        }

        $service = service('credentialEncryption');
        if (isset($data['data']['password']) && $data['data']['password'] !== null && $data['data']['password'] !== '') {
            $data['data']['password'] = $service->encrypt((string) $data['data']['password']);
        }
        if (isset($data['data']['api']) && $data['data']['api'] !== null && $data['data']['api'] !== '') {
            $data['data']['api'] = $service->encrypt((string) $data['data']['api']);
        }

        return $data;
    }

    /**
     * Decrypt sensitive fleet credentials after retrieving from the database.
     */
    protected function decryptCredentials(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        $service = service('credentialEncryption');
        if (!empty($data['singleton'])) {
            if (isset($data['data']['password'])) {
                $data['data']['password'] = $service->decrypt($data['data']['password']);
            }
            if (isset($data['data']['api'])) {
                $data['data']['api'] = $service->decrypt($data['data']['api']);
            }
        } elseif (is_array($data['data'])) {
            foreach ($data['data'] as &$row) {
                if (is_array($row)) {
                    if (isset($row['password'])) {
                        $row['password'] = $service->decrypt($row['password']);
                    }
                    if (isset($row['api'])) {
                        $row['api'] = $service->decrypt($row['api']);
                    }
                }
            }
            unset($row);
        }

        return $data;
    }
}
