<?php

namespace App\Services\ConfigPush;

use App\Models\ConfigJobModel;
use App\Models\JobTargetModel;
use App\Models\PfInventoryModel;

/**
 * Executes queued config jobs.
 *
 * This is the piece that no longer lives in the web request: a job is enqueued by the
 * controller and worked off here, either by the `jobs:run` spark command on cron, by the
 * best-effort background dispatch, or manually. Because every device step is a durable
 * job_targets row, an interrupted run resumes instead of leaving the fleet half-configured.
 *
 * Concurrency: a target is claimed with a conditional UPDATE, so two runners cannot work
 * the same device at once, and a target left in 'running' by a crashed process is only
 * reclaimed once it looks stalled.
 */
class ConfigJobRunner
{
    public const MAX_ATTEMPTS  = 3;
    private const STALE_AFTER  = 120;        // seconds before a runner-owned in-flight target is reclaimable
    private const AGENT_RECLAIM_AFTER = 900; // longer for agent-owned work: applying a config is slow,
                                             // and the device's polls keep the row fresh while it works

    protected ConfigJobModel $jobModel;
    protected JobTargetModel $targetModel;
    protected PfInventoryModel $inventoryModel;
    protected PushLogWriter $logWriter;
    protected ConfigJobService $jobService;
    protected ConfigVerificationService $verifier;

    /** @var ConfigTransportInterface[] ordered by preference */
    protected array $transports;

    public function __construct(
        ?ConfigJobModel $jobModel = null,
        ?JobTargetModel $targetModel = null,
        ?PfInventoryModel $inventoryModel = null,
        ?PushLogWriter $logWriter = null,
        ?ConfigJobService $jobService = null,
        ?array $transports = null,
        ?ConfigVerificationService $verifier = null
    ) {
        $this->verifier       = $verifier ?? new ConfigVerificationService();
        $this->jobModel       = $jobModel ?? new ConfigJobModel();
        $this->targetModel    = $targetModel ?? new JobTargetModel();
        $this->inventoryModel = $inventoryModel ?? new PfInventoryModel();
        $this->logWriter      = $logWriter ?? new PushLogWriter();
        $this->jobService     = $jobService ?? new ConfigJobService();
        // Agent first, SSH as the fallback. A device that is enrolled and polling gets its work
        // delivered by its own pickup; a silent device falls through to SSH, which is the tier
        // order this design wants (never fall *forward* to pushing at a device that is up).
        $this->transports     = $transports ?? [new AgentTransport(), new SshTransport()];
    }

    /**
     * @param int|null $jobId run one job, or sweep open jobs when null
     * @param int      $limit how many jobs to sweep
     * @param callable|null $emit function (string $status, string $message): void
     *
     * @return array{jobs:int, applied:int, failed:int}
     */
    public function run(?int $jobId = null, int $limit = 5, ?callable $emit = null): array
    {
        $emit ??= static function (string $status, string $message) {
        };

        $summary = ['jobs' => 0, 'applied' => 0, 'failed' => 0];

        $jobs = $this->openJobs($jobId, $limit);
        if ($jobs === []) {
            $emit('info', 'No open config jobs.');
            return $summary;
        }

        foreach ($jobs as $job) {
            $summary['jobs']++;
            $result = $this->runJob($job, $emit);
            $summary['applied'] += $result['applied'];
            $summary['failed'] += $result['failed'];
        }

        return $summary;
    }

    /**
     * @return list<array>
     */
    private function openJobs(?int $jobId, int $limit): array
    {
        if ($jobId !== null) {
            $job = $this->jobModel->find($jobId);

            return ($job && $this->isRunnable($job)) ? [$job] : [];
        }

        $jobs = $this->jobModel
            ->whereIn('status', ConfigJobModel::RUNNABLE_STATUSES)
            ->orderBy('id', 'ASC')
            ->limit($limit)
            ->findAll();

        return array_values(array_filter($jobs, fn ($job) => $this->isRunnable($job)));
    }

