<?php

namespace App\Services;

/**
 * Tiered backup retention, extracted from PfSenseController so that every producer of
 * config.xml snapshots into writable/noc_backup/<device>/ (the scheduled device backup
 * endpoint and the SSH config push pre-push snapshots) prunes by the same rules.
 *
 * Retention semantics are unchanged from the original controller method:
 *   - older than 7 days  and not a Monday        -> daily tier,   removed
 *   - older than 28 days and not the 1st of month -> weekly tier,  removed
 *   - older than 360 days                         -> monthly tier, removed
 * Files newer than the daily window, and the ones that fall on a Monday / 1st of the
 * month inside their window, are kept.
 */
class BackupRetentionService
{
    /** @var array{daily:int, weekly:int, monthly:int} */
    protected array $policy;

    /**
     * @param array{daily?:int, weekly?:int, monthly?:int}|null $policy
     */
    public function __construct(?array $policy = null)
    {
        $this->policy = [
            'daily'   => (int) ($policy['daily']   ?? 7),   // keep daily backups for 7 days
            'weekly'  => (int) ($policy['weekly']  ?? 4),   // keep weekly backups for 4 weeks (28 days)
            'monthly' => (int) ($policy['monthly'] ?? 12),  // keep monthly backups for 12 months (360 days)
        ];
    }

    /**
     * @return array{daily:int, weekly:int, monthly:int}
     */
    public function policy(): array
    {
        return $this->policy;
    }

    /**
     * Prunes aged-out files from one device backup folder.
     *
     * Returned shape:
     *   scanned => int                         files considered
     *   deleted => list<array{file,path,tier,age_days}>
     *   failed  => list<string>                files that matched but could not be removed
     *
     * @return array{scanned:int, deleted:list<array{file:string,path:string,tier:string,age_days:int}>, failed:list<string>}
     */
    public function prune(string $backupDir, string $pattern = '*.xml'): array
    {
        $result = ['scanned' => 0, 'deleted' => [], 'failed' => []];

        if (! is_dir($backupDir)) {
            return $result;
        }

        $files = glob(rtrim($backupDir, '/\\') . '/' . $pattern);
        if (empty($files)) {
            return $result;
        }

        $result['scanned'] = count($files);

        // Oldest first, matching the original sweep order.
        usort($files, static function ($a, $b) {
            return filemtime($a) <=> filemtime($b);
        });

        $now     = time();
        $daily   = $this->policy['daily'];
        $weekly  = $this->policy['weekly'];
        $monthly = $this->policy['monthly'];

        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime === false) {
                continue;
            }

            $ageDays = (int) floor(($now - $mtime) / (60 * 60 * 24));
            $tier    = null;

            if ($ageDays > $daily && $ageDays <= $weekly * 7) {
                if (date('N', $mtime) != 1) { // not a Monday -> drop the daily copy
                    $tier = 'daily';
                }
            } elseif ($ageDays > $weekly * 7 && $ageDays <= $monthly * 30) {
                if (date('j', $mtime) != 1) { // not the 1st -> drop the weekly copy
                    $tier = 'weekly';
                }
            } elseif ($ageDays > $monthly * 30) {
                $tier = 'monthly';
            }

            if ($tier === null) {
                continue;
            }

            if (@unlink($file)) {
                $result['deleted'][] = [
                    'file'     => basename($file),
                    'path'     => $file,
                    'tier'     => $tier,
                    'age_days' => $ageDays,
                ];
            } else {
                $result['failed'][] = basename($file);
            }
        }

        return $result;
    }
}
