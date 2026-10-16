<?php

namespace App\Services\ConfigPush;

use App\Models\ConfigJobModel;
use App\Models\JobTargetModel;
use App\Models\PfInventoryModel;

/**
 * Queue-facing half of the config push: create a job, fan it out to devices, report
 * progress. The actual device work lives in ConfigJobRunner.
 */
class ConfigJobService
{
    protected ConfigJobModel $jobModel;
    protected JobTargetModel $targetModel;
    protected PfInventoryModel $inventoryModel;

    public function __construct(?ConfigJobModel $jobModel = null, ?JobTargetModel $targetModel = null, ?PfInventoryModel $inventoryModel = null)
    {
        $this->jobModel       = $jobModel ?? new ConfigJobModel();
        $this->targetModel    = $targetModel ?? new JobTargetModel();
        $this->inventoryModel = $inventoryModel ?? new PfInventoryModel();
    }

    /**
     * A payload type or target spec that a second human must sign off on before anything
     * touches a device: raw root shell scripts, and anything fleet-wide.
     *
     * Kept in one place so the controller, the runner and the UI cannot disagree about
     * whether a job is approved enough to run.
     */
    public static function requiresApproval(string $payloadType, array $targetSpec): bool
    {
        return $payloadType === 'script' || ($targetSpec['type'] ?? '') === 'all';
    }

    /**
     * Whether repairing a specific job needs a second approver.
     *
     * Two rules, and both have to be here rather than sprinkled through the caller:
     *
     *  1. If the change needed oversight the first time it was applied, re-applying it needs oversight
     *     too. Without this, a repair would be a quieter route to the same change than the push was — a
     *     way *around* the gate rather than a use of it.
     *  2. The ordinary rule is re-evaluated on the repair as well, so a script payload is gated however
     *     the original was recorded.
     *
     * Pulled out as a pure function because this is the one decision in a repair that must never be
     * wrong, and a rule that can be tested without a database is a rule that stays tested.
     *
     * @param array $sourceJob the job being repaired
     */
    public static function reconcileRequiresApproval(array $sourceJob, string $payloadType, array $repairSpec): bool
    {
        return (int) ($sourceJob['requires_approval'] ?? 0) === 1
            || self::requiresApproval($payloadType, $repairSpec);
    }

    /**
     * Whether undoing a change needs a second approver.
     *
     * Deliberately the same rule as a repair, delegated rather than copied so the two can never drift
     * apart, because the gate belongs to the *change* and not to the direction it is being applied in.
     * A rollback of an ordinary one-branch XML push runs unattended — which is the whole point of the
     * feature, since the window catches the revert minutes after the push and nobody is awake. A
     * rollback of a change a second approver had to sign off on — a script, or anything fleet-wide —
     * waits for that approver too: a revert must not become the quiet route to the same action.
     *
     * The source's own payload type is re-evaluated rather than trusted to its recorded flag: a script is
     * gated because of what it *is*, and the undo of one must not hinge on a column having been written
     * correctly when the job was created. (The rollback's own payload is honestly `xml` — it *is* the
     * device's pre-push config.xml — so it is the source's type that carries the rule here.)
     *
     * @param array $sourceJob the job being undone
     */
    public static function rollbackRequiresApproval(array $sourceJob, array $spec): bool
    {
        return self::reconcileRequiresApproval($sourceJob, (string) ($sourceJob['payload_type'] ?? 'xml'), $spec);
    }