    /**
     * A job may only be worked once it has cleared its approval gate.
     *
     * 'awaiting_approval' already keeps unapproved jobs out by construction; this is the
     * belt-and-braces check that still holds if a future query is written loosely (and it
     * covers the explicit `--job=<id>` path, which bypasses the status filter entirely).
     */
    private function isRunnable(array $job): bool
    {
        if (!in_array($job['status'] ?? '', ConfigJobModel::RUNNABLE_STATUSES, true)) {
            return false;
        }

        if ((int) ($job['requires_approval'] ?? 0) === 1 && empty($job['approved_by'])) {
            return false;
        }

        return true;
    }

    /**
     * How long a target owned by this device may sit in flight before the runner reclaims it.
     *
     * An agent-owned change is applied on the branch and reported back on a later poll, which is
     * measured in minutes, not seconds — reclaiming at the SSH pace would have a runner and a device
     * both applying the same config.
     */
    private function reclaimWindow(?array $device): int
    {
        return ($device !== null && $this->isAgentOnline($device)) ? self::AGENT_RECLAIM_AFTER : self::STALE_AFTER;
    }

    /**
     * Whether an agent transport can reach this device right now (enrolled and polling).
     */
    private function isAgentOnline(array $device): bool
    {
        foreach ($this->transports as $transport) {
            if ($transport instanceof AgentTransport) {
                return $transport->supports($device);
            }
        }

        return false;
    }

    /**
     * @return array{applied:int, failed:int}
     */
    private function runJob(array $job, callable $emit): array
    {
        $jobId   = (int) $job['id'];
        $applied = 0;
        $failed  = 0;

        $emit('info', "Job #{$jobId} ({$job['payload_type']}) starting.");

        $this->jobModel->update($jobId, [
            'status'     => ConfigJobModel::STATUS_RUNNING,
            'started_at' => $job['started_at'] ?: date('Y-m-d H:i:s'),
        ]);

        // Canary first: until the one pilot device proves the change, nothing else is touched.
        $canary = $this->runCanaryPhase($job, $emit);

        if ($canary === 'hold') {
            $emit('info', "Job #{$jobId}: canary device still in flight — the rest of the fleet stays held.");
            return ['applied' => 0, 'failed' => 0];
        }

        if ($canary === 'halt') {
            $status = $this->jobService->refreshJobStatus($jobId);
            $emit('error', "Job #{$jobId} halted at the canary stage (status '{$status}').");
            return ['applied' => 0, 'failed' => 1];
        }

        $targets = $this->targetModel
            ->where('job_id', $jobId)
            ->whereIn('status', JobTargetModel::OPEN_STATUSES)
            ->orderBy('id', 'ASC')
            ->findAll();

        foreach ($targets as $target) {
            $device     = $this->inventoryModel->find($target['pf_id']);
            $deviceName = $device['name'] ?? ('device #' . $target['pf_id']);

            if (!$this->claimTarget($target, $this->reclaimWindow($device))) {
                if (($target['status'] ?? '') === JobTargetModel::STATUS_DELIVERED) {
                    $emit('info', "  [{$deviceName}] already armed for the device agent; waiting for its report.");
                } else {
                    $emit('info', "  [{$deviceName}] not claimed (already in progress, or attempt limit reached).");
                }
                continue;
            }

            if ($device === null) {
                $this->closeTarget((int) $target['id'], [
                    'status' => JobTargetModel::STATUS_FAILED,
                    'error'  => 'Device no longer exists in inventory.',
                ]);
                $emit('error', "  [{$deviceName}] device no longer exists in inventory.");
                $failed++;
                continue;
            }

            $outcome = $this->processTarget($job, $target, $device, $emit);

            if ($outcome === 'applied') {
                $applied++;
            } elseif ($outcome === 'failed') {
                $failed++;
            }
            // 'pending' (armed for a device agent) counts as neither: the job stays open.
        }

        $status = $this->jobService->refreshJobStatus($jobId);
        $emit('info', "Job #{$jobId} finished with status '{$status}' (applied {$applied}, failed {$failed}).");

        return ['applied' => $applied, 'failed' => $failed];
    }

