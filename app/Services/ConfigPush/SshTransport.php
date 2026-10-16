<?php

namespace App\Services\ConfigPush;

use App\Models\PfStatusModel;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;

/**
 * The original direct-SSH mechanism, unchanged in behaviour but lifted out of the
 * controller so the job runner can drive it.
 *
 * Safety rule carried over from Phase 0: a device is never modified unless its current
 * /cf/conf/config.xml could be downloaded and parsed first. If the snapshot fails, the
 * push is aborted rather than risking an unrestorable device.
 */
class SshTransport implements ConfigTransportInterface
{
    private const DEFAULT_SSH_PORT = 55255;
    private const CONNECT_TIMEOUT  = 10;
    private const EXEC_TIMEOUT     = 120;

    protected PfStatusModel $statusModel;
    protected ConfigSnapshotStore $snapshots;

    public function __construct(?PfStatusModel $statusModel = null, ?ConfigSnapshotStore $snapshots = null)
    {
        $this->statusModel = $statusModel ?? new PfStatusModel();
        $this->snapshots   = $snapshots ?? new ConfigSnapshotStore();
    }

    public function name(): string
    {
        return 'ssh';
    }

    public function supports(array $device, array $job = []): bool
    {
        // SSH can carry every kind of job, including a fragment merge: the merge runs here, against
        // the config this transport downloaded moments earlier, so the device needs no new software.
        return !empty($device['username']) && !empty($device['password']);
    }

    // ------------------------------------------------------------------ deliver

