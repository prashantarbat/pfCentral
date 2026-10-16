<?php

namespace App\Commands;

use App\Models\PfBakupModel;
use App\Models\PfInventoryModel;
use App\Support\CliOptions;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Renames backup files on disk and in the database to include the branch code.
 *
 * Pattern: backup_YYYYMMDD_HHMMSS.xml -> backup_{branchCode}_YYYYMMDD_HHMMSS.xml
 * Example: backup_20260925_061733.xml -> backup_1001_20260925_061733.xml
 *
 * Usage:
 *   php spark backup:rename-branch --dry-run
 *   php spark backup:rename-branch
 */
class RenameBackupsWithBranchCode extends BaseCommand
{
    protected $group       = 'PfSense';
    protected $name        = 'backup:rename-branch';
    protected $description = 'Renames backup files on disk and in database to include branch code (e.g. backup_1001_YYYYMMDD_His.xml).';
    protected $usage       = 'backup:rename-branch [--dry-run] [--device=<id>]';
    protected $options     = [
        '--dry-run' => 'Simulate the renames without modifying files or database records.',
        '--device'  => 'Filter to a single device by ID.',
    ];

    public function run(array $params)
    {
        $dryRun   = CliOptions::flag($params, 'dry-run');
        $deviceId = CliOptions::int($params, 'device');

        CLI::write("=== Backup Rename with Branch Code ===", 'yellow');
        if ($dryRun) {
            CLI::write("[DRY RUN MODE - No files or database rows will be altered]\n", 'cyan');
        }

        $inventoryModel = new PfInventoryModel();
        $backupModel    = new PfBakupModel();
        $db             = \Config\Database::connect();

        // 1. Fetch devices and extract branch codes
        $query = $inventoryModel;
        if ($deviceId !== null && $deviceId > 0) {
            $query = $query->where('id', $deviceId);
        }
        $deviceList = $query->findAll();
        $devices = [];
        foreach ($deviceList as $d) {
            $branchCode = '';
            if (preg_match('/^(\d+)/', $d['name'] ?? '', $m)) {
                $branchCode = $m[1];
            }
            $devices[$d['id']] = [
                'id'          => $d['id'],
                'name'        => $d['name'],
                'branch_code' => $branchCode,
            ];
        }

        CLI::write("Loaded " . count($devices) . " device(s).", 'white');

        // 2. Scan and rename physical files on disk
        $scanDirs = [
            WRITEPATH . 'pfcentral_backup',
            WRITEPATH . 'noc_backup',
        ];

        $diskRenamedCount = 0;
        $diskSkippedCount = 0;
        $diskFailedCount  = 0;

        foreach ($scanDirs as $baseDir) {
            if (!is_dir($baseDir)) {
                continue;
            }
            $folders = scandir($baseDir);
            foreach ($folders as $folder) {
                if ($folder === '.' || $folder === '..') {
                    continue;
                }
                $folderPath = $baseDir . '/' . $folder;
                if (!is_dir($folderPath)) {
                    continue;
                }

                // Determine branch code: first try matching from devices array, then folder prefix
                $branchCode = '';
                $matchedDevice = null;
                foreach ($devices as $dev) {
                    $safe = trim((string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $dev['name']), '_');
                    if (strcasecmp($folder, $safe) === 0 && !empty($dev['branch_code'])) {
                        $branchCode = $dev['branch_code'];
                        $matchedDevice = $dev;
                        break;
                    }
                }
                if ($deviceId !== null && $deviceId > 0) {
                    if (!$matchedDevice || (int)$matchedDevice['id'] !== $deviceId) {
                        continue;
                    }
                } elseif ($branchCode === '' && preg_match('/^(\d+)/', $folder, $fm)) {
                    $branchCode = $fm[1];
                }

                if ($branchCode === '') {
                    continue;
                }

                $xmlFiles = glob($folderPath . '/*.xml') ?: [];
                foreach ($xmlFiles as $xmlFile) {
                    $baseFile = basename($xmlFile);

                    // Skip if already has this branch code: backup_{branchCode}_...
                    if (preg_match('/^backup_' . preg_quote($branchCode, '/') . '_/', $baseFile)) {
                        $diskSkippedCount++;
                        continue;
                    }

                    // Check if matches backup_YYYYMMDD_HHMMSS pattern
                    if (preg_match('/^backup_(\d{8}_\d{6}.*\.xml)$/', $baseFile, $bm)) {
                        $newBase = 'backup_' . $branchCode . '_' . $bm[1];
                        $newPath = $folderPath . '/' . $newBase;

                        if ($dryRun) {
                            $diskRenamedCount++;
                        } else {
                            if (@rename($xmlFile, $newPath)) {
                                $diskRenamedCount++;
                            } else {
                                CLI::error("Failed to rename disk file: $xmlFile -> $newPath");
                                $diskFailedCount++;
                            }
                        }
                    }
                }
            }
        }

        CLI::write("Physical disk files: $diskRenamedCount renamed, $diskSkippedCount already formatted, $diskFailedCount failed.", 'green');

        // 3. Update database records in pf_backups
        $builder = $db->table('pf_backups');
        if ($deviceId !== null && $deviceId > 0) {
            $builder->where('pf_id', $deviceId);
        }
        $backupRows = $builder->get()->getResultArray();

        $dbRenamedCount = 0;
        $dbSkippedCount = 0;

        foreach ($backupRows as $row) {
            $dev = $devices[$row['pf_id']] ?? null;
            if (!$dev || empty($dev['branch_code'])) {
                continue;
            }

            $branchCode = $dev['branch_code'];
            $baseFile   = $row['file_name'];

            if (preg_match('/^backup_' . preg_quote($branchCode, '/') . '_/', $baseFile)) {
                $dbSkippedCount++;
                continue;
            }

            if (preg_match('/^backup_(\d{8}_\d{6}.*\.xml)$/', $baseFile, $bm)) {
                $newBase = 'backup_' . $branchCode . '_' . $bm[1];
                $oldPath = $row['file_path'];
                $newPath = str_replace($baseFile, $newBase, $oldPath);

                if ($dryRun) {
                    $dbRenamedCount++;
                } else {
                    $db->table('pf_backups')
                        ->where('id', $row['id'])
                        ->update([
                            'file_name' => $newBase,
                            'file_path' => $newPath,
                        ]);
                    $dbRenamedCount++;
                }
            }
        }

        CLI::write("Database records (pf_backups): $dbRenamedCount updated, $dbSkippedCount already formatted.", 'green');
        CLI::write("=== Backup Rename Complete ===", 'yellow');

        return EXIT_SUCCESS;
    }
}