    /**
     * Runs the pilot device ahead of the fleet.
     *
     * @return 'proceed' the fleet may be worked (no canary, or the canary already succeeded)
     * @return 'hold'    wait: the canary is queued elsewhere, or delivered but unconfirmed
     * @return 'halt'    the canary failed; nothing else may be touched
     */
    private function runCanaryPhase(array $job, callable $emit): string
    {
        $jobId      = (int) $job['id'];
        $canaryPfId = isset($job['canary_pf_id']) ? (int) $job['canary_pf_id'] : 0;

        if ($canaryPfId <= 0) {
            return 'proceed';
        }

        $canary = $this->targetModel
            ->where('job_id', $jobId)
            ->where('pf_id', $canaryPfId)
            ->first();

        if ($canary === null) {
            $emit('info', "Job #{$jobId}: canary device #{$canaryPfId} is no longer a target — proceeding with the fleet.");
            return 'proceed';
        }        if (!in_array($canary['status'] ?? '', JobTargetModel::OPEN_STATUSES, true)) {
            // Applied is the only closed state that lets the fleet proceed.
            return ($canary['status'] ?? '') === JobTargetModel::STATUS_APPLIED ? 'proceed' : 'halt';
        }

        $device     = $this->inventoryModel->find($canary['pf_id']);
        $deviceName = $device['name'] ?? ('device #' . $canary['pf_id']);

        $emit('info', "Job #{$jobId}: canary phase — trying [{$deviceName}] first; the rest hold until it succeeds.");

        // 'delivered' is open, so a canary that has been armed and is waiting for its device to
        // collect it lands here: the claim fails and the fleet holds, which is exactly right.
        if (!$this->claimTarget($canary, $this->reclaimWindow($device))) {
            // Another runner owns the canary right now. Fanning out here would defeat the
            // entire point of the canary, so hold instead.
            $emit('info', "  [{$deviceName}] canary is in flight elsewhere — the fleet stays held.");
            return 'hold';
        }

        if ($device === null) {
            $this->closeTarget((int) $canary['id'], [
                'status' => JobTargetModel::STATUS_FAILED,
                'error'  => 'Device no longer exists in inventory.',
            ]);
            $emit('error', "  [{$deviceName}] canary device no longer exists in inventory.");

            return 'halt';
        }

        $outcome = $this->processTarget($job, $canary, $device, $emit);

        if ($outcome === 'applied') {
            $emit('success', "Job #{$jobId}: canary succeeded on [{$deviceName}] — fanning out to the remaining device(s).");

            return 'proceed';
        }

        if ($outcome === 'pending') {
            // Canary armed for the device; hold the fleet until it reports.
            $emit('info', "Job #{$jobId}: canary armed for [{$deviceName}] — the fleet stays held until it reports.");

            return 'hold';
        }

        // Halting also un-arms anything already handed to a device agent, so a failed canary
        // cannot be followed by a fleet-wide rollout that was queued before it failed.
        $untouched = $this->jobService->skipUntouchedTargets($jobId, 'Canary device failed; rollout halted before fan-out.');
        $emit('error', "Job #{$jobId}: canary failed on [{$deviceName}] — rollout halted, {$untouched} device(s) left untouched.");

        return 'halt';
    }

