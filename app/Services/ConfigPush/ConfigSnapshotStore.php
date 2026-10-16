<?php

namespace App\Services\ConfigPush;

use App\Models\PfBakupModel;
use App\Services\BackupRetentionService;

/**
 * Stores a device's configuration as a restorable pre-change snapshot.
 *
 * This is the one place that knows how a rollback artifact is laid down — file naming, the
 * pf_backups row, and retention pruning — so it does not matter *how* the config arrived:
 *
 *   - the SSH transport downloads /cf/conf/config.xml itself (Phase 0), and
 *   - the device agent uploads the same file alongside its job report (Phase 2), because a
 *     device-initiated push means pfCentral never opens a connection to read it.
 *
 * Both produce the same artifact, which is what keeps one-click rollback working regardless of
 * which transport delivered the change.
 */
class ConfigSnapshotStore
{
    public const PREFIX_PREPUSH    = 'prepush';
    public const PREFIX_ROLLBACK   = 'prerollback';
    public const PREFIX_AGENT      = 'agentprepush';

    /** config.xml is text; anything vastly larger is a malformed or hostile upload. */
    public const MAX_BYTES = 8 * 1024 * 1024;

    protected PfBakupModel $backupModel;
    protected BackupRetentionService $retention;

    public function __construct(?PfBakupModel $backupModel = null, ?BackupRetentionService $retention = null)
    {
        $this->backupModel = $backupModel ?? new PfBakupModel();
        $this->retention   = $retention ?? new BackupRetentionService();
    }

    /**
     * Writes the snapshot for a device. The caller is responsible for emitting its own log lines.
     *
     * @return array{ok:bool, id:?int, file:?string, message:string, pruned:int, failed:list<string>}
     */
    public function store(array $device, string $deviceName, string $contents, string $batchId, string $prefix = self::PREFIX_PREPUSH): array
    {
        $problem = self::validate($contents);
        if ($problem !== null) {
            return $this->fail($problem);
        }

        $safeName  = self::safeName($deviceName);
        $backupDir = WRITEPATH . 'noc_backup/' . $safeName;

        if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            return $this->fail('Could not create the backup directory for ' . $safeName . '.');
        }

        // Extract branch code from the device name (leading digits: "1001-MAIN BRANCH" → "1001").
        // Falls back to "dev{id}" so the filename is still unambiguous when no code is present.
        $branchCode = '';
        if (preg_match('/^(\d+)/', (string) ($device['name'] ?? ''), $m)) {
            $branchCode = $m[1];
        } elseif (!empty($device['id'])) {
            $branchCode = 'dev' . $device['id'];
        }
        $branchPart = $branchCode !== '' ? $branchCode . '_' : '';

        $fileName = $prefix . '_' . $branchPart . date('Ymd_His') . '_' . substr($batchId, 0, 6) . '_' . bin2hex(random_bytes(2)) . '.xml';
        $filePath = $backupDir . '/' . $fileName;

        if (@file_put_contents($filePath, $contents) === false) {
            return $this->fail('Failed to write the backup to disk.');
        }

        // The file is safely on disk, so a failed row insert must not lose the snapshot.
        $backupId = null;
        try {
            $backupId = $this->backupModel->insert([
                'pf_id'      => $device['id'] ?? null,
                'file_name'  => $fileName,
                'file_path'  => $filePath,
                'quantity'   => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'Backup row insert failed: ' . $e->getMessage());
        }

        $pruned = $this->retention->prune($backupDir);

        return [
            'ok'      => true,
            'id'      => $backupId ? (int) $backupId : null,
            'file'    => $fileName,
            'message' => 'Backup saved: ' . $fileName,
            'pruned'  => count($pruned['deleted']),
            'failed'  => $pruned['failed'],
        ];
    }

    /**
     * @return array{ok:bool, id:?int, file:?string, message:string, pruned:int, failed:list<string>}
     */
    private function fail(string $message): array
    {
        return ['ok' => false, 'id' => null, 'file' => null, 'message' => $message, 'pruned' => 0, 'failed' => []];
    }

    /**
     * The most recent stored config for a device, for previewing a change against.
     *
     * Reads through pf_backups first (the authoritative artifact) and falls back to the newest file
     * in the device's folder, so a snapshot whose row was pruned or lost is still usable.
     *
     * @return array{ok:bool, contents:?string, file:?string, path:?string, captured_at:?int, message:string}
     */
    public function latest(array $device): array
    {
        $pfId = (int) ($device['id'] ?? 0);
        $row  = null;

        if ($pfId > 0) {
            try {
                $row = $this->backupModel->where('pf_id', $pfId)->orderBy('id', 'DESC')->first();
            } catch (\Throwable $e) {
                log_message('error', 'pf_backups read failed: ' . $e->getMessage());
            }
        }

        $path = (string) ($row['file_path'] ?? '');

        if ($path === '' || !is_file($path)) {
            $path = $this->newestFileFor((string) ($device['name'] ?? ''));
        }

        if ($path === '' || !is_file($path)) {
            return [
                'ok'          => false,
                'contents'    => null,
                'file'        => null,
                'path'        => null,
                'captured_at' => null,
                'message'     => 'No stored configuration snapshot exists for this device yet.',
            ];
        }

        $contents = @file_get_contents($path);
        $problem  = $contents === false ? 'The stored snapshot could not be read.' : self::validate($contents);

        if ($problem !== null) {
            return [
                'ok'          => false,
                'contents'    => null,
                'file'        => basename($path),
                'path'        => $path,
                'captured_at' => @filemtime($path) ?: null,
                'message'     => $problem,
            ];
        }

        return [
            'ok'          => true,
            'contents'    => (string) $contents,
            'file'        => basename($path),
            'path'        => $path,
            'captured_at' => @filemtime($path) ?: null,
            'message'     => 'Comparing against ' . basename($path) . '.',
        ];
    }

