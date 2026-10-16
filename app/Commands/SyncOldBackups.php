<?php

namespace App\Commands;

use App\Models\PfBakupModel;
use App\Models\PfInventoryModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SyncOldBackups extends BaseCommand
{
    protected $group       = 'PfSense';
    protected $name        = 'backup:sync-old';
    protected $description = 'Syncs backup files from legacy XAMPP directory to pfcentral_backup and indexes them in pf_backups.';

    public function run(array $params)
    {
        CLI::write("=== Syncing Legacy Backups to PfCentral Backup Vault ===", 'yellow');

        $srcBase = 'D:/xampp-/htdocs/sevak-/writable/pfcentral_backup';
        $dstBase = WRITEPATH . 'pfcentral_backup';

        if (!is_dir($srcBase)) {
            CLI::error("Source directory not found: $srcBase");
            return EXIT_ERROR;
        }

        if (!is_dir($dstBase)) {
            mkdir($dstBase, 0755, true);
        }

        $inventoryModel = new PfInventoryModel();
        $backupModel    = new PfBakupModel();
        $devices        = $inventoryModel->findAll();

        CLI::write("Loaded " . count($devices) . " devices from pf_inventory.", 'white');

        $srcFolders = scandir($srcBase);
        $copiedCount  = 0;
        $indexedCount = 0;

        foreach ($devices as $dev) {
            $devId = (int)$dev['id'];
            $devName = $dev['name'] ?? '';
            $branchCode = '';
            if (preg_match('/^(\d{4})/', $devName, $m)) {
                $branchCode = $m[1];
            }

            $safeName = trim((string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $devName), '_');
            $targetDir = $dstBase . '/' . $safeName;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // Find matching source folders
            $matchingSrcDirs = [];
            foreach ($srcFolders as $sf) {
                if ($sf === '.' || $sf === '..') {
                    continue;
                }
                $srcPath = $srcBase . '/' . $sf;
                if (!is_dir($srcPath)) {
                    continue;
                }

                if ($branchCode !== '' && str_starts_with($sf, $branchCode)) {
                    $matchingSrcDirs[] = $srcPath;
                } elseif (strcasecmp($sf, $safeName) === 0) {
                    $matchingSrcDirs[] = $srcPath;
                }
            }

            foreach ($matchingSrcDirs as $sDir) {
                $xmlFiles = glob($sDir . '/*.xml') ?: [];
                foreach ($xmlFiles as $xml) {
                    $base = basename($xml);
                    $dstFile = $targetDir . '/' . $base;

                    if (!file_exists($dstFile)) {
                        @copy($xml, $dstFile);
                        $copiedCount++;
                    }

                    // Check if already in pf_backups
                    $exists = $backupModel->where('pf_id', $devId)->where('file_name', $base)->first();
                    if (!$exists) {
                        // Extract timestamp from filename or filemtime
                        $createdAt = null;
                        if (preg_match('/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', $base, $tm)) {
                            $createdAt = "{$tm[1]}-{$tm[2]}-{$tm[3]} {$tm[4]}:{$tm[5]}:{$tm[6]}";
                        } else {
                            $createdAt = date('Y-m-d H:i:s', filemtime($dstFile));
                        }

                        $backupModel->insert([
                            'pf_id'      => $devId,
                            'file_name'  => $base,
                            'file_path'  => $dstFile,
                            'quantity'   => $dev['quantity'] ?? 1,
                            'created_at' => $createdAt
                        ]);
                        $indexedCount++;
                    }
                }
            }
        }

        // Also check writable/tmp for any DR backup zip packages
        $tmpZips = glob(WRITEPATH . 'tmp/DR_*.zip') ?: [];
        foreach ($tmpZips as $zipFile) {
            $zipBase = basename($zipFile);
            $devId = null;
            $branchCode = '';

            // Match branch code from filename like DR_1001-MAIN_BRANCH...
            if (preg_match('/DR_(\d{4})/', $zipBase, $zm)) {
                $branchCode = $zm[1];
                foreach ($devices as $d) {
                    if (str_starts_with($d['name'] ?? '', $branchCode)) {
                        $devId = (int)$d['id'];
                        break;
                    }
                }
            }

            if ($devId) {
                $zip = new \ZipArchive();
                if ($zip->open($zipFile) === true) {
                    $xmlContent = $zip->getFromName('config.xml');
                    if ($xmlContent) {
                        $safeDevName = trim((string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $devices[array_search($devId, array_column($devices, 'id'))]['name'] ?? 'device'), '_');
                        $targetDir = $dstBase . '/' . $safeDevName;
                        if (!is_dir($targetDir)) {
                            mkdir($targetDir, 0755, true);
                        }

                        $tsString = '';
                        if (preg_match('/_(\d{8}_\d{6})\.zip$/', $zipBase, $tsm)) {
                            $tsString = $tsm[1];
                        } else {
                            $tsString = date('Ymd_His', filemtime($zipFile));
                        }

                        $extractedName = 'backup_' . $branchCode . '_' . $tsString . '.xml';
                        $targetXmlPath = $targetDir . '/' . $extractedName;
                        file_put_contents($targetXmlPath, $xmlContent);

                        $exists = $backupModel->where('pf_id', $devId)->where('file_name', $extractedName)->first();
                        if (!$exists) {
                            if (preg_match('/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', $tsString, $tm)) {
                                $createdAt = "{$tm[1]}-{$tm[2]}-{$tm[3]} {$tm[4]}:{$tm[5]}:{$tm[6]}";
                            } else {
                                $createdAt = date('Y-m-d H:i:s', filemtime($zipFile));
                            }

                            $backupModel->insert([
                                'pf_id'      => $devId,
                                'file_name'  => $extractedName,
                                'file_path'  => $targetXmlPath,
                                'quantity'   => 1,
                                'created_at' => $createdAt
                            ]);
                            $indexedCount++;
                            CLI::write("Extracted & indexed DR backup for branch $branchCode: $extractedName", 'cyan');
                        }
                    }
                    $zip->close();
                }
            }
        }

        CLI::write("Sync complete! Copied $copiedCount physical files, indexed $indexedCount records into pf_backups.", 'green');
        return EXIT_SUCCESS;
    }
}