    /**
     * @return 'applied'|'failed'|'pending' — 'pending' means handed to a device agent and awaiting
     *                                   its report, so the target deliberately stays open
     */
    private function processTarget(array $job, array $target, array $device, callable $emit): string
    {
        $deviceName = $device['name'] ?? ('device #' . $target['pf_id']);
        $startedAt  = microtime(true);
        $lines      = [];

        $emitLine = function (string $status, string $message) use ($emit, $deviceName, &$lines) {
            $lines[] = '[' . date('H:i:s') . '] ' . strtoupper($status) . ': ' . $message;
            $emit($status, "  [{$deviceName}] {$message}");
        };

        $logId = $this->logWriter->start(
            (string) $job['batch_id'],
            $device,
            $deviceName,
            $job['target_spec']['type'] ?? null,
            (string) $job['payload_type'],
            isset($job['requested_by']) ? (int) $job['requested_by'] : null,
            'Deployment started.'
        );

        $transport = $this->pickTransport($device, $job);

        if ($transport === null) {
            $result = ['status' => 'failed', 'message' => 'No transport can reach this device (missing credentials?).'];
            $emitLine('error', $result['message']);
        } else {
            $emitLine('info', 'Using ' . $transport->name() . ' transport.');

            try {
                $result = $transport->deliver($device, [
                    'id'           => (int) $job['id'],
                    'target_id'    => (int) $target['id'],
                    'batch_id'     => (string) $job['batch_id'],
                    'payload'      => (string) $job['rendered_payload'],
                    'payload_type' => (string) $job['payload_type'],
                    'payload_mode' => (string) ($job['payload_mode'] ?? 'replace'),
                ], $emitLine);
            } catch (\Throwable $e) {
                $result = ['status' => 'failed', 'message' => 'Unhandled error: ' . $e->getMessage()];
                $emitLine('error', $result['message']);
            }
        }

        $outcome    = (string) ($result['status'] ?? 'failed');
        $message    = (string) ($result['message'] ?? '');
        $now        = date('Y-m-d H:i:s');
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($outcome === 'pending') {
            // Asynchronous handover. The target stays open so the job keeps running and the device's
            // report — not this attempt — decides the outcome. The audit row is closed as
            // 'delivered' and the device writes its own row when it reports.
            $this->closeTarget((int) $target['id'], [
                'status'       => JobTargetModel::STATUS_DELIVERED,
                'delivered_at' => $now,
                'result_log'   => implode("\n", $lines),
                'error'        => null,
                'duration_ms'  => $durationMs,
            ]);

            $this->logWriter->finish($logId, [
                'status'      => 'delivered',
                'message'     => $message,
                'ip'          => $result['ip'] ?? null,
                'log'         => implode("\n", $lines),
                'duration_ms' => $durationMs,
            ]);

            return 'pending';
        }

        $applied = $outcome === 'success';

        $this->closeTarget((int) $target['id'], [
            'status'       => $applied ? JobTargetModel::STATUS_APPLIED : JobTargetModel::STATUS_FAILED,
            'delivered_at' => $now,
            'applied_at'   => $applied ? $now : null,
            'result_log'   => implode("\n", $lines),
            'backup_id'    => $result['backup_id'] ?? null,
            'backup_file'  => $result['backup_file'] ?? null,
            'error'        => $applied ? null : mb_substr($message, 0, 500),
            'duration_ms'  => $durationMs,
        ]);

        $this->logWriter->finish($logId, [
            'status'      => $applied ? 'success' : 'failed',
            'message'     => $message,
            'ip'          => $result['ip'] ?? null,
            'backup_id'   => $result['backup_id'] ?? null,
            'backup_file' => $result['backup_file'] ?? null,
            'log'         => implode("\n", $lines),
            'duration_ms' => $durationMs,
        ]);

        // Last, and deliberately after the outcome is already recorded: verification is a second
        // opinion about an applied change, never a condition on it. If it fails, or the device is
        // unreachable, the deployment stays 'applied' — the push did what it was asked.
        if ($applied) {
            // The window opens here, on the *apply*, not on a verdict: the read-back below may fail,
            // and a branch that was unreachable seconds after a push is precisely the one worth asking
            // again in five minutes. If that read finds drift, `record()` closes the window again, so a
            // change that reverted instantly is not re-read four more times for nothing.
            $this->verifier->startWatch((int) $target['id'], $job, $device);

            $this->verifyTarget($job, $target, $device, $transport, $emitLine);
        }

        return $applied ? 'applied' : 'failed';
    }