    /**
     * Whether an automatic rollback may be raised at all.
     *
     * On by default: a revert is the one failure that looks like a clean success at both ends of the
     * hour, and an automatic repair is what makes catching it early worth anything. Set
     * `configJobs.autoRollback=false` to have reverts reported and left alone — the alert still goes
     * out, and the pre-push snapshot is still there for a person to restore.
     */
    public static function autoRollbackEnabled(bool|string|null $value = null): bool
    {
        if ($value === null) {
            $value = env('configJobs.autoRollback');
        }

        // `env()` answers this key with a real `false` when it is switched off, and a `false` squeezed
        // through a string parameter would arrive as `''` — indistinguishable from "unset", which
        // defaults to on. So "off" is checked before "empty", and that ordering is the whole feature: a
        // setting that silently means its opposite is worse than no setting.
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || trim($value) === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Why this target must not be rolled back automatically, or null when it should be.
     *
     * Pure, and this decision table is the whole safety story of the feature: every rule here is a case
     * where acting would be worse than reporting. In order:
     *
     *  1. **The switch is off.** Reported, not acted on.
     *  2. **The job is itself a rollback.** A device that undid the configuration we restored has
     *     something restoring it repeatedly, and putting the same file back a second time would fight
     *     it forever. That is a person's problem, and the alert says so.
     *  3. **This device was already handed to a rollback.** One revert, one repair: the guard is the
     *     target's own `rollback_job_id`, so a device is never rolled back twice for the same drift no
     *     matter how many sweeps, manual re-checks or window checks meet it afterwards.
     *  4. **It is not a revert.** A difference found after the window closed is a *later* change to the
     *     device, and restoring the pre-push snapshot would silently undo whatever came after it — the
     *     automatic path only ever answers the event the window actually witnessed.
     *  5. **There is no snapshot.** Without a pre-push artifact there is nothing to restore, and a
     *     rollback invented from the current state would be a change dressed up as an undo.
     *
     * A reason is always returned in the operator's words, because it ends up in the alert: "we did not
     * roll this back, and here is why" is the difference between a feature people trust and one they
     * have to guess about.
     *
     * @param array  $target       job_targets row
     * @param array  $job          config_jobs row (the job the target belongs to)
     * @param string $kind         ConfigAlertService::KIND_REVERT or KIND_DRIFT
     * @param bool   $enabled      whether auto-rollback is switched on
     * @param ?string $snapshotFile the recorded pre-push snapshot file name, if any
     * @param ?int    $snapshotId   the recorded pre-push `pf_backups` row id, if any
     */
    public static function autoRollbackRefusal(
        array $target,
        array $job,
        string $kind,
        bool $enabled,
        ?string $snapshotFile = null,
        ?int $snapshotId = null
    ): ?string {
        if (!$enabled) {
            return 'Automatic rollback is switched off (configJobs.autoRollback), so this revert was reported and nothing was restored.';
        }

        if ((int) ($job['rollback_of_job_id'] ?? 0) > 0) {
            return 'This job is itself a rollback, so the restored configuration was undone again: something on the device is putting it back, and repeating the restore would fight it. Nothing was rolled back a second time';
        }

        $existing = (int) ($target['rollback_job_id'] ?? 0);

        if ($existing > 0) {
            return 'This device has already been handed to rollback job #' . $existing . ' for this drift, so no second rollback was queued.';
        }

        if (!ConfigAlertService::isRevert($kind)) {
            return 'That difference was found after the verification window closed, so it is a later change rather than a revert — restoring the pre-push snapshot would undo whatever came after it. Left for a person to decide.';
        }

        if (empty($snapshotFile) && empty($snapshotId)) {
            return 'No pre-push snapshot was kept for this device, so there is nothing to roll back to.';
        }

        return null;
    }

    /**
     * Whether separation of duties is enforced. Operators running a one-admin deployment can
     * set configJobs.allowSelfApproval=true, at the cost of the whole point of the gate.
     */
    public static function selfApprovalAllowed(): bool
    {
        $value = env('configJobs.allowSelfApproval');

        return $value !== null && filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Creates the job and one queued target row per device.
     *
     * A job needing sign-off starts in 'awaiting_approval', a status the runner refuses to
     * pick up, so an unapproved job cannot run even if some future code path forgets the rule.
     *
     * @param array $attrs   payload, payload_type, target_spec (array), requested_by, template_id?,
     *                       canary (bool), canary_pf_id (int|null), watch_profile (string)
     * @param array $devices pf_inventory rows
     *
     * @return int the new job id
     */
    public function createJob(array $attrs, array $devices): int
    {
        $batchId     = self::uuid();
        $payloadType = $attrs['payload_type'] ?? 'xml';
        $targetSpec  = $attrs['target_spec'] ?? [];
        $watch       = $this->resolveWatch($attrs, $payloadType);
        // The gate can also be inherited. A reconcile re-applies bytes a second approver already signed
        // off on once, and if that change needed oversight the first time, quietly re-applying it without
        // oversight would be a way around the gate rather than a use of it.
        $requiresApproval = self::requiresApproval($payloadType, $targetSpec) || !empty($attrs['requires_approval']);
        $canaryPfId       = $this->resolveCanary($attrs, $devices);

        // Derived from the payload, not from the caller: the recorded mode has to describe the bytes
        // the transports will actually apply, or the audit trail would be fiction.
        $payloadMode = $payloadType === 'xml'
            ? (ConfigFragmentMerger::mode((string) ($attrs['payload'] ?? '')) ?? ConfigJobModel::MODE_REPLACE)
            : ConfigJobModel::MODE_REPLACE;

        $jobId = $this->jobModel->insert([
            'batch_id'          => $batchId,
            'template_id'       => $attrs['template_id'] ?? null,
            'rendered_payload'  => (string) ($attrs['payload'] ?? ''),
            'payload_type'      => $payloadType,
            'payload_mode'      => $payloadMode,
            'target_spec'       => $targetSpec,
            'status'            => $requiresApproval ? ConfigJobModel::STATUS_AWAITING_APPROVAL : ConfigJobModel::STATUS_PENDING,
            'requires_approval' => $requiresApproval ? 1 : 0,
            'canary_pf_id'      => $canaryPfId,
            // The choice and the schedule it resolved to. Frozen here rather than derived at read time
            // because `job_targets.watch_checks` indexes into the schedule — see the migration.
            'watch_profile'     => $watch['profile'],
            'watch_schedule'    => $watch['schedule'],
            'reconcile_of_job_id' => $attrs['reconcile_of_job_id'] ?? null,
            'rollback_of_job_id'  => $attrs['rollback_of_job_id'] ?? null,
            'requested_by'      => $attrs['requested_by'] ?? null,
        ], true);

        $rows = [];
        $now  = date('Y-m-d H:i:s');
        foreach ($devices as $device) {
            $rows[] = [
                'job_id'     => $jobId,
                'pf_id'      => $device['id'],
                'status'     => JobTargetModel::STATUS_QUEUED,
                'is_canary'  => ($canaryPfId !== null && (int) $device['id'] === $canaryPfId) ? 1 : 0,
                'attempts'   => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            $this->targetModel->insertBatch($rows);
        }

        return (int) $jobId;
    }

    /**
     * How long this job's change is watched for, and why it is normalised here rather than in the form.
     *
     * Two rules, both of them about not storing a fiction:
     *
     *  1. **A script is never watched.** It has no intended configuration to compare, so a window on it
     *     could only ever record "could not confirm" — and those records would overwrite the verdict the
     *     device's own report gave. `needsRead()` refuses it at the window anyway; forcing the profile to
     *     `none` means the job row says so instead of implying a watch that cannot happen.
     *  2. **The schedule is resolved now, not later.** A repair that inherits a profile inherits the
     *     schedule it stood for at this moment, so editing a profile's slots later cannot change what an
     *     in-flight window means.
     *
     * A *submitted* profile that does not exist is refused by the controller, not normalised silently:
     * turning a typo in "watched for a day" into the default hour is exactly the kind of quiet downgrade a
     * safety setting must not have.
     *
     * @param array $attrs may carry watch_profile
     *
     * @return array{profile:string, schedule:list<int>}
     */
    private function resolveWatch(array $attrs, string $payloadType): array
    {
        $profile = $payloadType === 'script'
            ? ConfigVerificationService::WATCH_PROFILE_NONE
            : ConfigVerificationService::normaliseWatchProfile((string) ($attrs['watch_profile'] ?? ConfigVerificationService::DEFAULT_WATCH_PROFILE));

        return [
            'profile'  => $profile,
            'schedule' => ConfigVerificationService::watchProfileMinutes($profile),
        ];
    }

    /**
     * Picks the single device a canary job must succeed on first. A single-device job has
     * nothing to stage, so canary is silently ignored there.
     *
     * @param array $attrs   may carry canary (bool) and canary_pf_id (int)
     * @param array $devices pf_inventory rows
     */
    private function resolveCanary(array $attrs, array $devices): ?int
    {
        if (empty($attrs['canary']) || count($devices) < 2) {
            return null;
        }

        $requested = isset($attrs['canary_pf_id']) ? (int) $attrs['canary_pf_id'] : 0;
        if ($requested > 0) {
            foreach ($devices as $device) {
                if ((int) $device['id'] === $requested) {
                    return $requested;
                }
            }
        }

        // Default to the first device in the resolved order.
        return (int) $devices[0]['id'];
    }

    /**
     * Second-approver sign-off. Moves the job into the runnable queue.
     *
     * @return array{ok:bool, message:string, job:?array}
     */
    public function approve(int $jobId, int $approverId): array
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            return ['ok' => false, 'message' => "Job #{$jobId} was not found.", 'job' => null];
        }

        $status = $job['status'] ?? '';
        if ($status !== ConfigJobModel::STATUS_AWAITING_APPROVAL) {
            $message = $status === ConfigJobModel::STATUS_PENDING || $status === ConfigJobModel::STATUS_RUNNING
                ? "Job #{$jobId} is already approved and running ({$status})."
                : "Job #{$jobId} cannot be approved from status '{$status}'.";

            return ['ok' => false, 'message' => $message, 'job' => $job];
        }

        $requester = (int) ($job['requested_by'] ?? 0);
        if ($requester === $approverId && !self::selfApprovalAllowed()) {
            return [
                'ok'      => false,
                'message' => "Job #{$jobId} must be approved by a second administrator: the requester cannot approve their own change. Enable configJobs.allowSelfApproval only where a second admin genuinely does not exist.",
                'job'     => $job,
            ];
        }

        $this->jobModel->update($jobId, [
            'status'      => ConfigJobModel::STATUS_PENDING,
            'approved_by' => $approverId,
            'approved_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok'      => true,
            'message' => "Job #{$jobId} approved. It will run on the next `jobs:run` pass.",
            'job'     => $this->jobModel->find($jobId),
        ];
    }

    /**
     * Re-applies a job's change to exactly the devices that drifted from it.
     *
     * This is deliberately a **new job** rather than a re-queue inside the old one, and that choice is
     * what makes "the same rules as a normal push" literally true rather than aspirational: it goes
     * through `createJob()`, so the approval gate, the canary selection, the derived payload mode and the
     * per-device target rows are the very same code the push page uses — not a parallel path that would
     * have to be kept in step by hand. It also keeps the original job's history honest: "it applied
     * everywhere, and three devices later stopped matching" is a fact worth being able to read.
     *
     * The subset is chosen from verification, not from status: only targets pfCentral has *read back* and
     * found wanting (`applied` + `drifted`). A device whose push failed is a different problem, and
     * re-applying to it is `retry`'s job.
     *
     * Drift is not cleared here. The source target keeps saying `drifted` until the reconcile has been
     * applied *and* verified, because marking a problem fixed at the moment somebody clicks a button is
     * exactly the kind of optimism this whole feature exists to avoid.
     *
     * `$onlyPfId` narrows the repair to one branch — the "put this one back" action an alert offers — and
     * `$onlyPfIds` to a named set of them, which is what a fleet-pattern alert needs: it speaks for the
     * devices that *undid this change while it was being watched*, and repairing it must not quietly
     * include a branch that stopped matching the change later. A later drift is a person's decision (see
     * `autoRollbackRefusal()`), so the pattern's action is aimed at its own members rather than at
     * everything the change is currently missing from.
     *
     * The subset is still taken from verification (`driftedTargets`), so a narrowed repair cannot be
     * pointed at a device that has not drifted: it is a *repair* that can be aimed, not a push that can be
     * smuggled in past the drift check.
     *
     * @param list<int>|null $onlyPfIds
     *
     * @return array{ok:bool, message:string, job_id:?int, devices:int, skipped:list<string>, requires_approval:bool, dispatched:bool}
     */
    public function reconcile(int $jobId, int $actorId, ?int $onlyPfId = null, ?array $onlyPfIds = null): array
    {
        $job = $this->jobModel->find($jobId);

        if (!$job) {
            return $this->reconcileRefusal("Job #{$jobId} was not found.");
        }

        $drifted = $this->driftedTargets($jobId);

        // The narrowings, in the order they can arrive: one device, or a named set of them.
        $onlyPfId  = $onlyPfId !== null && $onlyPfId > 0 ? $onlyPfId : null;
        $onlyPfIds = $onlyPfId === null && $onlyPfIds !== null
            ? array_values(array_unique(array_filter(array_map('intval', $onlyPfIds), static fn (int $id): bool => $id > 0)))
            : null;

        if ($onlyPfId !== null || ($onlyPfIds !== null && $onlyPfIds !== [])) {
            $wanted    = $onlyPfId !== null ? [$onlyPfId] : $onlyPfIds;
            $mentioned = array_map(static fn ($target) => (int) $target['pf_id'], $drifted);
            $drifted   = array_values(array_filter($drifted, static fn ($target) => in_array((int) $target['pf_id'], $wanted, true)));

            if ($drifted === []) {
                if ($onlyPfId !== null) {
                    return $this->reconcileRefusal(
                        in_array($onlyPfId, $mentioned, true)
                            ? "Device #{$onlyPfId}'s target for job #{$jobId} is not drifted any more, so there is nothing left to repair on it."
                            : "Device #{$onlyPfId} has not been found drifted from job #{$jobId}, so there is nothing to repair on it."
                    );
                }

                return $this->reconcileRefusal(
                    'None of the ' . count($wanted) . ' device(s) named are still drifted from job #' . $jobId . ', so there is nothing left to repair on them.'
                );
            }
        }

        if ($drifted === []) {
            return $this->reconcileRefusal("Job #{$jobId} has no drifted device to reconcile. Nothing applied to it has been read back and found wanting.");
        }

        $pfIds = array_map(static fn ($target) => (int) $target['pf_id'], $drifted);

        // A second repair may not *overlap* one already in flight — it would apply the same change twice to
        // the same device. It may run alongside it when the devices are disjoint, which is exactly what a
        // per-device repair from an alert needs: repairing branch B while branch A is still being repaired
        // is not a double-apply, and refusing it would make the alert's action useless for as long as
        // anything else was queued.
        $open = $this->overlappingReconcile($jobId, $pfIds);

        if ($open !== null) {
            return $this->reconcileRefusal(
                'Job #' . $jobId . ' already has a reconcile in flight (job #' . $open['id'] . ', ' . $open['status'] . ') covering device(s) #'
                    . implode(', #', $open['overlap']) . '. Let it finish or cancel it first.'
            );
        }

        $devices = [];
        $missing = [];

        foreach ($pfIds as $pfId) {
            $device = $this->inventoryModel->find($pfId);

            if ($device === null) {
                // Reported rather than silently dropped: a target row for a device that no longer exists
                // would just fail in the runner and look like a deployment problem.
                $missing[] = 'device #' . $pfId;

                continue;
            }

            $devices[] = $device;
        }

        if ($devices === []) {
            return $this->reconcileRefusal('None of the drifted devices still exist in the inventory, so there is nothing to re-apply the change to.');
        }

        // The pilot device is the original's canary when it drifted too, so the repair is proven on the
        // branch that already carried this change. Otherwise `createJob` picks one exactly as a push would
        // — and, as with any push, a single-device repair gets no canary because there is nothing to stage.
        $canaryPfId = (int) ($job['canary_pf_id'] ?? 0);
        $canary     = in_array($canaryPfId, array_map(static fn ($device) => (int) $device['id'], $devices), true);

        $newJobId = $this->createJob([
            'payload'             => (string) $job['rendered_payload'],
            'payload_type'        => (string) $job['payload_type'],
            'template_id'         => $job['template_id'] ?? null,
            'target_spec'         => [
                'type'          => 'reconcile',
                'source_job_id' => $jobId,
                'devices'       => count($devices),
            ],
            'requested_by'        => $actorId,
            'requires_approval'   => self::reconcileRequiresApproval($job, (string) $job['payload_type'], ['type' => 'reconcile']),
            'canary'              => true,
            'canary_pf_id'        => $canary ? $canaryPfId : null,
            'reconcile_of_job_id' => $jobId,
            // The same change deserves the same watching: a repair of a change that asked to be read for a
            // day is read for a day, because whatever undid it the first time is still out there.
            'watch_profile'       => (string) ($job['watch_profile'] ?? ConfigVerificationService::DEFAULT_WATCH_PROFILE),
        ], $devices);

        $newJob = $this->jobModel->find($newJobId);
        $gated  = (int) ($newJob['requires_approval'] ?? 0) === 1;
        $pilot  = (int) ($newJob['canary_pf_id'] ?? 0);
        $note   = $missing === [] ? '' : ' Skipped ' . count($missing) . ' device(s) no longer in the inventory: ' . implode(', ', array_slice($missing, 0, 5)) . '.';

        // Named when it is one device, counted when it is many: the alert's action repairs one branch, and
        // the toast that follows should say which branch rather than "queued for 1 drifted device(s)".
        if ($onlyPfId !== null && $onlyPfId > 0) {
            $message = 'Reconcile job #' . $newJobId . ' queued for ' . (string) ($devices[0]['name'] ?? ('device #' . $onlyPfId)) . ' only.';
        } else {
            $message = 'Reconcile job #' . $newJobId . ' queued for ' . count($devices) . ' drifted device(s)'
                . ($pilot > 0 ? ', starting with canary device #' . $pilot : '')
                . '.';
        }

        $message .= $note;

        // A gated change is not dispatched, exactly like a normal push: it waits for its second approver.
        if ($gated) {
            return [
                'ok'                => true,
                'message'           => $message . ' It re-applies a change that needed a second approver, so this does too — approve it to run.',
                'job_id'            => $newJobId,
                'devices'           => count($devices),
                'skipped'           => $missing,
                'requires_approval' => true,
                'dispatched'        => false,
            ];
        }

        $dispatched = $this->dispatchRunner($newJobId);

        return [
            'ok'                => true,
            'message'           => $message . ($dispatched ? '' : ' Waiting for the `jobs:run` runner (cron).'),
            'job_id'            => $newJobId,
            'devices'           => count($devices),
            'skipped'           => $missing,
            'requires_approval' => false,
            'dispatched'        => $dispatched,
        ];
    }

    /**
     * Reconciles every job that currently has drifted devices — the fleet-level "fix what is out of sync".
     *
     * One reconcile job per source job, because approval, canary and progress are all per-change: merging
     * unrelated changes into one job would make its approval meaningless.
     *
     * @return array{ok:bool, message:string, queued:list<array>, refusals:list<array>}
     */
    public function reconcileAll(int $actorId): array
    {
        $rows = $this->driftedBySourceJob();

        if ($rows === []) {
            return ['ok' => false, 'message' => 'No device is currently drifted, so there is nothing to reconcile.', 'queued' => [], 'refusals' => []];
        }

        $queued   = [];
        $refusals = [];

        foreach ($rows as $row) {
            $result = $this->reconcile((int) $row['job_id'], $actorId);

            if ($result['ok']) {
                $queued[] = $result;
            } else {
                $refusals[] = ['job_id' => (int) $row['job_id'], 'message' => $result['message']];
            }
        }

        $devices = array_sum(array_column($queued, 'devices'));

        return [
            'ok'       => $queued !== [],
            'message'  => $queued === []
                ? 'Nothing could be reconciled: ' . implode(' ', array_column($refusals, 'message'))
                : 'Queued ' . count($queued) . ' reconcile job(s) covering ' . $devices . ' device(s).'
                    . ($refusals === [] ? '' : ' ' . count($refusals) . ' job(s) were left alone.'),
            'queued'   => $queued,
            'refusals' => $refusals,
        ];
    }

    /**
     * Puts one device back to the configuration it was running before a job touched it.
     *
     * The mirror image of `reconcile()`: where a repair re-applies a change the device stopped
     * matching, a rollback *removes* it, restoring the pre-push snapshot the push itself saved. The two
     * are deliberately different jobs rather than one job with a flag, because they answer different
     * questions and leave different histories: "it applied everywhere, then three devices stopped
     * matching" is not the same fact as "this branch undid it minutes later, so we put it back".
     *
     * **One device per job, and that is not a simplification.** The payload *is* the snapshot, and each
     * device has its own — a job carrying a branch's configuration cannot be delivered to a second
     * branch without overwriting it with the wrong file. A fleet-wide "undo" would therefore be a
     * fleet-wide mis-configuration, so this method refuses to be aimed at more than one device by
     * construction: two devices are two jobs.
     *
     * The snapshot is resolved through the same `ConfigSnapshotStore::resolve()` the manual rollback
     * uses, so a queued rollback restores exactly what a person clicking Roll back would restore.
     *
     * The gate comes from `rollbackRequiresApproval()`: an ordinary one-branch XML push's rollback runs
     * unattended; one that undoes a script or a fleet-wide change waits for the second approver that
     * change needed. A gated rollback is *queued*, not refused — it appears in the approvals panel with
     * no requester, so any administrator can clear it.
     *
     * @param int      $sourceJobId the job whose change is being undone
     * @param int      $pfId        the device to put back
     * @param int|null $actorId     who asked; null for the automatic rollback the verification window raises
     *
     * @return array{ok:bool, message:string, job_id:?int, code:string, devices:int, requires_approval:bool, dispatched:bool}
     */
    public function rollback(int $sourceJobId, int $pfId, ?int $actorId = null): array
    {
        $job = $this->jobModel->find($sourceJobId);

        if (!$job) {
            return $this->rollbackRefusal("Job #{$sourceJobId} was not found.");
        }

        $target = $this->targetModel
            ->where('job_id', $sourceJobId)
            ->where('pf_id', $pfId)
            ->first();

        if (!$target) {
            return $this->rollbackRefusal("Device #{$pfId} was never a target of job #{$sourceJobId}, so it has nothing of that change to undo.");
        }

        $status = (string) ($target['status'] ?? '');

        if ($status !== JobTargetModel::STATUS_APPLIED) {
            return $this->rollbackRefusal("Device #{$pfId} is '{$status}' for job #{$sourceJobId}, not applied — there is no change on it to undo.");
        }

        if ((string) ($target['verify_status'] ?? '') !== JobTargetModel::VERIFY_DRIFTED) {
            return $this->rollbackRefusal("Device #{$pfId} has not been read back and found drifted from job #{$sourceJobId}, so there is nothing to roll back.");
        }

        // The decision table lives in one pure place; this method only supplies the facts it needs.
        $snapshotId  = (int) ($target['backup_id'] ?? 0) ?: null;
        $snapshotRef = (string) ($target['backup_file'] ?? '') ?: null;
        $refusal     = self::autoRollbackRefusal(
            $target,
            $job,
            // Classified against the *job's own* window: a change watched for a day is still a revert two
            // hours later, not an ordinary drift, because the window that witnessed it was still open.
            ConfigVerificationService::driftKind($target, null, ConfigVerificationService::watchScheduleFor($job)),
            self::autoRollbackEnabled(),
            $snapshotRef,
            $snapshotId
        );

        if ($refusal !== null) {
            return $this->rollbackRefusal($refusal);
        }

        $device = $this->inventoryModel->find($pfId);

        if ($device === null) {
            return $this->rollbackRefusal("Device #{$pfId} no longer exists in the inventory, so there is no device to put back.");
        }

        $snapshot = (new ConfigSnapshotStore())->resolve($device, $snapshotId, $snapshotRef);

        if (!$snapshot['ok']) {
            return $this->rollbackRefusal($snapshot['message']);
        }

        $deviceName = (string) ($device['name'] ?? ('device #' . $pfId));

        $spec = [
            'type'          => 'rollback',
            'source_job_id' => $sourceJobId,
            'devices'       => 1,
            'snapshot'      => $snapshot['file'],
        ];

        $newJobId = $this->createJob([
            // The payload *is* the snapshot — a complete config.xml, the document the device was running
            // before the push. That is what makes a rollback an ordinary job: the transports, the
            // replace-mode full-document guard, the read-back and the deploy history it inherits are the
            // ones already proven against real devices, not a second path through the runner that would
            // have to be kept in step with them.
            'payload'             => (string) $snapshot['contents'],
            'payload_type'        => 'xml',
            'template_id'         => $job['template_id'] ?? null,
            'target_spec'         => $spec,
            'requested_by'        => $actorId,
            'requires_approval'   => self::rollbackRequiresApproval($job, $spec),
            // A one-device job has nothing to stage, so a canary would be ceremony. The change being
            // undone was the canary-proven one; this puts the branch back to what it ran before it.
            'canary'              => false,
            'rollback_of_job_id'  => $sourceJobId,
            // And it inherits the source's window for the same reason a repair does: if that change was
            // worth reading back for a day, then so is the configuration it was rolled back from — a
            // restore that is itself undone is the failure this loop is here to catch.
            'watch_profile'       => (string) ($job['watch_profile'] ?? ConfigVerificationService::DEFAULT_WATCH_PROFILE),
        ], [$device]);

        $newJob = $this->jobModel->find($newJobId);
        $gated  = (int) ($newJob['requires_approval'] ?? 0) === 1;

        // The once-only guard is written after the job exists, because it names the job. A failure here
        // is logged rather than fatal: the rollback is already queued and is the more important half, and
        // the caller still reports what happened.
        try {
            $this->targetModel->update((int) $target['id'], ['rollback_job_id' => $newJobId]);
        } catch (\Throwable $e) {
            log_message('error', 'Rollback job #' . $newJobId . ' could not be recorded on target #' . $target['id'] . ': ' . $e->getMessage());
        }

        $message = 'Rollback job #' . $newJobId . ' queued for ' . $deviceName
            . ', restoring ' . ($snapshot['file'] ?? 'its pre-push snapshot') . ' from before job #' . $sourceJobId . '.';

        if ($gated) {
            return [
                'ok'                => true,
                'message'           => $message . ' It undoes a change that needed a second approver, so it needs one too — approve it to run.',
                'job_id'            => $newJobId,
                'code'              => 'awaiting_approval',
                'devices'           => 1,
                'requires_approval' => true,
                'dispatched'        => false,
            ];
        }

        $dispatched = $this->dispatchRunner($newJobId);

        return [
            'ok'                => true,
            'message'           => $message . ($dispatched ? '' : ' Waiting for the `jobs:run` runner (cron).'),
            'job_id'            => $newJobId,
            'code'              => 'queued',
            'devices'           => 1,
            'requires_approval' => false,
            'dispatched'        => $dispatched,
        ];
    }

    /**
     * @return array{ok:false, message:string, job_id:null, code:string, devices:int, requires_approval:false, dispatched:false}
     */
    private function rollbackRefusal(string $message): array
    {
        return [
            'ok'                => false,
            'message'           => $message,
            'job_id'            => null,
            'code'              => 'refused',
            'devices'           => 0,
            'requires_approval' => false,
            'dispatched'        => false,
        ];
    }

    /**
     * Targets that were applied and whose last read did not match the job.
     *
     * Targets already handed to a rollback are excluded, and that exclusion is a safety rule rather
     * than a display preference: the repair for a drift is "re-apply this change", and a device we have
     * just put back to its pre-push configuration is drifting precisely *because* the change is gone.
     * Offering it as a repair would quietly re-apply the very change the revert was rolled back from.
     *
     * @return list<array>
     */
    public function driftedTargets(int $jobId): array
    {
        return $this->targetModel
            ->where('job_id', $jobId)
            ->where('status', JobTargetModel::STATUS_APPLIED)
            ->where('verify_status', JobTargetModel::VERIFY_DRIFTED)
            ->where('rollback_job_id', null)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /**
     * Source jobs with at least one drifted target, with the count.
     *
     * Rolled-back targets are excluded here too, so the fleet-wide repair and the per-job repair cannot
     * disagree about whether there is anything left to do.
     *
     * @return list<array>
     */
    public function driftedBySourceJob(): array
    {
        return db_connect()
            ->table('job_targets')
            ->select('job_id, COUNT(*) AS drifted')
            ->where('status', JobTargetModel::STATUS_APPLIED)
            ->where('verify_status', JobTargetModel::VERIFY_DRIFTED)
            ->where('rollback_job_id', null)
            ->groupBy('job_id')
            ->orderBy('job_id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * A reconcile for this job that is not finished yet, if there is one.
     *
     * @return array|null
     */
    /**
     * An in-flight repair for this job that already covers any of these devices, with the overlap named.
     *
     * Overlap rather than "any repair at all", because the thing being prevented is the same change applied
     * twice to the same device — not a second repair existing. Two repairs for disjoint devices are two
     * repairs, and each gets its own canary, approval and history.
     *
     * @param  list<int> $pfIds the devices this repair is about to cover
     * @return array{id:int, status:string, overlap:list<int>}|null
     */
    private function overlappingReconcile(int $jobId, array $pfIds): ?array
    {
        $open = $this->jobModel
            ->where('reconcile_of_job_id', $jobId)
            ->whereNotIn('status', ConfigJobModel::TERMINAL_STATUSES)
            ->orderBy('id', 'DESC')
            ->findAll();

        foreach ($open as $repair) {
            $covered = array_map(
                static fn ($target) => (int) $target['pf_id'],
                $this->targetModel->where('job_id', (int) $repair['id'])->findAll()
            );

            $overlap = array_values(array_intersect($covered, $pfIds));

            if ($overlap !== []) {
                return ['id' => (int) $repair['id'], 'status' => (string) $repair['status'], 'overlap' => $overlap];
            }
        }

        return null;
    }

    /**
     * @return array{ok:false, message:string, job_id:null, devices:int, skipped:list<string>, requires_approval:false, dispatched:false}
     */
    private function reconcileRefusal(string $message): array
    {
        return [
            'ok'                => false,
            'message'           => $message,
            'job_id'            => null,
            'devices'           => 0,
            'skipped'           => [],
            'requires_approval' => false,
            'dispatched'        => false,
        ];
    }

    /**
     * Abandons a job that has not started fanning out. Queued targets are closed as skipped
     * so nothing is left open for the runner to pick up.
     *
     * @return array{ok:bool, message:string, job:?array}
     */
    public function cancel(int $jobId, int $actorId): array
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            return ['ok' => false, 'message' => "Job #{$jobId} was not found.", 'job' => null];
        }

        $status = $job['status'] ?? '';
        if (in_array($status, [ConfigJobModel::STATUS_COMPLETED, ConfigJobModel::STATUS_FAILED, ConfigJobModel::STATUS_CANCELED], true)) {
            return ['ok' => false, 'message' => "Job #{$jobId} is already finished ('{$status}').", 'job' => $job];
        }

        $reason = 'Canceled before completion by user #' . $actorId . '.';
        $halted = $this->skipUntouchedTargets($jobId, $reason);

        $this->jobModel->update($jobId, [
            'status'      => ConfigJobModel::STATUS_CANCELED,
            'last_error'  => $reason,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'ok'      => true,
            'message' => "Job #{$jobId} canceled; {$halted} device(s) abandoned.",
            'job'     => $this->jobModel->find($jobId),
        ];
    }

    /**
     * Closes a job's untouched targets — queued rows, and rows already armed for a device agent.
     *
     * Un-arming 'delivered' rows is not cosmetic: a job canceled after it was armed, or a fleet
     * rollout halted by a failed canary, must not leave work sitting in a device's pickup queue
     * where it could still be collected and applied.
     *
     * Targets someone is actively working ('running') are left alone.
     */
    public function skipUntouchedTargets(int $jobId, string $reason): int
    {
        $rows = $this->targetModel
            ->where('job_id', $jobId)
            ->whereIn('status', JobTargetModel::UNTOUCHED_STATUSES)
            ->findAll();

        foreach ($rows as $row) {
            $this->targetModel->update((int) $row['id'], [
                'status' => JobTargetModel::STATUS_SKIPPED,
                'error'  => mb_substr($reason, 0, 500),
            ]);
        }

        return count($rows);
    }

    /**
     * Tallies a set of job_targets rows. Public and static so the dashboard can reuse the exact
     * same accounting the console and the runner's final status decision use.
     *
     * @param list<array> $targets
     *
     * @return array{total:int, queued:int, running:int, applied:int, failed:int, skipped:int, other:int}
     */
    public static function countTargets(array $targets): array
    {
        $counts = [
            'total'   => count($targets),
            'queued'  => 0,
            'running' => 0,
            'applied' => 0,
            'failed'  => 0,
            'skipped' => 0,
            'other'   => 0,
        ];

        foreach ($targets as $target) {
            $status = $target['status'] ?? '';
            if ($status === JobTargetModel::STATUS_QUEUED) {
                $counts['queued']++;
            } elseif ($status === JobTargetModel::STATUS_RUNNING || $status === JobTargetModel::STATUS_DELIVERED) {
                $counts['running']++;
            } elseif ($status === JobTargetModel::STATUS_APPLIED) {
                $counts['applied']++;
            } elseif ($status === JobTargetModel::STATUS_FAILED) {
                $counts['failed']++;
            } elseif ($status === JobTargetModel::STATUS_SKIPPED) {
                $counts['skipped']++;
            } else {
                $counts['other']++;
            }
        }

        return $counts;
    }

    /**
     * Paged job list with per-job target progress, for the jobs dashboard.
     *
     * Progress is aggregated in SQL rather than loaded per job: a dashboard should not issue
     * one query per row to draw a progress bar.
     *
     * @param array{status?:string, q?:string} $filters
     *
     * @return array{rows:list<array>, total:int, page:int, per_page:int, pages:int}
     */
    public function listJobs(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $db      = db_connect();
        $perPage = max(1, min(100, $perPage));
        $page    = max(1, $page);
        $status  = trim((string) ($filters['status'] ?? ''));
        $search  = trim((string) ($filters['q'] ?? ''));

        $count = $db->table('config_jobs');
        $this->applyJobFilters($count, $status, $search);
        $total = $count->countAllResults();

        $builder = $db->table('config_jobs')
            ->select(
                'config_jobs.id, config_jobs.batch_id, config_jobs.payload_type, config_jobs.payload_mode,'
                . ' config_jobs.target_spec,'
                . ' config_jobs.status, config_jobs.requires_approval, config_jobs.requested_by,'
                . ' config_jobs.approved_by, config_jobs.approved_at, config_jobs.canary_pf_id,'
                . ' config_jobs.last_error, config_jobs.created_at, config_jobs.started_at,'
                . ' config_jobs.finished_at, COUNT(job_targets.id) AS total,'
                . " SUM(CASE WHEN job_targets.status = 'applied' THEN 1 ELSE 0 END) AS applied,"
                . " SUM(CASE WHEN job_targets.status = 'failed' THEN 1 ELSE 0 END) AS failed,"
                . " SUM(CASE WHEN job_targets.status = 'skipped' THEN 1 ELSE 0 END) AS skipped,"
                . " SUM(CASE WHEN job_targets.status = 'queued' THEN 1 ELSE 0 END) AS queued,"
                . " SUM(CASE WHEN job_targets.status IN ('running', 'delivered') THEN 1 ELSE 0 END) AS in_flight,"
                // Drifted devices are counted per job so the row can offer the repair only where there is
                // something to repair — and so the operator sees *how many* before clicking it. A target
                // already put back by a rollback is not counted: there is nothing left to repair on it,
                // and the repair would re-apply the change the rollback removed.
                . " SUM(CASE WHEN job_targets.verify_status = 'drifted' AND job_targets.rollback_job_id IS NULL THEN 1 ELSE 0 END) AS drifted,"
                . ' config_jobs.reconcile_of_job_id, config_jobs.rollback_of_job_id'
            )
            ->join('job_targets', 'job_targets.job_id = config_jobs.id', 'left')
            ->groupBy('config_jobs.id')
            ->orderBy('config_jobs.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage);

        $this->applyJobFilters($builder, $status, $search);
        $rows = $builder->get()->getResultArray();

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $total === 0 ? 1 : (int) ceil($total / $perPage),
        ];
    }

    /**
     * @param \CodeIgniter\Database\BaseBuilder $builder
     */
    private function applyJobFilters($builder, string $status, string $search): void
    {
        if ($status !== '') {
            $builder->where('config_jobs.status', $status);
        }

        if ($search !== '') {
            // Match a job id, or a batch id prefix — the two handles an operator has to hand.
            $builder->groupStart()
                ->like('config_jobs.batch_id', $search, 'after');

            if (ctype_digit($search)) {
                $builder->orWhere('config_jobs.id', (int) $search);
            }

            $builder->groupEnd();
        }
    }

    /**
     * Counts of jobs per status, for the dashboard tiles.
     *
     * @return array{total:int, by_status:array<string,int>}
     */
    public function jobSummary(): array
    {
        $rows = db_connect()
            ->table('config_jobs')
            ->select('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->getResultArray();

        $byStatus = [];
        $total    = 0;

        foreach ($rows as $row) {
            $byStatus[(string) $row['status']] = (int) $row['total'];
            $total += (int) $row['total'];
        }

        return ['total' => $total, 'by_status' => $byStatus, 'verify' => $this->verifyTally()];
    }

    /**
     * How the fleet's applied targets stand up to verification.
     *
     * Counted across all jobs, not this page: the point of the number is "is anything out there
     * drifting right now", which is a question about the fleet, and a per-page figure would answer
     * "did anything drift on page 3".
     *
     * @return array{applied:int, matched:int, drifted:int, unchecked:int}
     */
    private function verifyTally(): array
    {
        try {
            $row = db_connect()
                ->table('job_targets')
                ->select('SUM(status = \'applied\') AS applied')
                ->select('SUM(status = \'applied\' AND verify_status = \'matched\') AS matched')
                ->select('SUM(verify_status = \'drifted\') AS drifted')
                ->select('SUM(status = \'applied\' AND verify_status IN (\'pending\', \'unavailable\')) AS unchecked')
                ->get()
                ->getRowArray();
        } catch (\Throwable $e) {
            log_message('error', 'Verification tally failed: ' . $e->getMessage());

            return ['applied' => 0, 'matched' => 0, 'drifted' => 0, 'unchecked' => 0];
        }

        return [
            'applied'   => (int) ($row['applied'] ?? 0),
            'matched'   => (int) ($row['matched'] ?? 0),
            'drifted'   => (int) ($row['drifted'] ?? 0),
            'unchecked' => (int) ($row['unchecked'] ?? 0),
        ];
    }

    /**
     * One job with its per-device detail, for the dashboard's expandable row.
     *
     * @return array{job:array, targets:list<array>, counts:array}|null
     */
    public function jobDetail(int $jobId): ?array
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            return null;
        }

        $targets = db_connect()
            ->table('job_targets')
            ->select('job_targets.id, job_targets.pf_id, job_targets.status, job_targets.is_canary,'
                . ' job_targets.attempts, job_targets.delivered_at, job_targets.applied_at,'
                . ' job_targets.backup_id, job_targets.backup_file, job_targets.error,'
                . ' job_targets.verify_status, job_targets.verified_at, job_targets.verify_detail, job_targets.verify_attempts,'
                . ' job_targets.watch_started_at, job_targets.watch_until, job_targets.watch_next_at, job_targets.watch_checks,'
                . ' job_targets.watch_via, job_targets.rollback_job_id,'
                . ' job_targets.duration_ms, job_targets.updated_at,'
                . ' pf_inventory.name AS device_name, pf_inventory.man_ip,'
                . ' pf_inventory.groups AS device_group')
            ->join('pf_inventory', 'pf_inventory.id = job_targets.pf_id', 'left')
            ->where('job_targets.job_id', $jobId)
            ->orderBy('job_targets.id', 'ASC')
            ->get()
            ->getResultArray();

        // Whether a device is being watched right now is decided by the service that runs the watch, so
        // the badge on this row and the cron's log line cannot disagree about it — and the job travels with
        // it, because *how long* the watch lasts is this change's choice and not a system constant.
        foreach ($targets as $index => $target) {
            $targets[$index]['watch'] = ConfigVerificationService::watchSummary($target, null, $job);
        }

        return [
            'job'     => $job,
            'targets' => $targets,
            'counts'  => self::countTargets($targets),
            // How long this change is watched for, resolved once here: it belongs to the job, and the badge
            // on each device row and the job's own description should not be able to disagree about it.
            'window'  => [
                'profile'       => ConfigVerificationService::normaliseWatchProfile($job['watch_profile'] ?? null),
                'profile_label' => ConfigVerificationService::watchProfileLabel($job['watch_profile'] ?? null),
                'total'         => count(ConfigVerificationService::watchScheduleFor($job)),
            ],
        ];
    }

    /**
     * Re-queues whatever part of a failed job did not apply.
     *
     * Targets are reset rather than the job being re-created: the retry is the *same* change, so
     * it keeps the same payload, batch id and approval record. 'skipped' rows are reset too — they
     * are a canary's untouched remainder, and a retry must re-prove the change on the pilot device
     * before the fleet is touched again, which the runner's canary phase does on its own.
     *
     * Attempts are zeroed so a target abandoned at the attempt cap gets a genuine fresh start.
     *
     * @return array{ok:bool, message:string, requeued:int}
     */
    public function retry(int $jobId, int $actorId): array
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            return ['ok' => false, 'message' => "Job #{$jobId} was not found.", 'requeued' => 0];
        }

        $status = (string) ($job['status'] ?? '');

        $refusals = [
            ConfigJobModel::STATUS_AWAITING_APPROVAL => 'it is still waiting for approval — approve or cancel it instead.',
            ConfigJobModel::STATUS_RUNNING           => 'it is still in flight; the runner will finish it or reclaim it.',
            ConfigJobModel::STATUS_CANCELED          => 'it was canceled — queue a new job instead.',
            ConfigJobModel::STATUS_COMPLETED         => 'it already applied everywhere, so there is nothing to retry.',
        ];

        if (isset($refusals[$status])) {
            return ['ok' => false, 'message' => "Job #{$jobId} cannot be retried: " . $refusals[$status], 'requeued' => 0];
        }

        if ($status !== ConfigJobModel::STATUS_FAILED) {
            return ['ok' => false, 'message' => "Job #{$jobId} cannot be retried from status '{$status}'.", 'requeued' => 0];
        }

        $rows = $this->targetModel
            ->where('job_id', $jobId)
            ->whereIn('status', [JobTargetModel::STATUS_FAILED, JobTargetModel::STATUS_SKIPPED])
            ->findAll();

        if ($rows === []) {
            return ['ok' => false, 'message' => "Job #{$jobId} has no failed or untouched devices to retry.", 'requeued' => 0];
        }

        foreach ($rows as $row) {
            $this->targetModel->update((int) $row['id'], [
                'status'       => JobTargetModel::STATUS_QUEUED,
                'attempts'     => 0,
                'error'        => null,
                'applied_at'   => null,
                'delivered_at' => null,
                'duration_ms'  => null,
                'result_log'   => null,
            ]);
        }

        $this->jobModel->update($jobId, [
            'status'      => ConfigJobModel::STATUS_PENDING,
            'last_error'  => 'Retried by user #' . $actorId . ' at ' . date('Y-m-d H:i:s') . '.',
            'finished_at' => null,
        ]);

        $message = "Job #{$jobId} re-queued: " . count($rows) . ' device(s) will be attempted again';
        if (!empty($job['canary_pf_id'])) {
            $message .= ', starting with the canary device';
        }

        return ['ok' => true, 'message' => $message . '.', 'requeued' => count($rows)];
    }

    /**
     * Jobs waiting on a second approver, for the approvals panel.
     *
     * @return list<array>
     */
    public function pendingApprovals(int $limit = 25): array
    {
        return db_connect()
            ->table('config_jobs')
            // A rollback can be held here too — an undo of a change that needed a second approver — and the
            // panel has to be able to say so: "xml / replace" would read like an ordinary push, and the
            // approver is being asked to authorise a change to a branch either way.
            ->select('config_jobs.id, config_jobs.batch_id, config_jobs.payload_type, config_jobs.payload_mode, config_jobs.target_spec, config_jobs.requested_by, config_jobs.created_at, config_jobs.canary_pf_id, config_jobs.rollback_of_job_id, COUNT(job_targets.id) AS device_count')
            ->join('job_targets', 'job_targets.job_id = config_jobs.id', 'left')
            ->where('config_jobs.status', ConfigJobModel::STATUS_AWAITING_APPROVAL)
            ->groupBy('config_jobs.id')
            ->orderBy('config_jobs.id', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Job + targets with device names, for the polling endpoint and the terminal view.
     */
    public function jobStatus(int $jobId): ?array
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            return null;
        }

        $targets = db_connect()
            ->table('job_targets')
            ->select('job_targets.*, pf_inventory.name AS device_name, pf_inventory.man_ip')
            ->join('pf_inventory', 'pf_inventory.id = job_targets.pf_id', 'left')
            ->where('job_targets.job_id', $jobId)
            ->orderBy('job_targets.id', 'ASC')
            ->get()
            ->getResultArray();

        $counts = self::countTargets($targets);

        $status = $job['status'] ?? '';

        return [
            'job'      => $job,
            'targets'  => $targets,
            'counts'   => $counts,
            'finished' => in_array($status, ConfigJobModel::TERMINAL_STATUSES, true),
            // Awaiting approval is not 'finished' — it is stuck until a human acts, and the
            // console must say so instead of polling a job that will never start.
            'blocked'  => $status === ConfigJobModel::STATUS_AWAITING_APPROVAL,
        ];
    }

    /**
     * Recomputes a job's terminal status from its targets.
     */
    public function refreshJobStatus(int $jobId): string
    {
        $job = $this->jobModel->find($jobId);
        if (!$job) {
            return '';
        }

        // Never recompute a job out of an approval hold: its targets are all still 'queued',
        // which would otherwise look like 'running' and slip it past the gate.
        if (($job['status'] ?? '') === ConfigJobModel::STATUS_AWAITING_APPROVAL) {
            return ConfigJobModel::STATUS_AWAITING_APPROVAL;
        }

        $rows = $this->targetModel->where('job_id', $jobId)->findAll();

        $open     = 0;
        $failed   = 0;
        $finished = 0;

        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            if (in_array($status, JobTargetModel::OPEN_STATUSES, true)) {
                $open++;
            } else {
                $finished++;
                if ($status === JobTargetModel::STATUS_FAILED) {
                    $failed++;
                }
            }
        }

        if ($open > 0) {
            $status = ConfigJobModel::STATUS_RUNNING;
        } elseif ($rows !== [] && $failed === 0 && $finished > 0) {
            $status = ConfigJobModel::STATUS_COMPLETED;
        } elseif ($failed > 0) {
            $status = ConfigJobModel::STATUS_FAILED;
        } else {
            $status = $this->jobModel->find($jobId)['status'] ?? ConfigJobModel::STATUS_PENDING;
        }

        $data = ['status' => $status];
        if (!in_array($status, [ConfigJobModel::STATUS_RUNNING], true)) {
            $data['finished_at'] = $job['finished_at'] ?: date('Y-m-d H:i:s');
        }

        $this->jobModel->update($jobId, $data);

        return $status;
    }

