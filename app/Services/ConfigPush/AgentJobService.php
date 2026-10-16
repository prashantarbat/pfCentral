<?php

namespace App\Services\ConfigPush;

use App\Models\ConfigJobModel;
use App\Models\JobTargetModel;
use App\Models\PfInventoryModel;

/**
 * Server side of the device-initiated path: hand a device its armed work, accept its report.
 *
 * The endpoint is deliberately dumb — it makes no scheduling decisions. Whether a job may run at
 * all (approval) and in what order (canary) is decided by the runner, which arms jobs here. This
 * service only serves what has already been armed and records what comes back.
 *
 * One exception, on purpose: the canary rule is re-checked at pickup. Re-checking means a bug in
 * the arming path could not leak an unproven fleet-wide change to dozens of branches, and a device
 * is never trusted to decide that the fleet may proceed.
 */
class AgentJobService
{
    public const MAX_PER_PICKUP = 5;

    /**
     * How many due window checks one poll may carry.
     *
     * The agent polls every minute and the densest window is a slot every five, so one or two is the normal
     * case; the cap bounds a single report after a device has been offline, when several slots have come due
     * at once — and several is a real possibility now that a change can be watched for a day, though the
     * device reads the file once either way.
     */
    public const MAX_WATCH_PER_PICKUP = 4;

    protected AgentAuthService $auth;
    protected ConfigJobService $jobService;
    protected ConfigSnapshotStore $snapshots;
    protected PushLogWriter $logWriter;
    protected JobTargetModel $targetModel;
    protected ConfigJobModel $jobModel;
    protected PfInventoryModel $inventoryModel;
    protected ConfigVerificationService $verifier;

    public function __construct(
        ?AgentAuthService $auth = null,
        ?ConfigJobService $jobService = null,
        ?ConfigSnapshotStore $snapshots = null,
        ?PushLogWriter $logWriter = null,
        ?JobTargetModel $targetModel = null,
        ?ConfigJobModel $jobModel = null,
        ?PfInventoryModel $inventoryModel = null,
        ?ConfigVerificationService $verifier = null
    ) {
        $this->verifier       = $verifier ?? new ConfigVerificationService();
        $this->auth           = $auth ?? new AgentAuthService();
        $this->jobService     = $jobService ?? new ConfigJobService();
        $this->snapshots      = $snapshots ?? new ConfigSnapshotStore();
        $this->logWriter      = $logWriter ?? new PushLogWriter();
        $this->targetModel    = $targetModel ?? new JobTargetModel();
        $this->jobModel       = $jobModel ?? new ConfigJobModel();
        $this->inventoryModel = $inventoryModel ?? new PfInventoryModel();

        // The device and pfCentral decide "does this window check need the configuration uploaded" from one
        // file, required verbatim on both sides, so the two ends cannot drift apart about it.
        require_once ROOTPATH . 'public/assets/endpoint/agent_watch.php';
    }