    /**
     * Reads the device back and records whether the change is what is actually running.
     *
     * @param ConfigTransportInterface|null $transport the transport that just applied it
     */
    private function verifyTarget(array $job, array $target, array $device, ?ConfigTransportInterface $transport, callable $emitLine): void
    {
        try {
            // The transport that just delivered the change is asked first, because it is known to be
            // able to talk to this device. The rest follow: a transport that cannot read at all (the
            // agent channel) declines, which is what lets the readers that can still be reached.
            $readers = $transport === null ? null : array_merge(
                [$transport],
                array_values(array_filter($this->transports, static fn ($candidate) => $candidate !== $transport))
            );

            $outcome = $this->verifier->verifyDevice($target, $job, $device, $readers);
        } catch (\Throwable $e) {
            log_message('error', 'Post-apply verification failed for target #' . ($target['id'] ?? '?') . ': ' . $e->getMessage());

            return;
        }

        $status = $outcome['status'];

        if ($status === JobTargetModel::VERIFY_MATCHED) {
            $emitLine('success', 'Verified: ' . $outcome['detail']);

            return;
        }

        if ($status === JobTargetModel::VERIFY_NOT_APPLICABLE) {
            return;
        }

        $emitLine('error', 'Verification — ' . $outcome['detail']);

        // Say what happened to the alert as well. Drift discovered during a push is the one an operator
        // is looking at right now, so "and nobody has been told" belongs on the same console rather than
        // waiting to be noticed on a dashboard later.
        $alert = $outcome['alert'] ?? null;

        if (is_array($alert) && $alert['action'] === 'raised') {
            $emitLine('info', 'Drift alert raised: ' . $alert['message']);
        }
    }

    /**
     * Atomically takes ownership of a target.
     *
     * A queued row is claimed outright. A row left in flight by a crashed process — or by a device
     * that never reported back — is only reclaimed once it has gone stale, so two live runners
     * cannot fight over a device, and a runner cannot trample work a branch is still doing.
     */
    private function claimTarget(array $target, int $staleAfter = self::STALE_AFTER): bool
    {
        $id       = (int) $target['id'];
        $attempts = (int) ($target['attempts'] ?? 0);
        $now      = date('Y-m-d H:i:s');

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->closeTarget($id, [
                'status' => JobTargetModel::STATUS_FAILED,
                'error'  => 'Abandoned after ' . self::MAX_ATTEMPTS . ' interrupted attempts.',
            ]);

            return false;
        }

        $db = db_connect();

        // Branch on the row's status, not its attempt count: a queued row is waiting to be
        // taken whatever its history, while a 'running' row belongs to a live process and is
        // only reclaimable once it has gone quiet. Keying this on attempts instead would
        // strand any queued row that had already been attempted.
        if (($target['status'] ?? '') === JobTargetModel::STATUS_QUEUED) {
            $db->query(
                'UPDATE job_targets SET status = ?, attempts = attempts + 1, updated_at = ? WHERE id = ? AND status = ?',
                [JobTargetModel::STATUS_RUNNING, $now, $id, JobTargetModel::STATUS_QUEUED]
            );

            return $db->affectedRows() === 1;
        }

        // In-flight rows are reclaimable only once they have gone quiet: 'running' means a runner or
        // a device is applying the change, 'delivered' means it is armed for a device to collect.
        $staleBefore = date('Y-m-d H:i:s', time() - $staleAfter);
        $db->query(
            'UPDATE job_targets SET status = ?, attempts = attempts + 1, updated_at = ? WHERE id = ? AND status IN (?, ?) AND (updated_at IS NULL OR updated_at < ?)',
            [JobTargetModel::STATUS_RUNNING, $now, $id, JobTargetModel::STATUS_RUNNING, JobTargetModel::STATUS_DELIVERED, $staleBefore]
        );

        return $db->affectedRows() === 1;
    }

    private function closeTarget(int $id, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $this->targetModel->update($id, $data);
    }

    private function pickTransport(array $device, array $job = []): ?ConfigTransportInterface
    {
        foreach ($this->transports as $transport) {
            // The job is passed through because reachability is not the whole question: a device agent
            // too old to merge a fragment must not be handed one, and has to fall through to a
            // transport that can carry it.
            if ($transport->supports($device, $job)) {
                return $transport;
            }
        }

        return null;
    }
}