    /**
     * One *specific* stored snapshot: the one a deployment recorded, found by its row or its file name.
     *
     * `latest()` answers "what is the newest copy of this device's configuration?", which is what a diff
     * preview wants. This answers the stricter question — "give me *that* snapshot back" — because a
     * rollback has to restore the configuration from immediately before the change being undone, not the
     * newest one on disk. After a later push the newest snapshot is the state the device is in *now*, and
     * restoring it would be a no-op dressed up as a repair.
     *
     * One definition of "find this snapshot on disk", so a rollback run by hand and a rollback queued by
     * the verification window resolve the same artifact the same way — including the fallback to the
     * device's backup folder for a snapshot whose `pf_backups` row has been pruned.
     *
     * The file is required to be a *complete* configuration (`requireFullDocument`): whatever this returns
     * is destined to be written over the device's `/cf/conf/config.xml`, and a fragment would replace the
     * whole configuration with one section rather than merging into it.
     *
     * @return array{ok:bool, id:?int, file:?string, path:?string, contents:?string, message:string}
     */
    public function resolve(array $device, ?int $backupId, ?string $fileName): array
    {
        $path         = null;
        $resolvedId   = null;
        $resolvedName = null;

        if (!empty($backupId)) {
            try {
                $row = $this->backupModel->find($backupId);
            } catch (\Throwable $e) {
                log_message('error', 'pf_backups read failed while resolving a snapshot: ' . $e->getMessage());
                $row = null;
            }

            if ($row) {
                $path         = $row['file_path'] ?? null;
                $resolvedId   = $row['id'] ?? $backupId;
                $resolvedName = $row['file_name'] ?? $fileName;
            }
        }

        // Fall back to the recorded file name if the row is gone: the artifact is still on disk, and
        // losing the index entry must not lose the ability to put a device back.
        if (empty($path) && !empty($fileName)) {
            $candidate = WRITEPATH . 'noc_backup/' . self::safeName((string) ($device['name'] ?? '')) . '/' . $fileName;

            if (is_file($candidate)) {
                $path         = $candidate;
                $resolvedName = $resolvedName ?: $fileName;
            }
        }

        if (empty($path) || !is_file($path)) {
            return $this->cannotResolve('No pre-push backup file is available for this deployment, so it cannot be rolled back.');
        }

        $contents = @file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return $this->cannotResolve('The stored backup is empty and cannot be restored.');
        }

        $problem = self::validate($contents, true);

        if ($problem !== null) {
            return $this->cannotResolve('The stored backup cannot be restored: ' . $problem . '.');
        }

        return [
            'ok'       => true,
            'id'       => $resolvedId !== null ? (int) $resolvedId : null,
            'file'     => $resolvedName !== null ? (string) $resolvedName : null,
            'path'     => $path,
            'contents' => $contents,
            'message'  => 'Backup resolved: ' . ($resolvedName ?? basename($path)),
        ];
    }

    /**
     * @return array{ok:bool, id:null, file:null, path:null, contents:null, message:string}
     */
    private function cannotResolve(string $message): array
    {
        return ['ok' => false, 'id' => null, 'file' => null, 'path' => null, 'contents' => null, 'message' => $message];
    }

    private function newestFileFor(string $deviceName): string
    {
        $dir   = WRITEPATH . 'noc_backup/' . self::safeName($deviceName);
        $files = glob($dir . '/*.xml') ?: [];

        if ($files === []) {
            return '';
        }

        usort($files, static fn ($a, $b) => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));

        return $files[0];
    }

    /**
     * The same folder naming the SSH transport and the retention policy use, so a snapshot written
     * by any transport is found by every consumer.
     */
    public static function safeName(string $deviceName): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $deviceName), '_');
    }

    /**
     * A truncated or empty read is worse than no backup, because it would become a corrupt
     * rollback artifact. Returns a human-readable reason, or null when the payload is usable.
     *
     * @param bool $requireFullDocument Reject a bare section fragment. Set this for anything destined
     *                                  to *become* a device's config file — both transports write the
     *                                  payload straight over `/cf/conf/config.xml`, so a fragment would
     *                                  replace the whole configuration with that one section, not merge
     *                                  into it. Fragment merging is still unimplemented (see Phase 3
     *                                  in remote_config_architecture.md).
     */
    public static function validate(string $contents, bool $requireFullDocument = false): ?string
    {
        if (trim($contents) === '') {
            return 'The configuration snapshot is empty.';
        }

        if (strlen($contents) > self::MAX_BYTES) {
            return 'The configuration snapshot is implausibly large (> ' . round(self::MAX_BYTES / 1048576) . ' MB) and was refused.';
        }

        libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($contents);

        if ($parsed === false) {
            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message);
            }
            libxml_clear_errors();

            return 'The configuration snapshot is not valid XML: ' . implode('; ', array_slice($errors, 0, 3));
        }

        if ($requireFullDocument && strtolower($parsed->getName()) !== 'pfsense') {
            return 'this is a <' . $parsed->getName() . '> fragment, not a complete <pfsense> configuration. '
                . 'No transport can merge a fragment into a device yet, and writing one as the config '
                . 'would replace the device\'s entire configuration with that single section.';
        }

        return null;
    }
}