    /**
     * Best-effort background dispatch of the runner so the first attempt starts immediately.
     *
     * Returns false when the environment cannot spawn a detached process — jobs then simply
     * wait for the cron runner, which is the reliable path.
     */
    public function dispatchRunner(int $jobId): bool
    {
        if (!$this->autoDispatchEnabled()) {
            return false;
        }

        $php   = PHP_BINARY;
        $spark = ROOTPATH . 'spark';

        if (!is_file($spark)) {
            return false;
        }

        $base = escapeshellarg($php) . ' ' . escapeshellarg($spark) . ' jobs:run --job=' . (int) $jobId;

        try {
            if (DIRECTORY_SEPARATOR === '\\') {
                if (!function_exists('popen')) {
                    return false;
                }
                // Windows: start detached, discard output.
                $handle = @popen('start /B "" ' . $base . ' > NUL 2>&1', 'r');
                if ($handle === false) {
                    return false;
                }
                @pclose($handle);
                return true;
            }

            if (!function_exists('exec')) {
                return false;
            }
            @exec($base . ' > /dev/null 2>&1 &');
            return true;
        } catch (\Throwable $e) {
            log_message('warning', 'Config job auto-dispatch failed: ' . $e->getMessage());
            return false;
        }
    }

    private function autoDispatchEnabled(): bool
    {
        $value = env('configJobRunner.autoDispatch');

        return $value === null ? true : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
