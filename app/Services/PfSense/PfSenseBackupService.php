<?php

namespace App\Services\PfSense;

use App\Models\PfInventoryModel;
use App\Models\PfBakupModel;
use App\Models\PfStatusModel;
use App\Services\BackupRetentionService;
use CodeIgniter\CLI\CLI;
use ZipArchive;

/**
 * Service managing pfSense XML backups: ingestion, filesystem resolution,
 * pruning/retention, diff generation, XML inspection, and disaster recovery packaging.
 */
class PfSenseBackupService
{
    protected PfInventoryModel $inventoryModel;
    protected PfBakupModel $backupModel;
    protected PfStatusModel $statusModel;
    protected BackupRetentionService $retentionService;

    public function __construct(
        ?PfInventoryModel $inventoryModel = null,
        ?PfBakupModel $backupModel = null,
        ?PfStatusModel $statusModel = null,
        ?BackupRetentionService $retentionService = null
    ) {
        $this->inventoryModel   = $inventoryModel ?? new PfInventoryModel();
        $this->backupModel      = $backupModel ?? new PfBakupModel();
        $this->statusModel      = $statusModel ?? new PfStatusModel();
        $this->retentionService = $retentionService ?? new BackupRetentionService();
    }

    /**
     * Ingest and store an incoming base64-encoded XML backup from a firewall.
     *
     * @param string $encodedXmlData
     * @param string $deviceKey
     * @return array{statusCode: int, body: array}
     */
    public function storeBackup(string $encodedXmlData, string $deviceKey): array
    {
        if (empty($encodedXmlData)) {
            return [
                'statusCode' => 400,
                'body'       => ['error' => 'No XML data provided.'],
            ];
        }

        if (empty($deviceKey)) {
            return [
                'statusCode' => 400,
                'body'       => ['error' => 'No DEVICEKEY provided.'],
            ];
        }

        $xmlData = base64_decode($encodedXmlData);
        if ($xmlData === false) {
            return [
                'statusCode' => 400,
                'body'       => ['error' => 'Invalid Base64-encoded XML data.'],
            ];
        }

        $inventoryRecord = $this->inventoryModel->where('devicekey', $deviceKey)->first();
        if (!$inventoryRecord) {
            return [
                'statusCode' => 404,
                'body'       => ['error' => 'Device not found in inventory.'],
            ];
        }

        $deviceName = $inventoryRecord['name'] ?? 'unknown_device';
        $deviceName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $deviceName);
        $deviceName = trim($deviceName, '_');

        $backupDir = WRITEPATH . 'noc_backup/' . $deviceName;
        $parentDir = WRITEPATH . 'noc_backup';