    public function deliver(array $device, array $job, callable $emit): array
    {
        $payloadType = $job['payload_type'] ?? 'xml';
        $payload     = (string) ($job['payload'] ?? '');
        $batchId     = (string) ($job['batch_id'] ?? '');

        // A section fragment is merged into the config that is about to be replaced — the very copy
        // that becomes the rollback snapshot, so the merge happens against live bytes rather than a
        // stored snapshot of unknown age.
        $isMerge = ConfigFragmentMerger::isMergeJob(['payload_type' => $payloadType, 'payload' => $payload]);

        $emit('info', $isMerge ? 'Processing fragment merge...' : 'Processing deployment...');

        $target = $this->resolveTarget($device);
        if ($target['error'] !== null) {
            return ['status' => 'failed', 'message' => $target['error'], 'ip' => $target['ip'] ?: null];
        }

        [$ip, $port, $username, $password] = $target['values'];

        $emit('info', "Connecting to secure SSH shell at $ip:$port...");

        try {
            $connection = $this->open($ip, $port, $username, $password, $emit);
            if ($connection['error'] !== null) {
                return ['status' => 'failed', 'message' => $connection['error'], 'ip' => $ip];
            }

            /** @var SSH2 $ssh */
            /** @var SFTP $sftp */
            [$ssh, $sftp] = $connection['values'];

            // --- Safety gate: never modify a device we could not snapshot first. ---
            $backup = $this->saveSafetyBackup($device, $device['name'] ?? ('device #' . $device['id']), $sftp, $batchId, $emit, 'prepush');
            if (!$backup['ok']) {
                return [
                    'status'      => 'failed',
                    'ip'          => $ip,
                    'message'     => 'Pre-push backup failed, so the deployment was aborted to protect the running config.',
                    'backup_id'   => null,
                    'backup_file' => null,
                ];
            }

            $shared = [
                'ip'          => $ip,
                'backup_id'   => $backup['id'],
                'backup_file' => $backup['file'],
            ];

            if ($payloadType === 'script') {
                return array_merge($shared, $this->runScript($ssh, $sftp, $payload, $emit));
            }

            if ($isMerge) {
                $merged = $this->mergeFragment((string) $backup['contents'], $payload, $emit);

                if (!$merged['ok']) {
                    return array_merge($shared, ['status' => 'failed', 'message' => $merged['message']]);
                }

                // A fragment that changes nothing means the device is already in the desired state.
                // Rewriting an identical config and reloading pfSense for no reason is pure risk, so
                // report the no-op and stop here — the pre-push snapshot is still recorded.
                if ($merged['changes'] === []) {
                    $emit('success', 'Device already matches this fragment; nothing was written.');

                    return array_merge($shared, [
                        'status'  => 'success',
                        'message' => 'Already in the desired state: ' . $merged['message'] . '.',
                    ]);
                }

                $payload = (string) $merged['config'];
            }

            return array_merge($shared, $this->pushXml($ssh, $sftp, $payload, $emit));

        } catch (\Throwable $e) {
            return ['status' => 'failed', 'ip' => $ip, 'message' => 'Exception occurred: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ read

    /**
     * Reads /cf/conf/config.xml for post-apply verification.
     *
     * Nothing is emitted: verification is background work, and a read failure must not turn a
     * successful deployment's log into a wall of connection noise.
     */
    public function readConfig(array $device): array
    {
        $silent = static function (string $status, string $message): void {
        };

        $target = $this->resolveTarget($device);
        if ($target['error'] !== null) {
            return ['ok' => false, 'contents' => null, 'message' => $target['error']];
        }

        [$ip, $port, $username, $password] = $target['values'];

        try {
            $connection = $this->open($ip, $port, $username, $password, $silent);
            if ($connection['error'] !== null) {
                return ['ok' => false, 'contents' => null, 'message' => $connection['error']];
            }

            /** @var SFTP $sftp */
            [, $sftp] = $connection['values'];

            $contents = $sftp->get('/cf/conf/config.xml');
            if ($contents === false || !is_string($contents)) {
                return ['ok' => false, 'contents' => null, 'message' => 'Could not read /cf/conf/config.xml over SFTP.'];
            }

            $problem = ConfigSnapshotStore::validate($contents);
            if ($problem !== null) {
                return ['ok' => false, 'contents' => null, 'message' => 'The configuration read from the device is not usable: ' . $problem];
            }

            return ['ok' => true, 'contents' => $contents, 'message' => 'Read the running configuration from the device.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'contents' => null, 'message' => 'Could not read the device configuration: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ restore

    public function restore(array $device, array $backup, callable $emit): array
    {
        $emit('info', 'Starting rollback to ' . ($backup['file_name'] ?? 'stored backup') . '...');

        $target = $this->resolveTarget($device);
        if ($target['error'] !== null) {
            return ['status' => 'failed', 'message' => $target['error'], 'ip' => $target['ip'] ?: null];
        }

        [$ip, $port, $username, $password] = $target['values'];

        $contents = @file_get_contents($backup['path'] ?? '');
        if ($contents === false || trim($contents) === '') {
            $emit('error', 'The stored backup could not be read from disk.');
            return ['status' => 'failed', 'ip' => $ip, 'message' => 'The stored backup could not be read from disk.'];
        }

        $emit('info', "Connecting to secure SSH shell at $ip:$port...");

        try {
            $connection = $this->open($ip, $port, $username, $password, $emit);
            if ($connection['error'] !== null) {
                return ['status' => 'failed', 'message' => $connection['error'], 'ip' => $ip];
            }

            [$ssh, $sftp] = $connection['values'];

            // Keep what we are about to replace, but never block the rollback on it:
            // the current config may be exactly what is broken.
            $this->saveSafetyBackup($device, $device['name'] ?? ('device #' . $device['id']), $sftp, bin2hex(random_bytes(16)), $emit, 'prerollback');

            $emit('info', 'Uploading backup to /tmp/rollback_config.xml...');
            if (!$sftp->put('/tmp/rollback_config.xml', $contents)) {
                $emit('error', 'Failed to upload the backup via SFTP.');
                return ['status' => 'failed', 'ip' => $ip, 'message' => 'Failed to upload the backup via SFTP.'];
            }

            $emit('info', 'Restoring /cf/conf/config.xml from backup...');
            $ssh->exec('cp /tmp/rollback_config.xml /cf/conf/config.xml');

            $emit('info', 'Clearing system configuration cache...');
            $ssh->exec('rm -f /tmp/config.cache');

            $emit('info', 'Triggering pfSense dynamic configuration & rules reloader...');
            $ssh->exec('/etc/rc.reload_all');

            $emit('success', 'Rollback complete. Device is running the restored configuration.');

            return [
                'status'  => 'success',
                'ip'      => $ip,
                'message' => 'Rollback complete from ' . ($backup['file_name'] ?? 'stored backup') . '.',
            ];

        } catch (\Throwable $e) {
            return ['status' => 'failed', 'ip' => $ip, 'message' => 'Exception occurred: ' . $e->getMessage()];
        }
    }

    // ------------------------------------------------------------------ internals

    /**
     * Resolves IP/port/credentials for a device.
     *
     * @return array{values:array{0:string,1:int,2:string,3:string}, ip:string, error:?string}
     */
    private function resolveTarget(array $device): array
    {
        $status   = $this->statusModel->where('pf_id', $device['id'])->first();
        $ip       = !empty($device['man_ip']) ? $device['man_ip'] : (!empty($status['ip']) ? $status['ip'] : (!empty($device['ip']) ? $device['ip'] : ''));
        if (empty($ip)) {
            $dev = db_connect()->table('pf_devices')->where('TRIM(br_name)', trim($device['name'] ?? ''))->first();
            if ($dev && !empty($dev['lan_ip'])) {
                $ip = $dev['lan_ip'];
            } else {
                $loc = db_connect()->table('pf_locations')->where('id', $device['location'] ?? 0)->first();
                if ($loc && !empty($loc['ip'])) {
                    $ip = $loc['ip'];
                }
            }
        }
        $port     = !empty($status['ssh_port']) ? (int) $status['ssh_port'] : self::DEFAULT_SSH_PORT;
        $username = (string) ($device['username'] ?? '');
        $password = (string) ($device['password'] ?? '');

        $values = [$ip, $port, $username, $password];

        if (empty($ip)) {
            return ['values' => $values, 'ip' => '', 'error' => 'Failed to resolve management IP address.'];
        }

        if ($username === '' || $password === '') {
            return ['values' => $values, 'ip' => $ip, 'error' => 'Missing SSH credentials in device database record.'];
        }

        return ['values' => $values, 'ip' => $ip, 'error' => null];
    }

    /**
     * @return array{values:array{0:SSH2,1:SFTP}, error:?string}
     */
    private function open(string $ip, int $port, string $username, string $password, callable $emit): array
    {
        $ssh = new SSH2($ip, $port, self::CONNECT_TIMEOUT);
        if (!$ssh->login($username, $password)) {
            $emit('error', "Authentication failed for username '$username'. Please check credentials.");
            return ['values' => [], 'error' => "Authentication failed for username '$username'. Please check credentials."];
        }

        $emit('info', 'SSH Session Established. Connecting SFTP channel...');

        $sftp = new SFTP($ip, $port, self::CONNECT_TIMEOUT);
        if (!$sftp->login($username, $password)) {
            $emit('error', 'SFTP Login failed.');
            return ['values' => [], 'error' => 'SFTP Login failed.'];
        }

        return ['values' => [$ssh, $sftp], 'error' => null];
    }

    /**
     * Merges a fragment into the downloaded live configuration.
     *
     * The rules live in the file the device agent also uses, so the diff the operator approved and
     * this write cannot disagree about what "merge" means. All this method adds is the log.
     *
     * @return array{ok:bool, config:?string, message:string, changes:list<string>}
     */
    private function mergeFragment(string $live, string $fragment, callable $emit): array
    {
        $emit('info', 'Merging the fragment into the live configuration...');

        $merged = ConfigFragmentMerger::mergeForWrite($live, $fragment);

        if (!$merged['ok']) {
            $emit('error', 'Merge refused: ' . $merged['message']);

            return $merged;
        }

        foreach ($merged['changes'] as $change) {
            $emit('info', '  ' . $change);
        }

        foreach ($merged['notes'] as $note) {
            $emit('info', '  ' . $note);
        }

        return $merged;
    }

    /**
     * @return array{status:string, message:string}
     */
    private function pushXml(SSH2 $ssh, SFTP $sftp, string $payload, callable $emit): array
    {
        $emit('info', 'Uploading new configuration to /tmp/new_config.xml...');
        if (!$sftp->put('/tmp/new_config.xml', $payload)) {
            return ['status' => 'failed', 'message' => 'Failed to write config XML via SFTP.'];
        }

        $emit('info', 'Config uploaded. Overwriting active config `/cf/conf/config.xml`...');
        $ssh->exec('cp /tmp/new_config.xml /cf/conf/config.xml');

        $emit('info', 'Clearing system configuration cache...');
        $ssh->exec('rm -f /tmp/config.cache');

        $emit('info', 'Triggering pfSense dynamic configuration & rules reloader...');
        $ssh->exec('/etc/rc.reload_all');

        $emit('success', 'Configuration reloaded successfully. Device is ONLINE and running the new config!');

        return ['status' => 'success', 'message' => 'Configuration reloaded successfully. Device is ONLINE and running the new config!'];
    }

    /**
     * @return array{status:string, message:string}
     */
    private function runScript(SSH2 $ssh, SFTP $sftp, string $payload, callable $emit): array
    {
        $emit('info', 'Uploading custom shell script to /tmp/deploy_script.sh...');

        // Sanitize line endings for FreeBSD (strip \r)
        $cleanScript = str_replace("\r", '', $payload);

        if (!$sftp->put('/tmp/deploy_script.sh', $cleanScript)) {
            return ['status' => 'failed', 'message' => 'Failed to upload script via SFTP.'];
        }

        $ssh->exec('chmod +x /tmp/deploy_script.sh');
        $emit('info', 'Executing script on target device...');

        $ssh->setTimeout(self::EXEC_TIMEOUT);
        $buffer = '';
        $ssh->exec('/bin/sh -x /tmp/deploy_script.sh 2>&1', function ($str) use ($emit, &$buffer) {
            $buffer .= $str;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                if (trim($line) !== '') {
                    $emit('info', '> ' . trim($line));
                }
            }
        });
        if (trim($buffer) !== '') {
            $emit('info', '> ' . trim($buffer));
        }
        $ssh->setTimeout(0);

        $ssh->exec('rm -f /tmp/deploy_script.sh');
        $emit('success', 'Script executed successfully!');

        return ['status' => 'success', 'message' => 'Script executed successfully!'];
    }

    /**
     * Downloads the device's live /cf/conf/config.xml into pf_backups before anything is
     * overwritten, then prunes that device's folder with the shared retention policy.
     *
     * The downloaded contents are returned as well, because the merge path has to merge into exactly
     * the bytes that were snapshotted — merging into a separately-fetched copy could race with a
     * change made on the device in between, and the snapshot would then not describe what was replaced.
     *
     * @return array{ok:bool, id:?int, file:?string, message:string, contents:string}
     */
    private function saveSafetyBackup(array $device, string $deviceName, SFTP $sftp, string $batchId, callable $emit, string $prefix): array
    {
        $emit('info', 'Creating safety backup of /cf/conf/config.xml...');

        $contents = $sftp->get('/cf/conf/config.xml');
        if ($contents === false || trim($contents) === '') {
            $emit('error', 'Could not read /cf/conf/config.xml over SFTP.');
            return ['ok' => false, 'id' => null, 'file' => null, 'message' => 'Could not read /cf/conf/config.xml over SFTP.', 'contents' => ''];
        }

        // A truncated read is worse than no backup: refuse to treat it as restorable.
        libxml_use_internal_errors(true);
        if (simplexml_load_string($contents) === false) {
            $errors = [];
            foreach (libxml_get_errors() as $error) {
                $errors[] = trim($error->message);
            }
            libxml_clear_errors();
            $emit('error', 'Downloaded config.xml is not valid XML: ' . implode('; ', array_slice($errors, 0, 3)));
            return ['ok' => false, 'id' => null, 'file' => null, 'message' => 'Downloaded config.xml is not valid XML.', 'contents' => ''];
        }

        // Storing the artifact is shared with the device-agent transport, which uploads the same
        // file with its report instead of having pfCentral read it over SFTP.
        $stored = $this->snapshots->store($device, $deviceName, $contents, $batchId, $prefix);

        if (!$stored['ok']) {
            $emit('error', $stored['message']);
            return ['ok' => false, 'id' => null, 'file' => null, 'message' => $stored['message'], 'contents' => ''];
        }

        if ($stored['pruned'] > 0) {
            $emit('info', 'Retention: removed ' . $stored['pruned'] . ' aged-out snapshot(s) from this device folder.');
        }
        if ($stored['failed'] !== []) {
            log_message('warning', 'Could not delete aged-out backups for ' . $deviceName . ': ' . implode(', ', $stored['failed']));
        }

        $emit('success', 'Safety backup saved: ' . $stored['file'] . ($stored['id'] ? " (backup #{$stored['id']})" : ''));

        return ['ok' => true, 'id' => $stored['id'], 'file' => $stored['file'], 'message' => $stored['message'], 'contents' => $contents];
    }
}
