<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * What the search index actually covers, device by device.
 *
 * Kept as its own row per device rather than derived from `pf_backups` because the interesting cases are
 * the failures: a snapshot whose file was pruned off disk, one that no longer parses, one too large to
 * be worth indexing. A search page that silently omits those devices is worse than one that has never
 * been built — it answers "no device matches" with a confidence the data does not support.
 */
class ConfigIndexStateModel extends Model
{
    public const STATUS_INDEXED     = 'indexed';
    public const STATUS_NO_SNAPSHOT = 'no_snapshot';
    public const STATUS_UNREADABLE  = 'unreadable';
    public const STATUS_INVALID     = 'invalid';
    public const STATUS_TOO_LARGE   = 'too_large';
    public const STATUS_NOT_INDEXED = 'not_indexed';

    protected $table            = 'config_index_state';
    protected $primaryKey       = 'pf_id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'pf_id',
        'device_name',
        'status',
        'reason',
        'backup_id',
        'file_name',
        'file_path',
        'captured_at',
        'file_size',
        'nodes',
        'indexed_at',
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

    /** The statuses that mean "a search over this device can actually see its configuration". */
    public static function searchableStatuses(): array
    {
        return [self::STATUS_INDEXED];
    }

    /** A short sentence for the coverage strip, in an operator's words. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_INDEXED     => 'searchable',
            self::STATUS_NO_SNAPSHOT => 'no stored snapshot',
            self::STATUS_UNREADABLE  => 'snapshot unreadable',
            self::STATUS_INVALID     => 'snapshot is not valid XML',
            self::STATUS_TOO_LARGE   => 'snapshot too large',
            default                  => 'not indexed yet',
        };
    }
}