        if (!is_dir($parentDir)) {
            @mkdir($parentDir, 0775, true);
            @chmod($parentDir, 0775);
        }

        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
            @chmod($backupDir, 0775);
        }

        if (!is_writable($backupDir)) {
            @chmod($backupDir, 0775);
        }

        // Fallback to pfcentral_backup if noc_backup is not writable
        if (!is_writable($backupDir)) {
            $fallbackParent = WRITEPATH . 'pfcentral_backup';
            $fallbackDir    = $fallbackParent . '/' . $deviceName;
            if (!is_dir($fallbackParent)) {
                @mkdir($fallbackParent, 0775, true);
                @chmod($fallbackParent, 0775);
            }
            if (!is_dir($fallbackDir)) {
                @mkdir($fallbackDir, 0775, true);
                @chmod($fallbackDir, 0775);
            }
            if (is_writable($fallbackDir)) {
                $backupDir = $fallbackDir;
            }
        }

        $branchCode = '';
        if (!empty($inventoryRecord['name']) && preg_match('/^(\d+)/', $inventoryRecord['name'], $m)) {
            $branchCode = $m[1];
        }

        $backupFileName = ($branchCode !== '')
            ? 'backup_' . $branchCode . '_' . date('Ymd_His') . '.xml'
            : 'backup_' . date('Ymd_His') . '.xml';
        $backupFilePath = $backupDir . '/' . $backupFileName;

        $writeSuccess = false;
        try {
            $writeSuccess = (@file_put_contents($backupFilePath, $xmlData) !== false);
        } catch (\Throwable $e) {
            log_message('error', 'PfSenseBackupService: file_put_contents threw exception: ' . $e->getMessage());
            $writeSuccess = false;
        }

        if (!$writeSuccess) {
            log_message('error', "PfSenseBackupService: Failed to write backup to {$backupFilePath}. Directory writable: " . (is_writable($backupDir) ? 'yes' : 'no'));
            return [
                'statusCode' => 500,
                'body'       => ['error' => 'Failed to save backup: storage permission denied.'],
            ];
        }

        @chmod($backupFilePath, 0664);

        $this->backupModel->insert([
            'pf_id'      => $inventoryRecord['id'],
            'file_name'  => $backupFileName,
            'file_path'  => $backupFilePath,
            'quantity'   => $inventoryRecord['quantity'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->applyRetentionPolicy($backupDir);

        return [
            'statusCode' => 200,
            'body'       => [
                'message' => 'Backup saved successfully.',
                'file'    => $backupFileName,
            ],
        ];
    }

    /**
     * Resolves the actual file path on disk for a backup record.
     */
    public static function resolveBackupPath(array $backup, ?string $deviceName = null): ?string
    {
        $stored = $backup['file_path'] ?? '';
        if (!empty($stored) && file_exists($stored)) {
            return $stored;
        }

        $fileName = $backup['file_name'] ?? basename($stored);
        if (empty($fileName)) {
            return null;
        }

        $candidates = [];
        if (!empty($stored)) {
            $folderName   = basename(dirname($stored));
            $candidates[] = WRITEPATH . 'pfcentral_backup/' . $folderName . '/' . $fileName;
            $candidates[] = WRITEPATH . 'noc_backup/' . $folderName . '/' . $fileName;
        }
        if (!empty($deviceName)) {
            $safeName     = trim((string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $deviceName), '_');
            $candidates[] = WRITEPATH . 'pfcentral_backup/' . $safeName . '/' . $fileName;
            $candidates[] = WRITEPATH . 'noc_backup/' . $safeName . '/' . $fileName;
        }

        // Also check with alternate filename (with or without branch code)
        if (!empty($deviceName) && preg_match('/^(\d+)/', $deviceName, $m)) {
            $branchCode  = $m[1];
            $altFileName = null;
            if (preg_match('/^backup_' . preg_quote($branchCode, '/') . '_(.+)$/', $fileName, $bm)) {
                $altFileName = 'backup_' . $bm[1];
            } elseif (preg_match('/^backup_(\d{8}_\d{6}.*)$/', $fileName, $bm)) {
                $altFileName = 'backup_' . $branchCode . '_' . $bm[1];
            }
            if ($altFileName) {
                if (!empty($stored)) {
                    $folderName   = basename(dirname($stored));
                    $candidates[] = WRITEPATH . 'pfcentral_backup/' . $folderName . '/' . $altFileName;
                    $candidates[] = WRITEPATH . 'noc_backup/' . $folderName . '/' . $altFileName;
                }
                $safeName     = trim((string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $deviceName), '_');
                $candidates[] = WRITEPATH . 'pfcentral_backup/' . $safeName . '/' . $altFileName;
                $candidates[] = WRITEPATH . 'noc_backup/' . $safeName . '/' . $altFileName;
            }
        }

        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                return $cand;
            }
        }

        return !empty($stored) ? $stored : null;
    }

    public static function formatBytes($bytes, int $precision = 1): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min((int) $pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function buildUnifiedDiff(array $lines1, array $lines2, int $maxChanges = 400): array
    {
        $diffResult = [];
        $i          = 0;
        $j          = 0;
        $len1       = count($lines1);
        $len2       = count($lines2);
        $changes    = 0;

        while ($i < $len1 && $j < $len2 && $changes < $maxChanges) {
            if ($lines1[$i] === $lines2[$j]) {
                $i++;
                $j++;
                continue;
            }

            $matchFound = false;
            for ($k = 1; $k <= 5; $k++) {
                if ($i + $k < $len1 && $lines1[$i + $k] === $lines2[$j]) {
                    for ($m = 0; $m < $k; $m++) {
                        $diffResult[] = ['type' => 'del', 'line' => $lines1[$i + $m], 'num1' => $i + $m + 1, 'num2' => null];
                        $changes++;
                    }
                    $i += $k;
                    $matchFound = true;
                    break;
                }
                if ($j + $k < $len2 && $lines1[$i] === $lines2[$j + $k]) {
                    for ($m = 0; $m < $k; $m++) {
                        $diffResult[] = ['type' => 'add', 'line' => $lines2[$j + $m], 'num1' => null, 'num2' => $j + $m + 1];
                        $changes++;
                    }
                    $j += $k;
                    $matchFound = true;
                    break;
                }
            }

            if (!$matchFound) {
                $diffResult[] = ['type' => 'del', 'line' => $lines1[$i], 'num1' => $i + 1, 'num2' => null];
                $diffResult[] = ['type' => 'add', 'line' => $lines2[$j], 'num1' => null, 'num2' => $j + 1];
                $i++;
                $j++;
                $changes += 2;
            }
        }

        while ($i < $len1 && $changes < $maxChanges) {
            $diffResult[] = ['type' => 'del', 'line' => $lines1[$i], 'num1' => $i + 1, 'num2' => null];
            $i++;
            $changes++;
        }

        while ($j < $len2 && $changes < $maxChanges) {
            $diffResult[] = ['type' => 'add', 'line' => $lines2[$j], 'num1' => null, 'num2' => $j + 1];
            $j++;
            $changes++;
        }

        return [
            'changes_count' => $changes,
            'truncated'     => ($changes >= $maxChanges),
            'lines'         => $diffResult,
        ];
    }

    /**
     * Discovers backups on disk and fetches indexed backups with stats.
     */
    public function getDeviceBackupsWithStats(int $deviceId, ?array $device = null): array
    {
        $device = $device ?? $this->inventoryModel->find($deviceId);

        // Auto-discover backups on disk if missing or new
        if ($device && !empty($device['name'])) {
            $safeName = trim((string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $device['name']), '_');
            $scanDirs = [
                WRITEPATH . 'pfcentral_backup/' . $safeName,
                WRITEPATH . 'noc_backup/' . $safeName,
            ];
            foreach ($scanDirs as $dir) {
                if (is_dir($dir)) {
                    $xmlFiles = glob($dir . '/*.xml') ?: [];
                    if (!empty($xmlFiles)) {
                        $existingFiles = $this->backupModel->where('pf_id', $deviceId)->findColumn('file_name') ?? [];
                        $existingMap   = array_flip($existingFiles);
                        foreach ($xmlFiles as $xmlFile) {
                            $baseFile = basename($xmlFile);
                            if (!isset($existingMap[$baseFile])) {
                                $fileTime = @filemtime($xmlFile) ?: time();
                                $this->backupModel->insert([
                                    'pf_id'      => $deviceId,
                                    'file_name'  => $baseFile,
                                    'file_path'  => $xmlFile,
                                    'quantity'   => $device['quantity'] ?? 1,
                                    'created_at' => date('Y-m-d H:i:s', $fileTime),
                                ]);
                                $existingMap[$baseFile] = true;
                            }
                        }
                    }
                }
            }
        }

        $backups = $this->backupModel
            ->where('pf_id', $deviceId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        $totalBytes      = 0;
        $validCount      = 0;
        $latestTimestamp = 0;

        foreach ($backups as &$backup) {
            $realPath = self::resolveBackupPath($backup, $device['name'] ?? null);
            $backup['resolved_path'] = $realPath;
            if ($realPath && file_exists($realPath)) {
                $bytes                      = (int) @filesize($realPath);
                $backup['exists']           = true;
                $backup['file_size']        = $bytes;
                $backup['file_size_human']  = self::formatBytes($bytes);
                $totalBytes                += $bytes;
                $validCount++;
            } else {
                $backup['exists']          = false;
                $backup['file_size']       = 0;
                $backup['file_size_human'] = 'Not on Disk';
            }

            $backupTime = strtotime($backup['created_at']);
            if ($backupTime > $latestTimestamp) {
                $latestTimestamp = $backupTime;
            }
        }
        unset($backup);

        return [
            'backups' => $backups,
            'stats'   => [
                'total_count'       => count($backups),
                'valid_count'       => $validCount,
                'total_bytes'       => $totalBytes,
                'total_bytes_human' => self::formatBytes($totalBytes),
                'avg_bytes_human'   => $validCount > 0 ? self::formatBytes((int) ($totalBytes / $validCount)) : '0 B',
                'latest_timestamp'  => $latestTimestamp,
                'latest_human'      => $latestTimestamp > 0 ? format_datetime(date('Y-m-d H:i:s', $latestTimestamp)) : 'Never',
            ],
        ];
    }

    /**
     * Prepares a backup file for download.
     *
     * @param int $backupId
     * @return array{realPath: string, downloadFileName: string}|null
     */
    public function prepareDownload(int $backupId): ?array
    {
        $backup = $this->backupModel->find($backupId);
        if (!$backup) {
            return null;
        }

        $device   = $this->inventoryModel->find($backup['pf_id']);
        $realPath = self::resolveBackupPath($backup, $device['name'] ?? null);

        if (!$realPath || !file_exists($realPath)) {
            return null;
        }

        $downloadFileName = $backup['file_name'];
        if (!empty($device['name']) && preg_match('/^(\d+)/', $device['name'], $m)) {
            $branchCode = $m[1];
            if (!str_contains($downloadFileName, $branchCode)) {
                if (preg_match('/^backup_(.+)$/', $downloadFileName, $bm)) {
                    $downloadFileName = 'backup_' . $branchCode . '_' . $bm[1];
                } else {
                    $downloadFileName = $branchCode . '_' . $downloadFileName;
                }
            }
        }

        return [
            'realPath'         => $realPath,
            'downloadFileName' => $downloadFileName,
        ];
    }

    /**
     * Purges backups older than the $keep limit for a device.
     */
    public function purgeBackups(int $deviceId, int $keep): array
    {
        if ($keep < 1) {
            $keep = 30;
        }

        $device  = $this->inventoryModel->find($deviceId);
        $backups = $this->backupModel
            ->where('pf_id', $deviceId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        if (count($backups) <= $keep) {
            return [
                'purged'  => 0,
                'message' => 'No backups older than the selected limit were found.',
            ];
        }

        $backupsToPurge = array_slice($backups, $keep);
        $deletedFiles   = 0;

        foreach ($backupsToPurge as $backup) {
            $realPath = self::resolveBackupPath($backup, $device['name'] ?? null);
            if ($realPath && file_exists($realPath)) {
                @unlink($realPath);
            }
            $this->backupModel->delete($backup['id']);
            $deletedFiles++;
        }

        return [
            'purged'  => $deletedFiles,
            'message' => "Purged $deletedFiles older backups successfully.",
        ];
    }

    /**
     * Deletes a single backup by ID.
     */
    public function deleteSingleBackup(int $backupId): array
    {
        $backup = $this->backupModel->find($backupId);
        if (!$backup) {
            return ['success' => false, 'message' => 'Backup record not found.'];
        }

        $device   = $this->inventoryModel->find($backup['pf_id']);
        $realPath = self::resolveBackupPath($backup, $device['name'] ?? null);

        if ($realPath && file_exists($realPath)) {
            @unlink($realPath);
        }

        $this->backupModel->delete($backupId);

        return [
            'success'   => true,
            'deviceId'  => (int) $backup['pf_id'],
            'fileName'  => (string) $backup['file_name'],
            'message'   => "Backup {$backup['file_name']} permanently deleted.",
        ];
    }

    /**
     * Parses a backup XML file for preview inspection.
     */
    public function previewBackup(int $backupId): array
    {
        $backup = $this->backupModel->find($backupId);
        if (!$backup) {
            return ['statusCode' => 404, 'body' => ['success' => false, 'message' => 'Backup record not found.']];
        }

        $device   = $this->inventoryModel->find($backup['pf_id']);
        $realPath = self::resolveBackupPath($backup, $device['name'] ?? null);

        if (!$realPath || !file_exists($realPath)) {
            return ['statusCode' => 404, 'body' => ['success' => false, 'message' => 'Backup XML file is missing from disk storage.']];
        }

        $xmlString = @file_get_contents($realPath);
        if ($xmlString === false) {
            return ['statusCode' => 500, 'body' => ['success' => false, 'message' => 'Failed to read XML file contents.']];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            libxml_clear_errors();
            return [
                'statusCode' => 200,
                'body'       => [
                    'success' => false,
                    'message' => 'File is not a valid pfSense XML structure.',
                    'raw'     => mb_substr($xmlString, 0, 5000),
                ],
            ];
        }

        $version    = (string) ($xml->version ?? 'N/A');
        $hostname   = (string) ($xml->system->hostname ?? 'N/A');
        $domain     = (string) ($xml->system->domain ?? '');
        $fullHost   = $hostname . ($domain !== '' ? '.' . $domain : '');
        $timezone   = (string) ($xml->system->timezone ?? 'N/A');
        $lastchange = (string) ($xml->lastchange ?? 'N/A');

        $users = [];
        if (isset($xml->system->user)) {
            foreach ($xml->system->user as $u) {
                $users[] = [
                    'name'  => (string) ($u->name ?? ''),
                    'descr' => (string) ($u->descr ?? ''),
                    'scope' => (string) ($u->scope ?? 'user'),
                ];
            }
        }

        $interfaces = [];
        if (isset($xml->interfaces)) {
            foreach ($xml->interfaces->children() as $tag => $iface) {
                $descr        = (string) ($iface->descr ?? '');
                $interfaces[] = [
                    'tag'    => strtoupper($tag),
                    'name'   => strtoupper($tag) . ($descr !== '' ? ' (' . $descr . ')' : ''),
                    'if'     => (string) ($iface->if ?? ''),
                    'ipaddr' => (string) ($iface->ipaddr ?? 'Dynamic / Unset'),
                    'subnet' => (string) ($iface->subnet ?? ''),
                    'enable' => isset($iface->enable),
                ];
            }
        }

        $staticMapCount = 0;
        if (isset($xml->dhcpd)) {
            foreach ($xml->dhcpd->children() as $dhcpSection) {
                if (isset($dhcpSection->staticmap)) {
                    $staticMapCount += count($dhcpSection->staticmap);
                }
            }
        }

        $packages = [];
        if (isset($xml->installedpackages->package)) {
            foreach ($xml->installedpackages->package as $pkg) {
                $packages[] = [
                    'name'    => (string) ($pkg->name ?? ''),
                    'version' => (string) ($pkg->version ?? ''),
                    'descr'   => (string) ($pkg->descr ?? ''),
                ];
            }
        }

        $lines      = explode("\n", $xmlString);
        $totalLines = count($lines);
        $snippet    = implode("\n", array_slice($lines, 0, 400));

        return [
            'statusCode' => 200,
            'body'       => [
                'success'          => true,
                'file_name'        => $backup['file_name'],
                'file_size'        => self::formatBytes(filesize($realPath)),
                'created_at'       => format_datetime($backup['created_at']),
                'version'          => $version,
                'hostname'         => $fullHost,
                'timezone'         => $timezone,
                'lastchange'       => $lastchange,
                'users'            => $users,
                'interfaces'       => $interfaces,
                'packages'         => $packages,
                'static_map_count' => $staticMapCount,
                'total_lines'      => $totalLines,
                'snippet'          => $snippet,
            ],
        ];
    }

    /**
     * Compares two backups and builds unified diff lines.
     */
    public function diffBackups(int $b1Id, int $b2Id): array
    {
        if (!$b1Id || !$b2Id) {
            return ['statusCode' => 400, 'body' => ['success' => false, 'message' => 'Please select two backups to compare.']];
        }

        $b1 = $this->backupModel->find($b1Id);
        $b2 = $this->backupModel->find($b2Id);

        if (!$b1 || !$b2) {
            return ['statusCode' => 404, 'body' => ['success' => false, 'message' => 'One or both backups were not found.']];
        }

        $device = $this->inventoryModel->find($b1['pf_id']);
        $p1     = self::resolveBackupPath($b1, $device['name'] ?? null);
        $p2     = self::resolveBackupPath($b2, $device['name'] ?? null);

        if (!$p1 || !file_exists($p1) || !$p2 || !file_exists($p2)) {
            return ['statusCode' => 404, 'body' => ['success' => false, 'message' => 'One or both backup files could not be read on disk.']];
        }

        $content1 = @file_get_contents($p1) ?: '';
        $content2 = @file_get_contents($p2) ?: '';

        $lines1 = explode("\n", str_replace("\r\n", "\n", $content1));
        $lines2 = explode("\n", str_replace("\r\n", "\n", $content2));

        $diff = self::buildUnifiedDiff($lines1, $lines2, 350);

        return [
            'statusCode' => 200,
            'body'       => [
                'success' => true,
                'b1'      => [
                    'id'        => $b1['id'],
                    'file_name' => $b1['file_name'],
                    'date'      => format_datetime($b1['created_at']),
                    'size'      => self::formatBytes(filesize($p1)),
                ],
                'b2'      => [
                    'id'        => $b2['id'],
                    'file_name' => $b2['file_name'],
                    'date'      => format_datetime($b2['created_at']),
                    'size'      => self::formatBytes(filesize($p2)),
                ],
                'diff'    => $diff,
            ],
        ];
    }

    /**
     * Builds a Disaster Recovery (DR) package ZIP.
     */
    public function buildDrPackage(int $deviceId): ?array
    {
        $device = $this->inventoryModel->find($deviceId);
        if (!$device) {
            return null;
        }

        $latestBackup = $this->backupModel
            ->where('pf_id', $deviceId)
            ->orderBy('created_at', 'DESC')
            ->first();

        $realPath = null;
        if ($latestBackup) {
            $realPath = self::resolveBackupPath($latestBackup, $device['name'] ?? null);
        }

        if (!$realPath || !file_exists($realPath)) {
            $safeName   = trim((string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $device['name']), '_');
            $candidates = [
                WRITEPATH . 'pfcentral_backup/' . $safeName,
                WRITEPATH . 'noc_backup/' . $safeName,
            ];
            foreach ($candidates as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . '/*.xml') ?: [];
                    if (!empty($files)) {
                        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
                        $realPath = $files[0];
                        break;
                    }
                }
            }
        }

        if (!$realPath || !file_exists($realPath)) {
            return null;
        }

        $xmlString = @file_get_contents($realPath) ?: '';
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

        $hostname = (string) ($xml->system->hostname ?? ($device['name'] ?? 'pfsense'));
        $domain   = (string) ($xml->system->domain ?? 'local');
        $version  = (string) ($xml->version ?? 'Unknown');
        $manIp    = $device['ip'] ?? $device['man_ip'] ?? 'Unknown';
        $manPort  = $device['port'] ?? $device['man_port'] ?? '443';
        $serial   = $device['serial'] ?? 'Unknown';
        $dateStr  = date('Ymd_His');

        $ifSummary = '';
        if (isset($xml->interfaces)) {
            foreach ($xml->interfaces->children() as $tag => $iface) {
                $descr      = (string) ($iface->descr ?? strtoupper($tag));
                $hwIf       = (string) ($iface->if ?? 'N/A');
                $ip         = (string) ($iface->ipaddr ?? 'DHCP/Dynamic');
                $subnet     = (string) ($iface->subnet ?? '');
                $ifSummary .= sprintf("  - %-10s (Physical: %-8s) -> IP: %s%s\n", $descr, $hwIf, $ip, $subnet ? "/$subnet" : '');
            }
        }

        $readme = "========================================================================\n"
            . "  PFCENTRAL - CENTRALIZED NETWORK OPERATIONS PLATFORM\n"
            . "  EMERGENCY DISASTER RECOVERY (DR) PACKAGE\n"
            . "========================================================================\n\n"
            . "DEVICE IDENTITY:\n"
            . "  Branch Name       : " . ($device['name'] ?? 'N/A') . "\n"
            . "  Hardware Serial   : " . $serial . "\n"
            . "  System FQDN       : " . $hostname . "." . $domain . "\n"
            . "  pfSense Version   : " . $version . "\n"
            . "  Management IP     : " . $manIp . ":" . $manPort . "\n"
            . "  Package Generated : " . date('d-M-Y H:i:s T') . "\n"
            . "  Backup Timestamp  : " . ($latestBackup['created_at'] ?? 'N/A') . "\n\n"
            . "INTERFACE ASSIGNMENTS ON RECORD:\n"
            . ($ifSummary ?: "  (Default pfSense interfaces)\n") . "\n"
            . "------------------------------------------------------------------------\n"
            . "HARDWARE REPLACEMENT & RESTORATION PROCEDURE:\n"
            . "------------------------------------------------------------------------\n"
            . "METHOD 1: AUTOMATIC RESTORE ON FRESH INSTALL (RECOMMENDED FOR SPARE UNITS)\n"
            . "  1. Copy the enclosed 'config.xml' to the root of a FAT32 USB flash drive.\n"
            . "  2. Label the USB drive 'CONFIG' or place in directory '/conf/config.xml'.\n"
            . "  3. Boot the replacement pfSense firewall with the USB plugged in.\n"
            . "  4. pfSense installer will automatically discover and import this configuration.\n\n"
            . "METHOD 2: RESTORE VIA PFSENSE WEBGUI\n"
            . "  1. Complete initial setup wizard on replacement hardware.\n"
            . "  2. Navigate to: Diagnostics -> Backup & Restore -> Restore Backup tab.\n"
            . "  3. Select 'Restore Area: ALL' and browse to this 'config.xml'.\n"
            . "  4. Click 'Restore Configuration'. The firewall will reboot into full branch operational state.\n\n"
            . "METHOD 3: RESTORE VIA CONSOLE / SSH (EMERGENCY RESCUE SHELL)\n"
            . "  1. Connect serial console or SSH (Option 8: Shell).\n"
            . "  2. Copy 'config.xml' to '/cf/conf/config.xml'.\n"
            . "  3. Run: rm -f /tmp/config.cache\n"
            . "  4. Run: reboot\n\n"
            . "PFCENTRAL CONTACT:\n"
            . "  pfCentral Community Desk | Ext: 9999 / 9001\n"
            . "========================================================================\n";

        $bootstrapScript = "#!/bin/sh\n"
            . "# Emergency pfSense Config Restoration Script\n"
            . "# Generated for: " . escapeshellarg($device['name'] ?? 'Branch') . "\n"
            . "echo '==> Restoring branch firewall configuration...'\n"
            . "if [ ! -f /cf/conf/config.xml.bak ]; then\n"
            . "    cp /cf/conf/config.xml /cf/conf/config.xml.bak\n"
            . "fi\n"
            . "cp config.xml /cf/conf/config.xml\n"
            . "rm -f /tmp/config.cache\n"
            . "echo '==> Configuration written. Rebooting firewall in 5 seconds...'\n"
            . "sleep 5\n"
            . "reboot\n";

        $manifest = json_encode([
            'device_id'   => $deviceId,
            'name'        => $device['name'] ?? '',
            'ip'          => $manIp,
            'port'        => $manPort,
            'serial'      => $serial,
            'platform'    => $device['platform'] ?? 'pfSenseCE',
            'hostname'    => $hostname . '.' . $domain,
            'version'     => $version,
            'backup_file' => basename($realPath),
            'generated_at'=> date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT);

        $zipName = 'DR_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $device['name']) . '_' . $dateStr . '.zip';
        $zipPath = WRITEPATH . 'tmp/' . $zipName;

        if (!is_dir(WRITEPATH . 'tmp')) {
            @mkdir(WRITEPATH . 'tmp', 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        $zip->addFromString('config.xml', $xmlString);
        $zip->addFromString('RECOVERY_README.txt', $readme);
        $zip->addFromString('quick_restore_console.sh', $bootstrapScript);
        $zip->addFromString('device_profile.json', $manifest);
        $zip->close();

        return [
            'zipPath' => $zipPath,
            'zipName' => $zipName,
        ];
    }

    public function applyRetentionPolicy(string $backupDir): void
    {
        try {
            $result = $this->retentionService->prune($backupDir);

            if (is_cli()) {
                if ($result['scanned'] === 0) {
                    CLI::write("No backup files found in $backupDir.", 'yellow');
                    return;
                }

                foreach ($result['deleted'] as $entry) {
                    CLI::write("Deleted {$entry['tier']} backup: {$entry['path']}", 'red');
                }

                if ($result['failed'] !== []) {
                    CLI::write('Could not delete: ' . implode(', ', $result['failed']), 'yellow');
                }

                if ($result['deleted'] !== []) {
                    CLI::write('Deleted ' . count($result['deleted']) . " old backup files in $backupDir.", 'green');
                } else {
                    CLI::write("No old backup files were deleted in $backupDir.", 'yellow');
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'PfSenseBackupService: applyRetentionPolicy failed: ' . $e->getMessage());
        }
    }
}
