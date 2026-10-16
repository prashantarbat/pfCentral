<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CoreConfigModel;
use App\Models\AppGroupsModel;
use App\Models\PfInventoryModel;
use App\Models\ConfigJobModel;
use App\Models\ConfigPushLogModel;
use App\Models\JobTargetModel;
use App\Services\ConfigPush\ConfigDiffService;
use App\Services\ConfigPush\ConfigFragmentMerger;
use App\Services\ConfigPush\ConfigJobService;
use App\Services\ConfigPush\ConfigSnapshotStore;
use App\Services\ConfigPush\PushLogWriter;
use App\Services\ConfigPush\ConfigVerificationService;
use App\Services\ConfigPush\SshTransport;

class ConfigPushController extends BaseController
{
    protected $coreConfigModel;
    protected $appGroupsModel;
    protected $pfInventoryModel;
    protected $pushLogModel;
    protected $jobService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->coreConfigModel  = new CoreConfigModel();
        $this->appGroupsModel   = new AppGroupsModel();
        $this->pfInventoryModel = new PfInventoryModel();
        $this->pushLogModel     = new ConfigPushLogModel();
        $this->jobService       = new ConfigJobService();
    }

    public function index()
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        $appName = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';

        $data['title'] = strip_tags($appName) . ' 🔸 SSH Config Push Engine';
        $data['app_name'] = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url'] = $appUrl;

        $db = \Config\Database::connect();
        $builder = $db->table('pf_inventory')
            ->select('
                pf_inventory.id,
                pf_inventory.name,
                pf_inventory.man_ip,
                pf_inventory.active,
                pf_inventory.bypass,
                pf_inventory.groups,
                pf_inventory.location,
                COALESCE(
                    NULLIF(pf_inventory.man_ip, ""),
                    NULLIF(pf_status.ip, ""),
                    NULLIF(pf_devices.lan_ip, ""),
                    NULLIF(pf_locations.ip, ""),
                    "Dynamic IP"
                ) as resolved_ip,
                COALESCE(
                    NULLIF(pf_status.hostname, ""),
                    NULLIF(pf_devices.hostname, ""),
                    ""
                ) as hostname,
                pf_locations.name as location_name
            ')
            ->join('pf_status', 'pf_status.pf_id = pf_inventory.id', 'left')
            ->join('pf_devices', 'TRIM(pf_devices.br_name) = TRIM(pf_inventory.name)', 'left')
            ->join('pf_locations', 'pf_locations.id = pf_inventory.location', 'left')
            ->where('(pf_inventory.active = 1 OR pf_inventory.active = "1" OR pf_inventory.active IS NULL)')
            ->where('pf_inventory.name IS NOT NULL')
            ->where('TRIM(pf_inventory.name) !=', '');

        $builder->orderBy("CASE 
            WHEN pf_inventory.name LIKE '%0000%' THEN 2 
            WHEN pf_inventory.name LIKE '%9999%' THEN 3 
            WHEN CAST(TRIM(pf_inventory.name) AS UNSIGNED) > 0 THEN 0 
            ELSE 1 END", "ASC");
        $builder->orderBy("CAST(TRIM(pf_inventory.name) AS UNSIGNED)", "ASC");
        $builder->orderBy('TRIM(pf_inventory.name)', 'ASC');

        $data['devices'] = $builder->get()->getResultArray();
        $data['groups']  = $this->appGroupsModel->findAll();

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('config/push', $data)
            . view('frontend/footer', $data);
    }

    /**
     * Enqueues a deployment job. The device work happens in the job runner, not here.
     */
    public function deploy()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $targetType = $this->request->getPost('target_type');
        $targetId   = $this->request->getPost('target_id');

        $read = $this->readPayload();
        if ($read['error'] !== null) {
            return $this->response->setJSON(['status' => 'error', 'message' => $read['error']]);
        }

        $payload     = (string) $read['payload'];
        $payloadType = $read['type'];

        $resolved = $this->resolveTargets((string) $targetType, $targetId);
        if ($resolved['error'] !== null) {
            return $this->response->setJSON(['status' => 'error', 'message' => $resolved['error']]);
        }

        // How long this change is watched for. Refused rather than normalised when it is not a profile we
        // offer: a typo in "watched for a day" quietly becoming the default hour is the kind of downgrade a
        // safety setting must not have, and the form sends a fixed list, so an unknown value is hand-made.
        $watchProfile = $this->request->getPost('watch_profile');
        $watchProfile = $watchProfile !== null ? (string) $watchProfile : ConfigVerificationService::DEFAULT_WATCH_PROFILE;

        if (!ConfigVerificationService::isWatchProfile($watchProfile)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Unknown verification window "' . $watchProfile . '". Choose one of: '
                    . implode(', ', array_keys(ConfigVerificationService::WATCH_PROFILES)) . '.',
            ]);
        }

        try {
            $jobId = $this->jobService->createJob([
                'payload'      => $payload,
                'payload_type' => $payloadType,
                'target_spec'  => $resolved['spec'],
                'requested_by' => $user->id ?? null,
                'canary'       => filter_var($this->request->getPost('canary'), FILTER_VALIDATE_BOOLEAN),
                'canary_pf_id' => $this->request->getPost('canary_pf_id'),
                'watch_profile' => $watchProfile,
            ], $resolved['devices']);
        } catch (\Throwable $e) {
            log_message('error', 'Config job creation failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Could not queue the deployment: ' . $e->getMessage(),
            ]);
        }

        $state         = $this->jobService->jobStatus($jobId);
        $needsApproval = (int) ($state['job']['requires_approval'] ?? 0) === 1;
        $canaryPfId    = $state['job']['canary_pf_id'] ?? null;

        // An unapproved job must not be dispatched — the runner would only refuse it.
        $dispatched = $needsApproval ? false : $this->jobService->dispatchRunner($jobId);

        $message = 'Job #' . $jobId . ' queued for ' . count($resolved['devices']) . ' device(s).';

        if ($read['mode'] === ConfigJobModel::MODE_MERGE) {
            $message .= ' This is a section fragment: it will be merged into each device and can only add or update settings, never remove them.';
        }

        if ($canaryPfId) {
            $message .= ' Canary: one device runs first and the rest wait for it.';
        }

        // Said out loud, because it is a property of *this* job: an operator who asked for a day of watching
        // should see that they got it, and one who pushed a script should see that nothing will be watched.
        $message .= ' Verification window: ' . ConfigVerificationService::watchProfileLabel(
            $read['type'] === 'script' ? ConfigVerificationService::WATCH_PROFILE_NONE : $watchProfile
        ) . '.';

        if ($needsApproval) {
            $reasons = [];
            if ($payloadType === 'script') {
                $reasons[] = 'script payload';
            }
            if (($resolved['spec']['type'] ?? '') === 'all') {
                $reasons[] = 'fleet-wide target';
            }

            $message .= ' Held for approval (' . implode(' + ', $reasons)
                . '): a second administrator must approve it before it can run.';
        } elseif (!$dispatched) {
            $message .= ' Waiting for the `jobs:run` runner (cron).';
        }

        return $this->response->setJSON([
            'status'            => 'success',
            'job_id'            => $jobId,
            'targets'           => count($resolved['devices']),
            'dispatched'        => $dispatched,
            'requires_approval' => $needsApproval,
            'canary_pf_id'      => $canaryPfId,
            'message'           => $message,
        ]);
    }

    /**
     * Reports what a deployment *would* change on each target, without queueing anything.
     *
     * Deliberately creates no job and touches no device: it compares the posted payload against
     * each target's most recent stored snapshot. It is advisory — a missing snapshot is a warning,
     * not a refusal — because the SSH transport takes a fresh backup at push time, and a preview
     * that blocked a deployment would be worse than no preview.
     */
    public function preview()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $read = $this->readPayload();
        if ($read['error'] !== null) {
            return $this->response->setJSON(['status' => 'error', 'message' => $read['error']]);
        }

        $resolved = $this->resolveTargets((string) $this->request->getPost('target_type'), $this->request->getPost('target_id'));
        if ($resolved['error'] !== null) {
            return $this->response->setJSON(['status' => 'error', 'message' => $resolved['error']]);
        }

        $diffService = new ConfigDiffService();
        $devices     = [];

        foreach ($resolved['devices'] as $device) {
            $devices[] = $diffService->previewForDevice($device, (string) $read['payload'], $read['type']);
        }

        // Previewing the gate matters: an operator should learn that a fleet-wide change needs a
        // second signature while they are still looking at the diff, not after they click Deploy.
        $needsApproval = ConfigJobService::requiresApproval((string) $read['type'], $resolved['spec']);
        $reasons       = [];

        if ($read['type'] === 'script') {
            $reasons[] = 'script payload';
        }
        if (($resolved['spec']['type'] ?? '') === 'all') {
            $reasons[] = 'fleet-wide target';
        }

        $tally = ['identical' => 0, 'changed' => 0, 'conflict' => 0, 'no-snapshot' => 0, 'error' => 0, 'not-applicable' => 0];
        $totals = ['added' => 0, 'removed' => 0, 'changed' => 0, 'filtered' => 0];

        foreach ($devices as $device) {
            $tally[$device['status']] = ($tally[$device['status']] ?? 0) + 1;
            foreach (['added', 'removed', 'changed', 'filtered'] as $key) {
                $totals[$key] += (int) $device['totals'][$key];
            }
        }

        return $this->response->setJSON([
            'status'            => 'success',
            'targets'           => count($devices),
            'payload_mode'      => $read['mode'],
            'target_spec'       => $resolved['spec'],
            'devices'           => $devices,
            'tally'             => $tally,
            'totals'            => $totals,
            'requires_approval' => $needsApproval,
            'approval_reasons'  => $reasons,
            'message'           => 'Previewed against stored snapshots. Nothing was queued or sent.',
        ]);
    }

    /**
     * Reads and validates the posted payload, so deploy and preview cannot disagree about what a
     * payload is — or about what kind of change it represents.
     *
     * Two shapes are accepted and they mean different things, so the mode is returned rather than
     * re-derived at each use site:
     *
     *   - a complete `<pfsense>` document is installed over the device's configuration;
     *   - any other single element is a *section fragment*, merged into the section of that name on
     *     the device, additively and without ever deleting anything.
     *
     * Both are safe to accept because both transports can now perform them honestly: the SSH path
     * merges on pfCentral side against the live config it just downloaded, and a device agent old
     * enough to install a fragment verbatim is refused the job and takes it over SSH instead.
     *
     * @return array{payload:?string, type:string, mode:string, error:?string}
     */
    private function readPayload(): array
    {
        $payloadType = (string) ($this->request->getPost('payload_type') ?? 'xml');
        $fallback    = ['payload' => null, 'type' => $payloadType, 'mode' => ConfigJobModel::MODE_REPLACE];

        if ($payloadType === 'script') {
            $payload = (string) $this->request->getPost('config_script');
            if (trim($payload) === '') {
                return $fallback + ['error' => 'Shell script payload cannot be empty.'];
            }

            return ['payload' => $payload, 'type' => $payloadType, 'mode' => ConfigJobModel::MODE_REPLACE, 'error' => null];
        }

        $payload = (string) $this->request->getPost('config_xml');
        if (trim($payload) === '') {
            return $fallback + ['error' => 'Configuration XML cannot be empty.'];
        }

        // Malformed XML is rejected before it can reach the queue.
        $problem = ConfigSnapshotStore::validate($payload);
        if ($problem !== null) {
            return $fallback + ['error' => 'Invalid configuration payload: ' . $problem];
        }

        // Derived from the document itself, never from a submitted flag, so a recorded mode can never
        // disagree with the bytes a device will receive.
        $mode = ConfigFragmentMerger::mode($payload) ?? ConfigJobModel::MODE_REPLACE;

        return ['payload' => $payload, 'type' => $payloadType, 'mode' => $mode, 'error' => null];
    }

    /**
     * Second-approver sign-off for a job held by the approval gate.
     */
    public function approve()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $jobId = (int) $this->request->getPost('job_id');
        if ($jobId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No job was specified.']);
        }

        try {
            $result = $this->jobService->approve($jobId, (int) ($user->id ?? 0));
        } catch (\Throwable $e) {
            log_message('error', 'Config job approval failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'Could not approve the job.']);
        }

        if (!$result['ok']) {
            return $this->response->setStatusCode(409)->setJSON(['status' => 'error', 'message' => $result['message']]);
        }

        $dispatched = $this->jobService->dispatchRunner($jobId);

        return $this->response->setJSON([
            'status'     => 'success',
            'job_id'     => $jobId,
            'dispatched' => $dispatched,
            'message'    => $result['message'] . ($dispatched ? '' : ' Waiting for the `jobs:run` runner (cron).'),
        ]);
    }

    /**
     * Abandons a job that has not started fanning out.
     */
    public function cancel()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $jobId = (int) $this->request->getPost('job_id');
        if ($jobId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No job was specified.']);
        }

        try {
            $result = $this->jobService->cancel($jobId, (int) ($user->id ?? 0));
        } catch (\Throwable $e) {
            log_message('error', 'Config job cancel failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'Could not cancel the job.']);
        }

        if (!$result['ok']) {
            return $this->response->setStatusCode(409)->setJSON(['status' => 'error', 'message' => $result['message']]);
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'job_id'  => $jobId,
            'message' => $result['message'],
        ]);
    }

    /**
     * Jobs held by the approval gate plus recent jobs, for the queue panel.
     */
    public function jobs()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        try {
            $approvals = $this->jobService->pendingApprovals(25);
            $recent    = db_connect()
                ->table('config_jobs')
                ->select('id, payload_type, target_spec, status, requires_approval, requested_by, approved_by, canary_pf_id, created_at, started_at, finished_at, last_error')
                ->orderBy('id', 'DESC')
                ->limit(25)
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Config job queue read failed: ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error', 'message' => 'The job queue is unavailable.', 'approvals' => [], 'jobs' => []]);
        }

        return $this->response->setJSON([
            'status'                => 'success',
            'approvals'             => $approvals,
            'jobs'                  => $recent,
            'current_user_id'       => (int) ($user->id ?? 0),
            'self_approval_allowed' => ConfigJobService::selfApprovalAllowed(),
        ]);
    }

    /**
     * Live job state for the console view. Polled by the UI; the deployment itself is no
     * longer tied to this request.
     */
    public function job($id = null)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $jobId = (int) $id;
        if ($jobId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No job specified.']);
        }

        try {
            $state = $this->jobService->jobStatus($jobId);
        } catch (\Throwable $e) {
            log_message('error', 'Config job status read failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'Job state is unavailable.']);
        }

        if ($state === null) {
            return $this->response->setJSON(['status' => 'error', 'message' => "Job #{$jobId} was not found."]);
        }

        return $this->response->setJSON(array_merge(['status' => 'success'], $state));
    }

    /**
     * Recent persisted per-device deploy results (drives the history panel + rollback).
     */
    public function history()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized access.']);
        }

        try {
            $rows = $this->pushLogModel->orderBy('id', 'DESC')->limit(25)->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'config_push_logs read failed: ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error', 'message' => 'Deploy history is unavailable.', 'data' => []]);
        }

        return $this->response->setJSON(['status' => 'success', 'data' => $rows]);
    }

    /**
     * Restores a device from one of its stored pre-push snapshots.
     */
    public function rollback()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $logId = (int) $this->request->getPost('log_id');
        if ($logId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No deployment row was specified.']);
        }

        try {
            $log = $this->pushLogModel->find($logId);
        } catch (\Throwable $e) {
            log_message('error', 'config_push_logs read failed during rollback: ' . $e->getMessage());

            return $this->response->setJSON(['status' => 'error', 'message' => 'Deploy history is unavailable.']);
        }

        if (!$log) {
            return $this->response->setJSON(['status' => 'error', 'message' => "Deployment #{$logId} was not found."]);
        }

        $device = $this->pfInventoryModel->find($log['pf_id']);
        if (!$device) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'That device no longer exists in inventory.']);
        }

        $backup = $this->resolveBackup($log, $device);
        if (!$backup['ok']) {
            return $this->response->setJSON(['status' => 'error', 'message' => $backup['message']]);
        }

        $deviceName = $device['name'] ?? ('device #' . $device['id']);

        $logs = [];
        $emit = function ($status, $message) use (&$logs) {
            $logs[] = ['status' => $status, 'message' => $message];
        };

        $batchId   = ConfigJobService::uuid();
        $startedAt = microtime(true);

        $logWriter = new PushLogWriter();
        $logRowId  = $logWriter->start(
            $batchId,
            $device,
            $deviceName,
            'rollback',
            'rollback',
            isset($user->id) ? (int) $user->id : null,
            'Rollback to ' . $backup['file_name'] . '.'
        );

        try {
            $result = (new SshTransport())->restore($device, $backup, $emit);
        } catch (\Throwable $e) {
            $result = ['status' => 'failed', 'message' => 'Unhandled error: ' . $e->getMessage()];
            $emit('error', $result['message']);
        }

        $deviceLog = array_map(static function ($line) {
            return '[' . date('H:i:s') . '] ' . strtoupper($line['status']) . ': ' . $line['message'];
        }, $logs);

        $logWriter->finish($logRowId, [
            'status'      => $result['status'] ?? 'failed',
            'message'     => $result['message'] ?? 'Unknown result.',
            'ip'          => $result['ip'] ?? null,
            'backup_id'   => $backup['id'] ?? null,
            'backup_file' => $backup['file_name'] ?? null,
            'log'         => implode("\n", $deviceLog),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $this->response->setJSON([
            'status'      => $result['status'] ?? 'failed',
            'message'     => $result['message'] ?? 'Unknown result.',
            'device'      => $deviceName,
            'backup_file' => $backup['file_name'] ?? null,
            'log'         => $logs,
        ]);
    }

    /**
     * Resolves the target selector into device rows plus the spec recorded on the job.
     *
     * @return array{devices:list<array>, spec:array, error:?string}
     */
    private function resolveTargets(string $targetType, $targetId): array
    {
        $db = \Config\Database::connect();
        $targetQuery = function($b) {
            return $b->select('
                pf_inventory.*,
                COALESCE(
                    NULLIF(pf_inventory.man_ip, ""),
                    NULLIF(pf_status.ip, ""),
                    NULLIF(pf_devices.lan_ip, ""),
                    NULLIF(pf_locations.ip, ""),
                    ""
                ) as ip
            ')
            ->join('pf_status', 'pf_status.pf_id = pf_inventory.id', 'left')
            ->join('pf_devices', 'TRIM(pf_devices.br_name) = TRIM(pf_inventory.name)', 'left')
            ->join('pf_locations', 'pf_locations.id = pf_inventory.location', 'left');
        };

        if ($targetType === 'single') {
            $device = $targetQuery($db->table('pf_inventory'))->where('pf_inventory.id', $targetId)->get()->getRowArray();
            if (!$device) {
                return ['devices' => [], 'spec' => [], 'error' => "Device ID $targetId not found."];
            }

            return ['devices' => [$device], 'spec' => ['type' => 'single', 'id' => (int) $targetId], 'error' => null];
        }

        if ($targetType === 'group') {
            $group = $this->appGroupsModel->find($targetId);
            if (!$group) {
                return ['devices' => [], 'spec' => [], 'error' => "Device Group ID $targetId not found."];
            }

            $devices = $targetQuery($db->table('pf_inventory'))
                ->where('pf_inventory.groups', $group['name'])
                ->where('(pf_inventory.active = 1 OR pf_inventory.active = "1" OR pf_inventory.active IS NULL)')
                ->where('pf_inventory.name IS NOT NULL')
                ->where('TRIM(pf_inventory.name) !=', '')
                ->get()
                ->getResultArray();

            if ($devices === []) {
                return ['devices' => [], 'spec' => [], 'error' => "No active devices found in group '{$group['name']}'."];
            }

            return [
                'devices' => $devices,
                'spec'    => ['type' => 'group', 'id' => (int) $targetId, 'name' => $group['name']],
                'error'   => null,
            ];
        }

        if ($targetType === 'all') {
            $devices = $targetQuery($db->table('pf_inventory'))
                ->where('(pf_inventory.active = 1 OR pf_inventory.active = "1" OR pf_inventory.active IS NULL)')
                ->where('pf_inventory.name IS NOT NULL')
                ->where('TRIM(pf_inventory.name) !=', '')
                ->get()
                ->getResultArray();

            if ($devices === []) {
                return ['devices' => [], 'spec' => [], 'error' => 'No active devices found in inventory.'];
            }

            return ['devices' => $devices, 'spec' => ['type' => 'all'], 'error' => null];
        }

        return ['devices' => [], 'spec' => [], 'error' => 'Invalid target type specified.'];
    }

    /**
     * Locates and validates the snapshot a rollback should restore.
     *
     * The lookup itself lives in `ConfigSnapshotStore::resolve()` because the queued rollback the
     * verification window can now raise needs exactly the same answer: which artifact, on disk, is the
     * pre-change configuration for this device. Two implementations of "find that snapshot" is two
     * places for the fallback path to fall out of step — and the one that drifted would be the one that
     * handed a device the wrong file.
     *
     * @return array{ok:bool, id:?int, file_name:?string, path:?string, message:string}
     */
    private function resolveBackup(array $log, array $device): array
    {
        $resolved = (new ConfigSnapshotStore())->resolve($device, $log['backup_id'] ?? null, $log['backup_file'] ?? null);

        return [
            'ok'        => $resolved['ok'],
            'id'        => $resolved['id'],
            'file_name' => $resolved['file'],
            'path'      => $resolved['path'],
            'message'   => $resolved['message'],
        ];
    }
}