    /**
     * Claims this device's armed targets and returns their payloads, signed.
     *
     * @return list<array> sealed jobs, ready to hand to the device
     */
    public function claimForDevice(array $device, array $agent, int $limit = self::MAX_PER_PICKUP): array
    {
        $pfId    = (int) $device['id'];
        $limit   = max(1, min(self::MAX_PER_PICKUP, $limit));
        $claimed = [];

        $candidates = db_connect()
            ->table('job_targets')
            ->select('job_targets.id AS target_id, job_targets.job_id, config_jobs.batch_id, config_jobs.payload_type, config_jobs.rendered_payload, config_jobs.canary_pf_id, config_jobs.target_spec')
            ->join('config_jobs', 'config_jobs.id = job_targets.job_id', 'inner')
            ->where('job_targets.pf_id', $pfId)
            ->where('job_targets.status', JobTargetModel::STATUS_DELIVERED)
            ->whereIn('config_jobs.status', ConfigJobModel::RUNNABLE_STATUSES)
            // An approval-gated job is never served without its approval, whatever the queue says.
            ->groupStart()
                ->where('config_jobs.requires_approval', 0)
                ->orGroupStart()
                    ->where('config_jobs.requires_approval', 1)
                    ->where('config_jobs.approved_by IS NOT NULL', null, false)
                ->groupEnd()
            ->groupEnd()
            ->orderBy('job_targets.id', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        if ($candidates === []) {
            return [];
        }

        $cleared = $this->canaryClearedJobIds($candidates, $pfId);

        foreach ($candidates as $candidate) {
            if (!in_array((int) $candidate['job_id'], $cleared, true)) {
                continue;
            }

            // Defence in depth. Arming already refuses a merge job for a device agent that cannot
            // merge, but an agent *downgraded* between arming and pickup would otherwise be handed a
            // fragment — and its apply step writes the payload straight over /cf/conf/config.xml,
            // installing one section as the whole configuration. The target stays armed for a
            // transport that can carry it instead.
            if (!$this->agentCanApply($agent, $candidate)) {
                log_message('warning', 'Not serving merge job #' . $candidate['job_id'] . ' to a device agent below ' . AgentAuthService::MERGE_MIN_VERSION . '.');
                continue;
            }

            // Conditional claim: delivered -> running. A job already taken (or canceled between the
            // query and here) simply is not served twice.
            $connection  = db_connect();
            $claimResult = $connection->query(
                'UPDATE job_targets SET status = ?, updated_at = ? WHERE id = ? AND status = ?',
                [JobTargetModel::STATUS_RUNNING, date('Y-m-d H:i:s'), (int) $candidate['target_id'], JobTargetModel::STATUS_DELIVERED]
            );

            // A raw write returns true rather than a result object, so the row count comes from the
            // connection. A failed statement returns false instead of throwing when the driver
            // suppresses warnings, so that must be reported and not mistaken for 'someone took it'.
            if ($claimResult === false) {
                log_message('error', 'Agent job claim failed: ' . json_encode($connection->error()));
                continue;
            }

            if ($connection->affectedRows() !== 1) {
                continue;
            }

            // A raw builder select returns the JSON column as a string: the model's json-array cast
            // only applies to rows the model itself reads.
            $spec = $candidate['target_spec'];
            if (is_string($spec)) {
                $spec = json_decode($spec, true) ?: [];
            }

            $job = [
                'job_id'       => (int) $candidate['job_id'],
                'target_id'    => (int) $candidate['target_id'],
                'batch_id'     => (string) $candidate['batch_id'],
                'payload_type' => (string) $candidate['payload_type'],
                'payload'      => (string) $candidate['rendered_payload'],
                'target_type'  => is_array($spec) ? ($spec['type'] ?? null) : null,
            ];

            $claimed[] = array_merge($job, $this->auth->sealJob($agent, $job));
        }

        return $claimed;
    }

    /**
     * Whether this device's agent can be trusted with this job's payload.
     *
     * @param array $candidate a claimed row carrying payload_type and rendered_payload
     */
    private function agentCanApply(array $agent, array $candidate): bool
    {
        $isMerge = ConfigFragmentMerger::isMergeJob([
            'payload_type' => (string) ($candidate['payload_type'] ?? 'xml'),
            'payload'      => (string) ($candidate['rendered_payload'] ?? ''),
        ]);

        return !$isMerge || $this->auth->agentCanMerge($agent);
    }

    /**
     * Which of these jobs may be served to this device right now.
     *
     * A job with no canary is always clear. A job with a canary is clear only for the canary device
     * itself, or once the canary has actually applied.
     *
     * @param list<array> $candidates
     *
     * @return list<int>
     */
    private function canaryClearedJobIds(array $candidates, int $pfId): array
    {
        $jobIds = array_values(array_unique(array_map(static fn ($row) => (int) $row['job_id'], $candidates)));

        $canaryStatus = [];
        foreach ($candidates as $row) {
            if (!empty($row['canary_pf_id'])) {
                $canaryStatus[(int) $row['job_id']] = (int) $row['canary_pf_id'];
            }
        }

        if ($canaryStatus === []) {
            return $jobIds;
        }

        // Joined on the job's *own* canary device, so a row can only clear the job it belongs to.
        $applied = db_connect()
            ->table('job_targets')
            ->select('job_targets.job_id')
            ->join('config_jobs', 'config_jobs.id = job_targets.job_id', 'inner')
            ->whereIn('job_targets.job_id', array_keys($canaryStatus))
            ->where('job_targets.status', JobTargetModel::STATUS_APPLIED)
            ->where('job_targets.pf_id = config_jobs.canary_pf_id', null, false)
            ->get()
            ->getResultArray();

        $appliedJobIds = array_map(static fn ($row) => (int) $row['job_id'], $applied);

        $cleared = [];
        foreach ($jobIds as $jobId) {
            if (!isset($canaryStatus[$jobId])) {
                $cleared[] = $jobId;           // no canary to satisfy
                continue;
            }

            if ($canaryStatus[$jobId] === $pfId || in_array($jobId, $appliedJobIds, true)) {
                $cleared[] = $jobId;
            }
        }

        return $cleared;
    }

    // ------------------------------------------------------------------ verification window

    /**
     * The window checks this device owes, sealed and ready to hand back on its poll.
     *
     * This is how a device pfCentral cannot reach is still watched: the *schedule* lives here, in
     * `job_targets.watch_*`, where it is visible on screen and survives a reboot of either end, and the
     * device picks up the due slot on the poll it was already making once a minute. Nothing is claimed
     * here — a check is a read, not a job — so a device that takes one and vanishes is asked again when it
     * returns, and the slot it names in its report is what stops a retry from advancing the schedule twice.
     *
     * @return list<array> target_id, job_id, slot, watch_hash, signature
     */
    public function claimWatches(array $device, array $agent, int $limit = self::MAX_WATCH_PER_PICKUP): array
    {
        $pfId = (int) $device['id'];

        if ($pfId <= 0 || !$this->auth->agentCanWatch($agent)) {
            return [];
        }

        try {
            $rows = db_connect()
                ->table('job_targets')
                ->select('job_targets.id, job_targets.job_id, job_targets.watch_checks, job_targets.watch_hash, config_jobs.payload_type')
                ->join('config_jobs', 'config_jobs.id = job_targets.job_id', 'inner')
                ->where('job_targets.pf_id', $pfId)
                ->where('job_targets.status', JobTargetModel::STATUS_APPLIED)
                ->where('job_targets.watch_via', ConfigVerificationService::WATCH_VIA_AGENT)
                ->where('job_targets.watch_next_at IS NOT NULL', null, false)
                ->where('job_targets.watch_next_at <=', date('Y-m-d H:i:s'))
                // The bound is the largest any window may be, not this job's own length: a job's schedule
                // is a property of the job, and the real end of its window is `advanceWatch()` clearing
                // `watch_next_at` on the last slot. See `MAX_WATCH_SLOTS`.
                ->where('job_targets.watch_checks <', ConfigVerificationService::MAX_WATCH_SLOTS)
                // A script has no configuration to compare, so there is nothing to watch for it.
                ->where('config_jobs.payload_type', 'xml')
                ->orderBy('job_targets.watch_next_at', 'ASC')
                ->limit(max(1, $limit))
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Agent watch checks could not be listed: ' . $e->getMessage());

            return [];
        }

        $checks = [];

        foreach ($rows as $row) {
            $checks[] = [
                'target_id'  => (int) $row['id'],
                'job_id'     => (int) $row['job_id'],
                'slot'       => (int) $row['watch_checks'],
                'watch_hash' => $row['watch_hash'] !== null ? (string) $row['watch_hash'] : null,
            ];
        }

        if ($checks === []) {
            return [];
        }

        return $this->auth->sealWatches($agent, $checks)['checks'];
    }

    /**
     * Records what the device found when it read its own configuration.
     *
     * One read answers every check in the batch — they are all looking at the same file — so the report
     * carries one hash and at most one configuration, and each check is decided on its own.
     *
     * A check whose recorded hash still matches is `matched` from the hash alone: the file is byte-for-byte
     * what this change installed, which is a stronger claim than "the intended settings are present" and
     * needs no upload. Anything else is compared against the job the ordinary way — the same convergence
     * test the SSH window and the hourly sweep use, so a later change to an unrelated section is tolerated
     * rather than reported as drift. A changed file the device could not send (too large) is `unavailable`,
     * never a pass.
     *
     * Every refusal names the slot it was waiting for, because the slot is the whole replay defence: a
     * report for a check that has already been answered, or one that is not due yet, is refused rather than
     * silently advancing a device's schedule.
     *
     * @param array $input config_sha256, config? (base64), checks: list of {target_id, slot}
     *
     * @return array{ok:bool, message:string, results:list<array>}
     */
    public function watchReport(array $device, array $input): array
    {
        $pfId = (int) $device['id'];
        $sha  = trim((string) ($input['config_sha256'] ?? ''));
        $raw  = (string) ($input['config'] ?? '');
        $checks = $input['checks'] ?? [];

        if (!is_array($checks) || $checks === []) {
            return ['ok' => false, 'message' => 'A watch report must carry the checks it answers.', 'results' => []];
        }

        $config = null;
        if (trim($raw) !== '') {
            $decoded = base64_decode($raw, true);

            if ($decoded === false || trim($decoded) === '') {
                return ['ok' => false, 'message' => 'The uploaded configuration was not valid base64.', 'results' => []];
            }

            $config = $decoded;

            // The hash the device claims and the configuration it sent have to be the same reading, or the
            // two halves of this report describe different files and neither can be trusted.
            $actual = hash('sha256', $decoded);

            if ($sha !== '' && !hash_equals($actual, $sha)) {
                return ['ok' => false, 'message' => 'The uploaded configuration does not match the hash reported for it.', 'results' => []];
            }

            $sha = $actual;
        }

        $results = [];
        $answered = 0;

        foreach ($checks as $check) {
            $targetId = is_array($check) ? (int) ($check['target_id'] ?? 0) : 0;
            $slot     = is_array($check) ? (int) ($check['slot'] ?? -1) : -1;

            $outcome = $this->recordWatchCheck($pfId, $targetId, $slot, $sha, $config);
            $results[] = $outcome;

            if ($outcome['recorded']) {
                $answered++;
            }
        }

        return [
            'ok'      => $answered > 0,
            'message' => $answered > 0
                ? $answered . ' of ' . count($checks) . ' watch check(s) recorded.'
                : 'No watch check in this report was due for this device.',
            'results' => $results,
        ];
    }

    /**
     * One check: validate it is really this device's and really due, decide, record, and move the schedule.
     *
     * @return array{target_id:int, slot:int, recorded:bool, status:?string, detail:string, next:?string}
     */
    private function recordWatchCheck(int $pfId, int $targetId, int $slot, string $sha, ?string $config): array
    {
        $refuse = static fn (string $detail): array => [
            'target_id' => $targetId,
            'slot'      => $slot,
            'recorded'  => false,
            'status'    => null,
            'detail'    => $detail,
            'next'      => null,
        ];

        $target = $this->targetModel->find($targetId);

        if (!$target) {
            return $refuse("Target #{$targetId} does not exist.");
        }

        if ((int) $target['pf_id'] !== $pfId) {
            return $refuse("Target #{$targetId} does not belong to this device.");
        }

        if ((string) ($target['status'] ?? '') !== JobTargetModel::STATUS_APPLIED) {
            return $refuse("Target #{$targetId} is '{$target['status']}', so there is no applied change to re-check.");
        }

        $expected = $target['watch_checks'] !== null ? (int) $target['watch_checks'] : 0;

        if (!pfcentral_watch_slot_matches($expected, $slot)) {
            return $refuse("Target #{$targetId} is waiting for check {$expected}, not check {$slot} — this report is late, replayed, or was already recorded.");
        }

        $due = $target['watch_next_at'] ?? null;

        if ($due === null) {
            return $refuse("Target #{$targetId} has no window check outstanding.");
        }

        if (strtotime((string) $due) > time()) {
            return $refuse("Target #{$targetId} is not due to be read until {$due}.");
        }

        $job = $this->jobModel->find((int) $target['job_id']);

        if (!$job) {
            return $refuse('The job behind target #' . $targetId . ' no longer exists.');
        }

        $verdict = pfcentral_watch_verdict($target['watch_hash'] ?? null, $sha, $config);

        if ($verdict === 'compare') {
            // The device's configuration has changed since it applied this job, so it has to be compared
            // against what the job intended. `verify()` records the verdict and raises any alert.
            $outcome = $this->verifier->verify($target, $job, $this->deviceRecord($pfId, $target), (string) $config);
            $status  = (string) $outcome['status'];
            $detail  = (string) $outcome['detail'];
        } elseif ($verdict === 'unchanged') {
            $outcome = $this->verifier->record(
                $target,
                $job,
                $this->deviceRecord($pfId, $target),
                JobTargetModel::VERIFY_MATCHED,
                'The device read its own configuration and it is byte-for-byte what this change installed; nothing has touched it since.'
            );
            $status = (string) $outcome['status'];
            $detail = (string) $outcome['detail'];
        } else {
            $outcome = $this->verifier->record(
                $target,
                $job,
                $this->deviceRecord($pfId, $target),
                JobTargetModel::VERIFY_UNAVAILABLE,
                'The device read its own configuration and reported that it has changed since this change was applied, but did not send a copy to compare.',
                true,
                // The device's own scheduling budget is the window's slots; the sweep's three attempts are
                // kept for after the hour, exactly as they are for a pfCentral-side window check.
                false
            );
            $status = (string) $outcome['status'];
            $detail = (string) $outcome['detail'];
        }

        $next = $this->verifier->advanceWatch(
            $targetId,
            $target,
            $expected,
            $status === JobTargetModel::VERIFY_DRIFTED,
            // The job's own window: a device watching a change for a day has nine slots to walk, not four,
            // and advancing it against the default would close its window seven checks early.
            ConfigVerificationService::watchScheduleFor($job)
        );

        return [
            'target_id' => $targetId,
            'slot'      => $slot,
            'recorded'  => true,
            'status'    => $status,
            'detail'    => $detail,
            'next'      => $next,
        ];
    }

    /**
     * The inventory row for a device, for the log row and the alert. Falls back to a stub rather than
     * failing: a device that exists enough to have reported can still be named in an alert.
     */
    private function deviceRecord(int $pfId, array $target): array
    {
        return $this->inventoryModel->find($pfId)
            ?? ['id' => $pfId, 'name' => 'device #' . ($target['pf_id'] ?? $pfId)];
    }

    /**
     * Targets this device is currently applying, and a keep-alive so a slow apply is not mistaken
     * for an abandoned one and reclaimed out from under the device.
     *
     * @return list<int>
     */
    public function inFlight(int $pfId): array
    {
        $rows = db_connect()
            ->table('job_targets')
            ->select('id')
            ->where('pf_id', $pfId)
            ->where('status', JobTargetModel::STATUS_RUNNING)
            ->get()
            ->getResultArray();

        $ids = array_map(static fn ($row) => (int) $row['id'], $rows);

        if ($ids !== []) {
            db_connect()->table('job_targets')->whereIn('id', $ids)->update(['updated_at' => date('Y-m-d H:i:s')]);
        }

        return $ids;
    }

    /**
     * Records what the device did.
     *
     * `post_change_config` deserves a note. On the agent channel pfCentral never opens a connection to
     * the branch, so the device is the *only* possible source for verification — and it is asked to
     * send the configuration it read back after reloading, not the payload it was given. A device
     * echoing the payload back would prove nothing at all, so this is a read of `/cf/conf/config.xml`
     * taken after the change, and it is compared against the job like any other verification.
     *
     * @param array $input target_id, status (applied|failed), message, log, pre_change_config?,
     *                     post_change_config?, duration_ms?, watch_hash?
     *
     * @return array{ok:bool, message:string, target_status:?string, job_status:?string, backup_file:?string, verify_status:?string}
     */
    public function report(array $device, array $input): array
    {
        $pfId     = (int) $device['id'];
        $targetId = (int) ($input['target_id'] ?? 0);
        $outcome  = strtolower(trim((string) ($input['status'] ?? '')));
        $message  = trim((string) ($input['message'] ?? ''));

        if ($outcome !== 'applied' && $outcome !== 'failed') {
            return $this->refuse("status must be 'applied' or 'failed'.");
        }

        $target = $this->targetModel->find($targetId);
        if (!$target) {
            return $this->refuse("Target #{$targetId} does not exist.");
        }

        // A device may only speak for itself.
        if ((int) $target['pf_id'] !== $pfId) {
            return $this->refuse("Target #{$targetId} does not belong to this device.");
        }

        // Only work this device actually collected can be reported on, which is what stops a late
        // report from overwriting a result pfCentral already reached over the SSH fallback.
        if (($target['status'] ?? '') !== JobTargetModel::STATUS_RUNNING) {
            return $this->refuse("Target #{$targetId} is '{$target['status']}', not in flight — nothing to record (a retry of this report is harmless).");
        }

        $job = $this->jobModel->find($target['job_id']);
        if (!$job) {
            return $this->refuse('The job behind this target no longer exists.');
        }

        $deviceName = $device['name'] ?? ('device #' . $pfId);
        $applied    = $outcome === 'applied';
        $now        = date('Y-m-d H:i:s');
        $log        = mb_substr((string) ($input['log'] ?? ''), 0, 20000);
        $durationMs = isset($input['duration_ms']) ? (int) $input['duration_ms'] : null;

        // Take the outcome first, conditionally, so a device retrying its report cannot record it
        // twice (or write a second snapshot file).
        $connection = db_connect();
        $taken      = $connection->query(
            'UPDATE job_targets SET status = ?, applied_at = ?, error = ?, result_log = ?, duration_ms = ?, updated_at = ? WHERE id = ? AND status = ?',
            [
                $applied ? JobTargetModel::STATUS_APPLIED : JobTargetModel::STATUS_FAILED,
                $applied ? $now : null,
                $applied ? null : mb_substr($message !== '' ? $message : 'The device reported a failure.', 0, 500),
                $log,
                $durationMs,
                $now,
                $targetId,
                JobTargetModel::STATUS_RUNNING,
            ]
        );

        if ($taken === false) {
            log_message('error', 'Agent report update failed: ' . json_encode($connection->error()));

            return $this->refuse('The result could not be recorded.');
        }

        if ($connection->affectedRows() !== 1) {
            return $this->refuse("Target #{$targetId} was recorded by another report first.");
        }

        // The device uploads the config it replaced, because with a device-initiated push pfCentral
        // never opens a connection to read it. This is what keeps one-click rollback working.
        $backupId   = null;
        $backupFile = null;
        $note       = '';

        $uploaded = (string) ($input['pre_change_config'] ?? '');
        if (trim($uploaded) !== '') {
            $decoded = base64_decode($uploaded, true);

            if ($decoded === false || trim($decoded) === '') {
                $note = ' The uploaded pre-change snapshot was not valid base64 and was discarded.';
            } else {
                $stored = $this->snapshots->store($device, $deviceName, $decoded, (string) $job['batch_id'], ConfigSnapshotStore::PREFIX_AGENT);

                if ($stored['ok']) {
                    $backupId   = $stored['id'];
                    $backupFile = $stored['file'];
                } else {
                    $note = ' No rollback snapshot was stored: ' . $stored['message'];
                }
            }
        } elseif ($applied) {
            $note = ' Applied without a rollback snapshot.';
        }

        if ($backupId !== null || $backupFile !== null) {
            $this->targetModel->update($targetId, ['backup_id' => $backupId, 'backup_file' => $backupFile]);
        }

        // Verification, for a device nothing else can read. A failed report is never verified: there is
        // nothing to confirm, and "applied but drifted" must not be said about a change that never
        // went in.
        $verify = ['status' => null, 'detail' => ''];

        if ($applied) {
            $posted = $this->decodeConfig((string) ($input['post_change_config'] ?? ''));

            // Keep the hash of the configuration the device says it is running, because it is what makes a
            // device-side window check cheap: while that hash still matches the file, nothing at all has
            // touched it since this change was installed — better evidence than the convergence test it
            // replaces, and it costs a few hundred bytes instead of a configuration upload.
            if ($posted !== null) {
                try {
                    $this->targetModel->update($targetId, ['watch_hash' => hash('sha256', $posted)]);
                } catch (\Throwable $e) {
                    log_message('error', 'Watch hash could not be stored for target #' . $targetId . ': ' . $e->getMessage());
                }
            }

            // And only then open a window, if somebody can actually do the reading: a transport on this
            // side, or the device's own agent (v2.2) reading itself on the schedule the pickup hands it.
            $this->verifier->startWatch($targetId, $job, $device);

            $verify = $posted === null
                ? $this->verifier->verifyDevice($target, $job, $device)
                : $this->verifier->verify($target, $job, $device, $posted);
        }

        // A report is an auditable deployment in its own right, so it gets its own history row.
        $logRowId = $this->logWriter->start(
            (string) $job['batch_id'],
            $device,
            $deviceName,
            $job['target_spec']['type'] ?? null,
            (string) $job['payload_type'],
            isset($job['requested_by']) ? (int) $job['requested_by'] : null,
            'Reported by the device agent.'
        );

        $verifyNote = $verify['status'] === null ? '' : ' Verification: ' . $verify['detail'];

        $this->logWriter->finish($logRowId, [
            'status'      => $applied ? 'success' : 'failed',
            'message'     => ($message !== '' ? $message : ($applied ? 'Applied by the device agent.' : 'Reported as failed by the device agent.')) . $note . $verifyNote,
            'ip'          => $device['man_ip'] ?? null,
            'backup_id'   => $backupId,
            'backup_file' => $backupFile,
            'log'         => $log,
            'duration_ms' => $durationMs,
        ]);

        // Refresh now rather than waiting for the next sweep, so the dashboard is immediate.
        $jobStatus = $this->jobService->refreshJobStatus((int) $job['id']);

        $status = $applied ? 'applied' : 'failed';

        return [
            'ok'            => true,
            'message'       => "Recorded target #{$targetId} as {$status}." . $note . $verifyNote,
            'target_status' => $status,
            'job_status'    => $jobStatus,
            'backup_file'   => $backupFile,
            'verify_status' => $verify['status'],
        ];
    }

    /**
     * Decodes a base64 configuration the device uploaded.
     *
     * Returns null when the field is absent or unusable, so the caller can fall back to reading the
     * device itself rather than treating a bad upload as a configuration.
     */
    private function decodeConfig(string $encoded): ?string
    {
        if (trim($encoded) === '') {
            return null;
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false || trim($decoded) === '') {
            return null;
        }

        return ConfigSnapshotStore::validate($decoded) === null ? $decoded : null;
    }

    /**
     * @return array{ok:bool, message:string, target_status:?string, job_status:?string, backup_file:?string, verify_status:?string}
     */
    private function refuse(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'target_status' => null, 'job_status' => null, 'backup_file' => null, 'verify_status' => null];
    }
}
