<?php

namespace App\Services\ConfigPush;

use App\Models\ConfigAlertModel;
use App\Models\ConfigJobModel;
use App\Models\PfInventoryModel;

/**
 * Turns "this device drifted" into an alert a human actually receives.
 *
 * The verification layer already knows drift when it sees it, and it records the fact on the target.
 * That is not the same as telling anybody, which is the whole problem this service exists to solve:
 * a device that quietly reverted overnight is invisible until somebody opens a job's detail table on
 * the off-chance. So drift becomes a row in `config_alerts`, visible in the UI, and an email.
 *
 * Three properties matter more than anything else here, and each one is the difference between an
 * alert people act on and one they filter into a folder:
 *
 *  1. **A drift alerts once.** The alert is keyed on the target *and* on a fingerprint of the drift
 *     detail, so re-checking the same unchanged difference — which the hourly sweep, a manual
 *     Re-check, and `--all` all do — raises nothing new. An alert per sweep would train everyone to
 *     ignore the sender within a day.
 *  2. **A *different* drift alerts again.** If the difference changes, the old alert is resolved as
 *     superseded and a new one is raised. Silencing that would hide a second, unrelated change behind
 *     the first one's fingerprint.
 *  3. **The alert outlives the email.** The row is stored before any mail is attempted, so a mail
 *     outage cannot lose it: the alert is open and visibly un-notified, and the sweep retries up to
 *     `MAX_NOTIFY_ATTEMPTS` — after which it stays open with the reason recorded, rather than being
 *     quietly dropped or retried forever.
 *
 * And it never throws. Alerting is diagnostics on top of a deployment that already happened; a
 * logging or mail problem must not be able to fail the thing it was meant to report on.
 */
class ConfigAlertService
{
    /** How long an alert's email is retried before it is left as visibly unsent. */
    public const MAX_NOTIFY_ATTEMPTS = ConfigAlertMailer::MAX_NOTIFY_ATTEMPTS;

    public const RESOLVED_MATCHED    = 'matched';
    public const RESOLVED_SUPERSEDED = 'superseded';
    public const RESOLVED_DISMISSED  = 'dismissed';

    /**
     * The revert was answered by putting the device back on its pre-push snapshot.
     *
     * Deliberately not `matched`: the device does not match the job again — the change was removed, and
     * calling that a match would say a revert "fixed itself" when what happened is that we undid it.
     */
    public const RESOLVED_ROLLED_BACK = 'rolled_back';

    /**
     * Every device in a fleet pattern has been accounted for, so the summary alert closes.
     *
     * Deliberately not `matched`: what closed is the *grouping*, and each member carries its own answer
     * (matched, rolled back, superseded, dismissed). `cleared` says "nothing is left to group", which is
     * the only thing this row ever claimed.
     */
    public const RESOLVED_CLEARED = 'cleared';

    /** The change was undone while the verification window was still watching it. */
    public const KIND_REVERT = ConfigAlertModel::KIND_REVERT;

    /** The device stopped matching a change whose window had already closed. */
    public const KIND_DRIFT = ConfigAlertModel::KIND_DRIFT;

    /** One change, undone on several devices inside the window it asked for. */
    public const KIND_REVERT_PATTERN = ConfigAlertModel::KIND_REVERT_PATTERN;

    /**
     * How many devices must undo one change before it is one story rather than N coincidences.
     *
     * Two is not enough. Two branches undoing a change a few minutes apart is a canary pair reporting
     * what the rest of the fleet would have reported, and it is also the most common shape of a genuine
     * local problem — a pair of devices sharing a package sync, a pair behind one upstream. Three
     * devices inside one window is a pattern: it is no longer plausible that three boxes independently
     * decided to undo the same change.
     */
    public const PATTERN_MIN_DEVICES = 3;

    /**
     * How long a revert's email waits before it is sent, unless a fleet pattern forms first.
     *
     * Grouping the alerts did not remove the messages that made the pattern recognisable: the first two
     * branches had already been emailed, one at a time, by the time the third arrived. Four branches undoing
     * one change produced three messages — two of them about devices that were part of a single fact about
     * the change. So the sending waits instead.
     *
     * Five minutes is one read cycle, not a round number: the verification window's first check is five
     * minutes after a push and `jobs:watch` runs every five, so every device of one fleet push is read
     * inside the same pass or the next one. A pattern therefore forms *within* the grace, which is the whole
     * point; a change undone on one branch gets its email one cycle later, which is the same order as the
     * latency between the push and the read that noticed it. `configJobs.revertEmailGrace=0` restores the
     * old behaviour (send immediately) for anyone who would rather have the message than the quiet.
     */
    public const REVERT_EMAIL_GRACE_MINUTES = 5;

    /**
     * How loud each kind is. A revert outranks a drift; nothing outranks a revert.
     *
     * Ordering rather than a flag, because the two kinds meet on one row: the same difference can be
     * raised quietly by the sweep and later turn out to have been a revert (a repaired device that
     * reverts again inside its new window), and the row has to be able to climb without a second alert
     * row describing the same difference.

    protected ConfigAlertModel $alertModel;
    protected ConfigAlertMailer $mailer;
    protected ConfigJobModel $jobModel;
    protected PfInventoryModel $inventoryModel;

    /** Whether this run may send email. Suppressed alerts are still recorded and still owed. */
    protected bool $emailEnabled = true;

    public function __construct(
        ?ConfigAlertModel $alertModel = null,
        ?ConfigAlertMailer $mailer = null,
        ?ConfigJobModel $jobModel = null,
        ?PfInventoryModel $inventoryModel = null
    ) {
        $this->alertModel     = $alertModel ?? new ConfigAlertModel();
        $this->mailer         = $mailer ?? new ConfigAlertMailer();
        $this->jobModel       = $jobModel ?? new ConfigJobModel();
        $this->inventoryModel = $inventoryModel ?? new PfInventoryModel();
    }

    /**
     * Records alerts without emailing them — a maintenance window, where you know what is changing.
     *
     * Note what this does *not* do: it leaves `notified_at` null and the attempt count untouched, so an
     * alert raised during a window is still owed an email and the next normal sweep sends it. Silencing
     * the mail must never be the same thing as losing the alert.
     */
    public function suppressEmail(bool $suppress = true): self
    {
        $this->emailEnabled = !$suppress;

        return $this;
    }

    private const SEVERITY = [
        self::KIND_DRIFT          => 1,
        self::KIND_REVERT         => 2,
        // The loudest thing this system can say about a change: it did not survive contact with the fleet.
        self::KIND_REVERT_PATTERN => 3,
    ];

    /**
     * Whether this kind is the loud one — one definition, borrowed from the row's own vocabulary.
     */
    public static function isRevert(?string $kind): bool
    {
        return ConfigAlertModel::isRevert($kind);
    }

    /** Whether this row speaks for several devices that undid the same change. */
    public static function isPattern(?string $kind): bool
    {
        return ConfigAlertModel::isPattern($kind);
    }

    /**
     * The louder of two kinds — the rule that stops an alert from being quietened.
     *
     * The sweep keeps re-reading a device that is still wrong, so the same difference is met again and
     * again; once it is known to have been a revert, a later sighting of it must not put the row back to
     * "ordinary drift" and make the loud one a one-off that scrolls away.
     *
     * @return array{kind:string, escalated:bool}
     */
    public static function escalate(?string $current, ?string $incoming): array
    {
        $current  = ConfigAlertModel::normaliseKind($current);
        $incoming = ConfigAlertModel::normaliseKind($incoming);
        $escalated = self::SEVERITY[$incoming] > self::SEVERITY[$current];

        return ['kind' => $escalated ? $incoming : $current, 'escalated' => $escalated];
    }

    /**
     * The one-line summary an operator scans in a list of alerts.
     *
     * The kind changes the sentence, not just a badge: "no longer matches job #12" is a statement about a
     * device that has drifted, while a revert is a statement about *what somebody did to it* and how soon
     * afterwards. The age comes from the same helper the runner logs, so the alert and the log line cannot
     * disagree about how long after the push the change was undone.
     *
     * @param array $target job_targets row (its `applied_at` is what the age is measured from)
     */
    public static function summary(array $target, array $job, array $device, string $kind, ?int $now = null): string
    {
        $deviceName = (string) ($device['name'] ?? ($target['device_name'] ?? ('device #' . ($target['pf_id'] ?? '?'))));
        $jobId      = (int) ($job['id'] ?? 0);
        $payload    = (string) ($job['payload_type'] ?? 'xml') . '/' . (string) ($job['payload_mode'] ?? 'replace');

        if (!self::isRevert($kind)) {
            return sprintf('%s no longer matches job #%d (%s)', $deviceName, $jobId, $payload);
        }

        $age = ConfigVerificationService::ageInMinutes($target['applied_at'] ?? null, $now);

        return sprintf(
            'REVERTED: %s undid job #%d %s (%s)',
            $deviceName,
            $jobId,
            $age !== null
                ? $age . ' minute(s) after it was applied'
                : 'inside the window it was applied under',
            $payload
        );
    }

    /** The configured grace, in minutes. Zero disables the deferral entirely. */
    public static function emailGraceMinutes(): int
    {
        $value = env('configJobs.revertEmailGrace');

        if ($value === null || trim((string) $value) === '') {
            return self::REVERT_EMAIL_GRACE_MINUTES;
        }

        return max(0, (int) $value);
    }

    /**
     * When this alert's email becomes due, or null when it is due at once. Pure.
     *
     * Deliberately *not* a uniform delay: only the kind that can join a pattern waits. A drift is never a
     * member — a pattern is about devices that undid a change while it was being watched — so deferring it
     * would buy nothing and cost latency on an alert about a device that has been wrong for days. A pattern
     * alert is the message we are trying to arrive at, so it is sent the moment it exists.
     *
     * @param string|null $kind       ConfigAlertModel kind
     * @param string|null $raisedAt   when the alert was raised (the clock the grace runs from)
     * @param int|null    $grace      override for tests; the configured grace otherwise
     */
    public static function emailDueAt(?string $kind, ?string $raisedAt, ?int $graceMinutes = null): ?string
    {
        if (self::isPattern($kind) || !ConfigAlertModel::isRevert($kind)) {
            return null;
        }

        $grace  = $graceMinutes ?? self::emailGraceMinutes();
        $raised = $raisedAt !== null ? strtotime($raisedAt) : false;

        if ($grace <= 0 || $raised === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $raised + ($grace * 60));
    }

    /**
     * What state this alert's email is in, in the words a person needs. Pure.
     *
     * Four states, and the difference between them is the difference between "nobody has been told" and
     * "we are about to":
     *
     *   sent      the email went out (and to whom is on the row)
     *   deferred  held on purpose, waiting to see whether this is one branch or the whole change
     *   owed      due and not yet sent — the next sweep's job; not a failure
     *   failed    tried up to the cap and still not delivered, with the reason recorded
     *
     * Collapsing `deferred` into "not emailed" would make the banner report a deliberate five-minute wait as
     * a mail failure, which is the kind of false alarm that teaches people to ignore the strip.
     *
     * @return array{state:string, label:string, minutes_until:?int}
     */
    public static function notifyState(array $alert, ?int $now = null): array
    {
        $now = $now ?? time();

        if (($alert['notified_at'] ?? null) !== null) {
            return ['state' => 'sent', 'label' => 'emailed', 'minutes_until' => null];
        }

        if ((int) ($alert['notify_attempts'] ?? 0) >= self::MAX_NOTIFY_ATTEMPTS) {
            return ['state' => 'failed', 'label' => 'could not be emailed', 'minutes_until' => null];
        }

        $due = ($alert['notify_after'] ?? null) !== null ? strtotime((string) $alert['notify_after']) : false;

        if ($due !== false && $due > $now) {
            $minutes = max(1, (int) ceil(($due - $now) / 60));

            return [
                'state'         => 'deferred',
                'label'         => 'emails in ~' . $minutes . ' min unless more devices undo the same change',
                'minutes_until' => $minutes,
            ];
        }

        return ['state' => 'owed', 'label' => 'not emailed yet', 'minutes_until' => null];
    }

    /**
     * What the shared "change" is, in the operator's words.
     *
     * A pattern alert has to name the thing that is wrong, and the job is not a useful name on its own:
     * `#12` says nothing about what was pushed. So the label carries what a person recognises — the kind of
     * payload and, when the change went to a group, the group — because the whole point of grouping the
     * reverts is that the *change* is the suspect, and the reader has to be able to see which change.
     *
     * Deliberately built from the job row rather than from any one member: the members are branches, and
     * the change is what they have in common.
     *
     * @param array $job config_jobs row
     */
    public static function changeLabel(array $job): string
    {
        $jobId = (int) ($job['id'] ?? 0);
        $type  = (string) ($job['payload_type'] ?? 'xml');
        $mode  = (string) ($job['payload_mode'] ?? 'replace');

        $label = sprintf('job #%d — %s', $jobId, ConfigVerificationReportService::payloadLabel($type, $mode));

        $spec = $job['target_spec'] ?? [];

        if (is_string($spec)) {
            $decoded = json_decode($spec, true);
            $spec    = is_array($decoded) ? $decoded : [];
        }

        $tags = [];

        if (is_array($spec)) {
            if (($spec['type'] ?? '') === 'group' && trim((string) ($spec['name'] ?? '')) !== '') {
                $tags[] = 'group "' . trim((string) $spec['name']) . '"';
            } elseif (($spec['type'] ?? '') === 'all') {
                $tags[] = 'the whole fleet';
            }

            $specType = (string) ($spec['type'] ?? '');

            if (in_array($specType, ['reconcile', 'rollback'], true)) {
                $tags[] = $specType;
            }
        }

        if ($tags !== []) {
            $label .= ' → ' . implode(' ', $tags);
        }

        return $label;
    }

    /**
     * The one-line summary of a fleet pattern: the change, and how many devices undid it.
     *
     * The count is the number an operator acts on, so it is always the *current* one and the scope is
     * always named: "4 of its 12 devices" says the change is failing on a third of the rollout, while
     * "all 4 devices" says every branch that got it has lost it. Those are different decisions, and one
     * sentence that said "4 devices" for both would hide which one it is. Pure.
     *
     * @param array $job   config_jobs row (the change)
     * @param int   $count how many devices have undone it right now
     * @param int   $total how many devices the change was pushed to
     */
    public static function patternSummary(array $job, int $count, int $total): string
    {
        $count = max(0, $count);
        $total = max($count, $total);

        $scope = $total > $count
            ? sprintf('%d of its %d device(s)', $count, $total)
            : sprintf('all %d of its device(s)', $count);

        return sprintf('REVERTED ON %d DEVICES: %s was undone on %s', $count, self::changeLabel($job), $scope);
    }

    /**
     * Why these reverts are not (yet) one fleet pattern, or null when they are.
     *
     * Pure, and the whole rule of the feature is in here. Every branch is a case where grouping would
     * tell the operator *less* than separate alerts:
     *
     *  1. **Too few devices.** One revert is a branch problem, and the ordinary alert already says so in
     *     the most specific words available (which device, how long after the push). Grouping two of them
     *     would replace two precise alerts with one vague one.
     *  2. **No change to name.** `job_id` is how the members are found and how the alert names the suspect;
     *     without it there is no such thing as "the same change", only a set of devices.
     */
    public static function patternRefusal(array $job, int $count): ?string
    {
        if ($count < self::PATTERN_MIN_DEVICES) {
            return sprintf(
                'Only %d device(s) have undone this change, so each is reported as its own revert: one or two ' .
                'branches undoing a change is a branch problem, and the per-device alert says more than a group would.',
                $count
            );
        }

        if ((int) ($job['id'] ?? 0) <= 0) {
            return 'The job behind these reverts no longer exists, so there is no shared change to name.';
        }

        return null;
    }

    /**
     * The identity of a pattern: the change it is about, and nothing else.
     *
     * One open pattern per job on purpose. A device is either inside the pattern or not — there is no
     * second dimension to key on, and a fingerprint that included the count or a time bucket would split
     * one unfolding event into several alerts, which is the failure this feature exists to fix.
     */
    public static function patternFingerprint(int $jobId): string
    {
        return sha1('revert-pattern:' . $jobId);
    }

    /**
     * One alert as the dashboard's banner consumes it, including what is being done about it.
     *
     * Here rather than in the controller because it is the same kind of decision as `summary()`: the
     * wording a person reads about an alert. Keeping it with the alert vocabulary also makes it testable
     * without a session, which matters — this row is what a page renders, and a page is exactly what the
     * CLI-only suite used to miss.
     *
     * @param array $alert            config_alerts row
     * @param array<int, string> $rollbackStatuses rollback job id => config_jobs.status, when known
     */
    public static function uiRow(array $alert, array $rollbackStatuses = []): array
    {
        $rollbackId = (int) ($alert['rollback_job_id'] ?? 0);
        $rollback   = $rollbackId > 0 ? self::rollbackState($rollbackStatuses[$rollbackId] ?? null) : null;

        return [
            'id'              => (int) $alert['id'],
            'job_id'          => ($alert['job_id'] ?? null) !== null ? (int) $alert['job_id'] : null,
            'target_id'       => ($alert['target_id'] ?? null) !== null ? (int) $alert['target_id'] : null,
            'pf_id'           => ($alert['pf_id'] ?? null) !== null ? (int) $alert['pf_id'] : null,
            'device'          => (string) ($alert['device_name'] ?? ''),
            'kind'            => ConfigAlertModel::normaliseKind($alert['kind'] ?? null),
            'summary'         => (string) ($alert['summary'] ?? ''),
            'detail'          => (string) ($alert['detail'] ?? ''),
            'raised_at'       => (string) ($alert['raised_at'] ?? ''),
            'notified_at'     => ($alert['notified_at'] ?? null) !== null ? (string) $alert['notified_at'] : null,
            'notified_to'     => (string) ($alert['notified_to'] ?? ''),
            'notify_error'    => ($alert['notify_error'] ?? null) !== null ? (string) $alert['notify_error'] : null,
            'notify_attempts' => (int) ($alert['notify_attempts'] ?? 0),
            // Whether the email went out, is being deliberately held, is simply owed, or failed — see
            // `notifyState()`. The banner needs all four: a five-minute deferral reported as a mail failure
            // would be the kind of false alarm that teaches people to ignore the strip.
            'notify_state'    => self::notifyState($alert)['state'],
            'notify_label'    => self::notifyState($alert)['label'],
            // Whether the revert has already been answered, and by what: this is what stops the row
            // offering "reconcile" next to a rollback that deliberately removed the change.
            'rollback_job_id' => $rollbackId > 0 ? $rollbackId : null,
            'rollback_label'  => $rollback['label'] ?? null,
            'rollback_tone'   => $rollback['tone'] ?? null,
            // A fleet pattern is a different shape of row: it has no target of its own, it carries a
            // device count, and the banner has to render it as one alert with the devices inside it.
            'is_pattern'      => ConfigAlertModel::isPattern($alert['kind'] ?? null),
            'device_count'    => (int) ($alert['device_count'] ?? 0),
            'grouped_into'    => ($alert['grouped_into'] ?? null) !== null ? (int) $alert['grouped_into'] : null,
        ];
    }

    /**
     * How to describe what the rollback answering an alert is doing. Pure.
     *
     * One definition, used by the email and by the banner, because "waiting for approval" and "already
     * applied" are the difference between an operator who has to act and one who does not. An unknown or
     * missing status reads as `queued`, which is the truthful thing to say about a job that exists and
     * has not been reported as finished.
     *
     * @return array{label:string, tone:string} tone: waiting|running|done|failed
     */
    public static function rollbackState(?string $status): array
    {
        return match (trim((string) $status)) {
            ConfigJobModel::STATUS_AWAITING_APPROVAL => ['label' => 'waiting for approval', 'tone' => 'waiting'],
            ConfigJobModel::STATUS_PENDING          => ['label' => 'queued', 'tone' => 'waiting'],
            ConfigJobModel::STATUS_RUNNING          => ['label' => 'running', 'tone' => 'running'],
            ConfigJobModel::STATUS_COMPLETED        => ['label' => 'applied', 'tone' => 'done'],
            ConfigJobModel::STATUS_FAILED           => ['label' => 'failed', 'tone' => 'failed'],
            ConfigJobModel::STATUS_CANCELED         => ['label' => 'cancelled', 'tone' => 'failed'],
            default                                 => ['label' => 'queued', 'tone' => 'waiting'],
        };
    }

    /**
     * The identity of a specific drift: the same difference must not alert twice, a different one must.
     *
     * Hashing the normalised detail rather than the device alone is what makes both halves true. The
     * detail is produced by the verification service and is deterministic for a given pair of configs,
     * so it is a faithful stand-in for "what is different" without storing either configuration.
     */
    public static function fingerprint(string $detail): string
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $detail)));

        return sha1($normalised);
    }

    /**
     * Raises the alert for one drifted target, unless this exact drift is already open.
     *
     * @param array  $target   job_targets row
     * @param array  $job      config_jobs row
     * @param array  $device   pf_inventory row
     * @param string $detail   the verification detail, in the operator's words
     * @param array|null $rollback the rollback that was just raised for this revert, if any:
     *                             `['queued' => bool, 'job_id' => ?int, 'gated' => bool, 'message' => string]`
     *
     * @return array{raised:bool, alert:?array, message:string, notified:bool}
     */
    public function raise(array $target, array $job, array $device, string $detail, string $kind = self::KIND_DRIFT, ?array $rollback = null): array
    {
        $targetId    = (int) ($target['id'] ?? 0);
        $fingerprint = self::fingerprint($detail);
        $jobId       = (int) ($job['id'] ?? 0);
        $deviceName  = (string) ($device['name'] ?? ($target['device_name'] ?? ('device #' . ($target['pf_id'] ?? '?'))));
        $kind        = ConfigAlertModel::normaliseKind($kind);

        try {
            $open = $this->alertModel
                ->where('target_id', $targetId)
                ->where('status', ConfigAlertModel::STATUS_OPEN)
                ->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'Drift alert lookup failed: ' . $e->getMessage());

            return ['raised' => false, 'alert' => null, 'message' => 'The alert could not be checked.', 'notified' => false];
        }

        foreach ($open as $existing) {
            if ((string) $existing['fingerprint'] !== $fingerprint) {
                continue;
            }

            // The same difference, already known. This is the common case — the sweep and every manual
            // re-check land here — and it must never produce a second email.
            $step = self::escalate($existing['kind'] ?? null, $kind);

            if (!$step['escalated']) {
                // Already known. The pattern is still refreshed rather than skipped: this is the path a
                // re-read of a device that already reverted takes, and it is the cheapest moment to
                // heal a member that was somehow left out of an existing pattern.
                $pattern = $this->refreshPattern($job);

                return [
                    'raised'    => false,
                    'alert'     => $existing,
                    'message'   => 'This exact difference is already an open alert.' . $this->patternNote($pattern),
                    'notified'  => $existing['notified_at'] !== null,
                    'escalated' => false,
                    'pattern'   => $pattern,
                ];
            }

            // The exception, and the only one: the same difference, now known to be a *revert*. That is
            // not the sweep seeing the same thing again, it is the same thing turning out to be the
            // urgent kind — and it can happen at most once, because the kind only ever climbs.
            return $this->escalateAlert($existing, $target, $job, $device, $step['kind'], $rollback);
        }

        // The device now differs in a *new* way: the old alert described something that is no longer
        // the case, so it is closed as superseded and this one takes over.
        foreach ($open as $existing) {
            $this->resolve((int) $existing['id'], 'verification', self::RESOLVED_SUPERSEDED);
        }

        $summary = self::summary($target, $job, $device, $kind);

        // The rollback is recorded on the row rather than folded into the summary, and for a reason that
        // matters: the summary column is 255 characters and the revert line already uses much of it, so an
        // appended sentence would be silently truncated on exactly the long device names this fleet has.
        // The id is also the more useful fact — the banner and the email can then say what the rollback's
        // *state* is, which a frozen sentence could not.
        $rollbackJobId = (int) ($rollback['job_id'] ?? 0);

        // A revert waits one read cycle before it is sent: if two more branches undo the same change in
        // that cycle, this alert is grouped into the pattern and never emailed at all — which is the only
        // way the fleet revert can arrive as *one* message rather than three.
        $raisedAt = date('Y-m-d H:i:s');
        $notifyAfter = self::emailDueAt($kind, $raisedAt);

        try {
            $alertId = (int) $this->alertModel->insert([
                'job_id'          => $jobId ?: null,
                'target_id'       => $targetId ?: null,
                'pf_id'           => (int) ($target['pf_id'] ?? 0) ?: null,
                'device_name'     => mb_substr($deviceName, 0, 150),
                'fingerprint'     => $fingerprint,
                'status'          => ConfigAlertModel::STATUS_OPEN,
                'summary'         => mb_substr($summary, 0, 255),
                'detail'          => mb_substr($detail, 0, 600),
                // The detail stays byte-for-byte what the verification produced: it is the fingerprint
                // source, and rewording it by kind would turn one drift into two alerts.
                'kind'            => $kind,
                'rollback_job_id' => $rollbackJobId > 0 ? $rollbackJobId : null,
                'raised_at'       => $raisedAt,
                'notify_after'    => $notifyAfter,
                'notify_attempts' => 0,
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'Drift alert could not be raised: ' . $e->getMessage());

            return ['raised' => false, 'alert' => null, 'message' => 'The drift alert could not be stored.', 'notified' => false];
        }

        $alert = $this->alertModel->find($alertId) ?: [];

        // The third branch to undo the same change is not a third alert: it is *one* alert about the
        // change, and this row becomes a member of it. Done before the email below, so a device that
        // arrives to complete a pattern does not send its own email and then be grouped — the operator
        // gets one message about the change, not one per branch plus a summary.
        $pattern = $kind === self::KIND_REVERT ? $this->refreshPattern($job) : null;

        if (!$this->emailEnabled) {
            return [
                'raised'    => true,
                'alert'     => $alert,
                'message'   => 'Alert recorded; email suppressed for this run and owed to the next one.' . $this->patternNote($pattern),
                'notified'  => false,
                'escalated' => false,
                'pattern'   => $pattern,
            ];
        }

        // A member of a pattern has no email of its own: the pattern's email carries the story, and a
        // message per branch is exactly what this feature removes. `grouped_into` is re-read rather than
        // trusted from the refresh result, because that is the fact the notification rule is built on.
        if ($this->isGrouped($alertId)) {
            return [
                'raised'    => true,
                'alert'     => $this->alertModel->find($alertId) ?: $alert,
                'message'   => 'Alert recorded and grouped into the fleet-pattern alert for this change; ' .
                    'the pattern carries the email so one change does not arrive as one message per branch.',
                'notified'  => false,
                'escalated' => false,
                'pattern'   => $pattern,
            ];
        }

        // Held, not sent: this is the deferral itself, and it is what stops a fleet-wide revert from
        // arriving as one message per branch. The alert is already visible and loud on the page.
        if ($notifyAfter !== null) {
            return [
                'raised'    => true,
                'alert'     => $this->alertModel->find($alertId) ?: $alert,
                'message'   => 'Alert recorded and its email held for ' . self::emailGraceMinutes() . ' minute(s): '
                    . 'if more devices undo this same change in that window it becomes one alert about the change '
                    . 'and this one is never sent. Otherwise the next sweep sends it.',
                'notified'  => false,
                'escalated' => false,
                'pattern'   => $pattern,
            ];
        }

        $result = $this->notifyAlert($alert, $job, $device);

        return [
            'raised'    => true,
            'alert'     => $result['alert'] ?? $alert,
            'message'   => $result['message'] . $this->patternNote($pattern),
            'notified'  => (bool) ($result['ok'] ?? false),
            'escalated' => false,
            'pattern'   => $pattern,
        ];
    }

    /**
     * The one-line note a caller appends to its own log line about the pattern, or an empty string.
     *
     * Kept here rather than composed at each call site so the runner's log line, the harness and the
     * service's return value cannot describe the same grouping three different ways.
     *
     * @param array|null $pattern as returned by `refreshPattern()`
     */
    private function patternNote(?array $pattern): string
    {
        if ($pattern === null || (int) ($pattern['id'] ?? 0) <= 0) {
            return '';
        }

        return sprintf(
            ' Grouped into fleet-pattern alert #%d, which now covers %d device(s) that undid this change%s.',
            (int) $pattern['id'],
            (int) ($pattern['devices'] ?? 0),
            ($pattern['created'] ?? false) ? ' — the pattern alert carries the email for all of them' : ''
        );
    }

    /**
     * Climbs an open alert to a louder kind, and emails the louder wording once.
     *
     * The email is re-armed by clearing `notified_at` rather than by loosening the retry cap: the attempt
     * counter is left alone, so a mail outage cannot restart the budget it has already spent. When email is
     * suppressed the notification state is left exactly as it is — the row's kind has already changed, so
     * whatever the next normal sweep sends will carry the revert wording anyway.
     *
     * @return array{raised:bool, alert:?array, message:string, notified:bool, escalated:bool}
     */
    private function escalateAlert(array $alert, array $target, array $job, array $device, string $kind, ?array $rollback = null): array
    {
        $alertId = (int) ($alert['id'] ?? 0);

        $data = [
            'kind'    => $kind,
            'summary' => mb_substr(self::summary($target, $job, $device, $kind), 0, 255),
        ];

        // An escalation that also found a rollback to raise must record it, or the email the escalation
        // sends would still say "nothing was changed or rolled back by us" while a rollback job exists.
        $rollbackJobId = (int) ($rollback['job_id'] ?? 0);

        if ($rollbackJobId > 0 && (int) ($alert['rollback_job_id'] ?? 0) !== $rollbackJobId) {
            $data['rollback_job_id'] = $rollbackJobId;
        }

        if ($this->emailEnabled) {
            $data['notified_at']  = null;
            $data['notify_error'] = null;
            // The louder email is deferred for the same reason the first one was: an escalation to a revert
            // is exactly the kind of alert that can turn out to belong to a fleet-wide change, and a
            // just-known-to-be-a-revert device is a candidate member the moment the row exists.
            $data['notify_after'] = self::emailDueAt($kind, (string) ($alert['raised_at'] ?? date('Y-m-d H:i:s')));
        }

        try {
            $this->alertModel->update($alertId, $data);
        } catch (\Throwable $e) {
            log_message('error', 'Drift alert could not be escalated: ' . $e->getMessage());

            return [
                'raised'    => false,
                'alert'     => $alert,
                'message'   => 'The alert could not be escalated to a revert.',
                'notified'  => $alert['notified_at'] !== null,
                'escalated' => false,
            ];
        }

        $updated = $this->alertModel->find($alertId) ?: $alert;

        // Climbing to a revert makes this row a candidate member: a change that turns out to have been
        // undone on this device *and* on two others is one story, whichever path discovered it.
        $pattern = $kind === self::KIND_REVERT ? $this->refreshPattern($job) : null;

        // Held rather than sent, and that order matters: an escalation that turns out to belong to a pattern
        // is grouped on the spot (above) and is never emailed at all.
        if ($this->emailEnabled && $kind === self::KIND_REVERT && !$this->isGrouped($alertId)) {
            return [
                'raised'    => true,
                'alert'     => $updated,
                'message'   => 'The same difference is now known to be a revert. Its louder email is held for '
                    . self::emailGraceMinutes() . ' minute(s): if other devices undo the same change it is grouped '
                    . 'into one alert about the change instead.' . $this->patternNote($pattern),
                'notified'  => false,
                'escalated' => true,
                'pattern'   => $pattern,
            ];
        }

        if (!$this->emailEnabled) {
            return [
                'raised'    => true,
                'alert'     => $updated,
                'message'   => 'The same difference is now known to be a revert; the alert is louder and its email is owed to the next run.' . $this->patternNote($pattern),
                'notified'  => false,
                'escalated' => true,
                'pattern'   => $pattern,
            ];
        }

        if ($this->isGrouped($alertId)) {
            return [
                'raised'    => true,
                'alert'     => $updated,
                'message'   => 'The same difference is now known to be a revert on this device, and it was grouped into the fleet-pattern alert for this change, which carries the email.' . $this->patternNote($pattern),
                'notified'  => false,
                'escalated' => true,
                'pattern'   => $pattern,
            ];
        }

        $result = $this->notifyAlert($updated, $job, $device);

        return [
            'raised'    => true,
            'alert'     => $result['alert'] ?? $updated,
            'message'   => 'The same difference is now known to be a revert, so the alert was escalated and the louder email sent. ' . $result['message'] . $this->patternNote($pattern),
            'notified'  => (bool) ($result['ok'] ?? false),
            'escalated' => true,
            'pattern'   => $pattern,
        ];
    }

    /**
     * Makes one job's reverts read as one story, and keeps that story current.
     *
     * Called on every revert and every resolution, because both change the answer to the only two
     * questions a pattern has: *is this a pattern yet*, and *how many devices are in it now*. Both are
     * answered from the same source of truth — the job's own open revert alerts — rather than from a
     * counter that four call sites would keep disagreeing about.
     *
     * The rule for forming one is `patternRefusal()` and nothing else; the rule for keeping it is that its
     * members are the truth. A member that was somehow left ungrouped is healed here (grouping is
     * idempotent), and a pattern whose last member has been repaired or rolled back closes itself — a
     * pattern never outlives the devices it speaks for.
     *
     * **The email is sent exactly once, and only for a new pattern.** The count in the *email* is the count
     * at the moment it was recognised; the count on the *row* and in the banner is the count now, which is
     * the number an operator acts on. Re-emailing on every new branch would be the per-device spam this
     * feature removes, wearing a different hat — and the pattern cannot get quieter, so there is nothing to
     * re-state as it grows.
     *
     * @param array $job        config_jobs row (the change)
     * @param bool  $allowEmail false when this refresh came from a resolution, where recording the pattern
     *                          is right but sending mail from inside a repair is not: the row stays owed,
     *                          and the next sweep sends it exactly as it sends any other unsent alert
     *
     * @return array{id:int, devices:int, created:bool, notified:bool, message:string}|null
     */
    public function refreshPattern(array $job, bool $allowEmail = true): ?array
    {
        $jobId = (int) ($job['id'] ?? 0);

        if ($jobId <= 0) {
            return null;
        }

        try {
            $members = $this->membersOfJob($jobId);
            $pattern = $this->alertModel
                ->where('fingerprint', self::patternFingerprint($jobId))
                ->where('status', ConfigAlertModel::STATUS_OPEN)
                ->first();
        } catch (\Throwable $e) {
            log_message('error', 'Fleet-pattern lookup failed for job #' . $jobId . ': ' . $e->getMessage());

            return null;
        }

        // No pattern yet: this is either still a set of unrelated branch problems, or the moment the
        // change turns out to be the thing at fault.
        if (!$pattern) {
            $refusal = self::patternRefusal($job, count($members));

            if ($refusal !== null) {
                return null;
            }

            return $this->createPattern($job, $members, $allowEmail);
        }

        $patternId = (int) $pattern['id'];

        // Every member is closed out and every one of them has been dealt with: nothing left to speak for.
        if ($members === []) {
            $this->resolve($patternId, 'verification', self::RESOLVED_CLEARED);

            return [
                'id'       => $patternId,
                'devices'  => 0,
                'created'  => false,
                'notified' => $pattern['notified_at'] !== null,
                'message'  => 'The fleet-pattern alert for this change is closed: every device in it has been repaired, rolled back or resolved.',
            ];
        }

        return $this->updatePattern($pattern, $job, $members);
    }

    /** The pattern's current members, newest first. */
    private function membersOfJob(int $jobId): array
    {
        try {
            return $this->alertModel
                ->where('job_id', $jobId)
                ->where('status', ConfigAlertModel::STATUS_OPEN)
                ->where('kind', ConfigAlertModel::KIND_REVERT)
                ->orderBy('raised_at', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'Fleet-pattern members could not be read for job #' . $jobId . ': ' . $e->getMessage());

            return [];
        }
    }

    /**
     * The devices a pattern speaks for, as alert rows — the per-device handles the banner renders.
     *
     * @return list<array>
     */
    public function membersOf(int $alertId): array
    {
        if ($alertId <= 0) {
            return [];
        }

        try {
            return $this->alertModel
                ->where('grouped_into', $alertId)
                ->where('status', ConfigAlertModel::STATUS_OPEN)
                ->orderBy('raised_at', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'Fleet-pattern members could not be read for alert #' . $alertId . ': ' . $e->getMessage());

            return [];
        }
    }

    /** Whether this row has been absorbed into an open pattern alert. */
    private function isGrouped(int $alertId): bool
    {
        if ($alertId <= 0) {
            return false;
        }

        try {
            $row = $this->alertModel->find($alertId);

            if (!$row || (int) ($row['grouped_into'] ?? 0) <= 0) {
                return false;
            }

            return (bool) $this->alertModel
                ->where('id', (int) $row['grouped_into'])
                ->where('status', ConfigAlertModel::STATUS_OPEN)
                ->countAllResults();
        } catch (\Throwable $e) {
            log_message('error', 'Fleet-pattern membership could not be checked for alert #' . $alertId . ': ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Creates the pattern row, groups the members, and sends the one email about the change.
     *
     * @param list<array> $members
     */
    private function createPattern(array $job, array $members, bool $allowEmail): ?array
    {
        $jobId = (int) $job['id'];
        $count = count($members);
        $total = $this->targetCount($jobId);

        try {
            $patternId = (int) $this->alertModel->insert([
                'job_id'          => $jobId,
                // A pattern has no device of its own — that is the whole point of it — so every
                // device-shaped column stays empty and the count carries the truth instead.
                'target_id'       => null,
                'pf_id'           => null,
                'device_name'     => mb_substr(sprintf('%d devices', $count), 0, 150),
                'fingerprint'     => self::patternFingerprint($jobId),
                'status'          => ConfigAlertModel::STATUS_OPEN,
                'summary'         => mb_substr(self::patternSummary($job, $count, $total), 0, 255),
                'detail'          => mb_substr($this->memberDetail($members), 0, 600),
                'kind'            => self::KIND_REVERT_PATTERN,
                'device_count'    => $count,
                'raised_at'       => date('Y-m-d H:i:s'),
                'notify_attempts' => 0,
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'Fleet-pattern alert could not be raised for job #' . $jobId . ': ' . $e->getMessage());

            return null;
        }

        $this->groupMembers($members, $patternId);

        $pattern = $this->alertModel->find($patternId) ?: [];
        $patched = [
            'id'       => $patternId,
            'devices'  => $count,
            'created'  => true,
            'notified' => false,
            'message'  => sprintf(
                'Fleet-pattern alert #%d raised: %s has been undone on %d device(s). This is one alert for the change, not one per branch.',
                $patternId,
                self::changeLabel($job),
                $count
            ),
        ];

        if (!$allowEmail || !$this->emailEnabled) {
            return $patched;
        }

        $result = $this->notifyAlert($pattern, $job, []);

        return array_merge($patched, [
            'notified' => (bool) ($result['ok'] ?? false),
            'message'  => $patched['message'] . ' ' . $result['message'],
        ]);
    }

    /**
     * Keeps an existing pattern honest: the count, the wording and the membership.
     *
     * @param list<array> $members
     */
    private function updatePattern(array $pattern, array $job, array $members): array
    {
        $patternId = (int) $pattern['id'];
        $count     = count($members);
        $total     = $this->targetCount((int) $job['id']);
        $summary   = self::patternSummary($job, $count, $total);

        try {
            $this->alertModel->update($patternId, [
                'device_count' => $count,
                'device_name'  => mb_substr(sprintf('%d devices', $count), 0, 150),
                'summary'      => mb_substr($summary, 0, 255),
                'detail'       => mb_substr($this->memberDetail($members), 0, 600),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Fleet-pattern alert #' . $patternId . ' could not be updated: ' . $e->getMessage());
        }

        $this->groupMembers($members, $patternId);

        return [
            'id'       => $patternId,
            'devices'  => $count,
            'created'  => false,
            'notified' => $pattern['notified_at'] !== null,
            'message'  => sprintf(
                'Fleet-pattern alert #%d now covers %d device(s) that undid this change.',
                $patternId,
                $count
            ),
        ];
    }

    /**
     * Points the members at the pattern that speaks for them.
     *
     * Written per row rather than in one statement so a failure on one member cannot lose the rest, and
     * idempotent so a member that arrives after the pattern is created is picked up on the next refresh.
     *
     * @param list<array> $members
     */
    private function groupMembers(array $members, int $patternId): void
    {
        foreach ($members as $member) {
            if ((int) ($member['grouped_into'] ?? 0) === $patternId) {
                continue;
            }

            try {
                $this->alertModel->update((int) $member['id'], ['grouped_into' => $patternId]);
            } catch (\Throwable $e) {
                log_message('error', 'Alert #' . ($member['id'] ?? '?') . ' could not be grouped into pattern #' . $patternId . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * What the pattern's `detail` says: which branches, in the words the banner and the email use.
     *
     * The member rows keep their own verification detail — that is the fingerprint source and stays
     * byte-for-byte what the verification produced. The pattern's detail is deliberately a *device list*
     * instead: "what is different" for the group is "which devices", and six paragraphs of near-identical
     * detail text is what the grouping exists to stop anybody having to read.
     *
     * @param list<array> $members
     */
    private function memberDetail(array $members): string
    {
        $names = [];

        foreach ($members as $member) {
            $names[] = (string) ($member['device_name'] ?? ('device #' . ($member['pf_id'] ?? '?')));
        }

        return 'Devices: ' . implode(', ', array_slice($names, 0, 20))
            . (count($names) > 20 ? ' and ' . (count($names) - 20) . ' more' : '');
    }

    /** How many devices the change was pushed to — the scope a count is read against. */
    private function targetCount(int $jobId): int
    {
        try {
            return db_connect()->table('job_targets')->where('job_id', $jobId)->countAllResults();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Closes the open alerts for a target — the device matched the job again.
     *
     * Called from the same place drift is detected, so an alert cannot be resolved without a fresh
     * read of the device agreeing: nothing here takes a hopeful shortcut.
     *
     * @return int how many alerts were closed
     */
    public function resolveForTarget(int $targetId, string $by, string $resolution): int
    {
        if ($targetId <= 0) {
            return 0;
        }

        try {
            $open = $this->alertModel
                ->where('target_id', $targetId)
                ->where('status', ConfigAlertModel::STATUS_OPEN)
                ->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'Drift alert resolve lookup failed: ' . $e->getMessage());

            return 0;
        }

        $closed = 0;

        foreach ($open as $alert) {
            if ($this->resolve((int) $alert['id'], $by, $resolution)) {
                $closed++;
            }
        }

        return $closed;
    }

    /**
     * An operator has looked at the alert and decided the difference is intended.
     *
     * Recorded rather than deleted, and recorded *by whom*: "somebody dismissed this" is materially
     * different from "this never happened", and the difference is exactly what an auditor asks about.
     *
     * A pattern alert is one decision about several devices, so dismissing it dismisses its members too. The
     * alternative — closing the group and leaving three open per-device alerts behind it — would resurrect
     * exactly the noise the group was created to remove, and would be asking the operator to say the same
     * thing once per branch.
     *
     * @return array{ok:bool, message:string}
     */
    public function dismiss(int $alertId, string $by): array
    {
        $alert = $this->alertModel->find($alertId);

        if (!$alert) {
            return ['ok' => false, 'message' => "Alert #{$alertId} was not found."];
        }

        if ((string) $alert['status'] !== ConfigAlertModel::STATUS_OPEN) {
            return ['ok' => false, 'message' => 'That alert is already resolved.'];
        }

        $grouped = 0;

        if (ConfigAlertModel::isPattern($alert['kind'] ?? null)) {
            foreach ($this->membersOf($alertId) as $member) {
                if ($this->resolve((int) $member['id'], $by, self::RESOLVED_DISMISSED)) {
                    $grouped++;
                }
            }
        }

        if (!$this->resolve($alertId, $by, self::RESOLVED_DISMISSED)) {
            return ['ok' => false, 'message' => 'The alert could not be resolved.'];
        }

        return [
            'ok'      => true,
            'message' => $grouped > 0
                ? 'Alert resolved by ' . $by . ', along with the ' . $grouped . ' device(s) it grouped.'
                : 'Alert resolved by ' . $by . '.',
        ];
    }

    /**
     * Open alerts, newest first, for the UI.
     *
     * @return list<array>
     */
    public function openAlerts(int $limit = 50): array
    {
        try {
            return $this->alertModel
                ->where('status', ConfigAlertModel::STATUS_OPEN)
                ->orderBy('raised_at', 'DESC')
                ->limit(max(1, $limit))
                ->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'Open drift alerts could not be listed: ' . $e->getMessage());

            return [];
        }
    }

    public function openCount(): int
    {
        try {
            return $this->alertModel->where('status', ConfigAlertModel::STATUS_OPEN)->countAllResults();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Open alerts whose email is still owed: never sent, tried fewer than the cap, oldest first.
     *
     * A grouped member is excluded, and that exclusion *is* the grouping's saving: an alert per branch is
     * precisely what the pattern replaces, so the members stay visible and owed nothing. The pattern row
     * carries the email instead — it is the one alert in the set whose subject names the change rather
     * than a single device.
     *
     * A row whose `notify_after` has not arrived is excluded too, and that is the other half of the same
     * idea: a revert waits one read cycle, and if the change turns out to be fleet-wide it is grouped inside
     * that cycle and never reaches this list at all. Ordering is by when each email is due, not by when its
     * alert was raised, so a held revert cannot overtake an alert that has been waiting longer.
     *
     * @return list<array>
     */
    public function pendingNotifications(int $limit = 5): array
    {
        try {
            return $this->alertModel
                ->where('status', ConfigAlertModel::STATUS_OPEN)
                ->where('grouped_into', null)
                ->where('notified_at', null)
                ->where('notify_attempts <', self::MAX_NOTIFY_ATTEMPTS)
                ->groupStart()
                ->where('notify_after', null)
                ->orWhere('notify_after <=', date('Y-m-d H:i:s'))
                ->groupEnd()
                ->orderBy('notify_after', 'ASC')
                ->orderBy('raised_at', 'ASC')
                ->limit(max(1, $limit))
                ->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'Pending drift notifications could not be listed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Sends every email that is still owed, oldest first.
     *
     * Shared by the two runners that can discover drift — the hourly sweep and the verification window
     * that watches a change for its first hour — because "an alert was recorded but nobody was told"
     * has to be retried the same way wherever the drift turned up. A second copy of this loop would be
     * a second place for the retry cap or the ordering to drift out of step.
     *
     * @return list<array{alert:array, ok:bool, message:string}>
     */
    public function notifyPending(int $limit = 10): array
    {
        $results = [];

        foreach ($this->pendingNotifications($limit) as $alert) {
            $result = $this->notify($alert);

            $results[] = [
                'alert'   => $alert,
                'ok'      => (bool) ($result['ok'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
            ];
        }

        return $results;
    }

    /**
     * Emails an existing alert — the retry path, used by the sweep for anything left un-notified.
     *
     * @return array{ok:bool, message:string, alert:?array}
     */
    public function notify(array $alert): array
    {
        $job    = $alert['job_id'] ? ($this->jobModel->find((int) $alert['job_id']) ?? []) : [];
        $device = $alert['pf_id'] ? ($this->inventoryModel->find((int) $alert['pf_id']) ?? []) : [];

        return $this->notifyAlert($alert, $job, $device);
    }

    /**
     * Sends and records the attempt. The alert row is updated either way, because "we could not tell
     * anyone" has to be as visible as the alert itself.
     */
    private function notifyAlert(array $alert, array $job, array $device): array
    {
        $alertId = (int) ($alert['id'] ?? 0);

        // A pattern's readers need the devices it speaks for — the subject counts them and the body lists
        // them with their own rollback states — and the members are only readable here, from the row.
        $context = [
            'job'      => $job,
            'device'   => $device,
            'rollback' => $this->rollbackJobFor($alert),
            'members'  => ConfigAlertModel::isPattern($alert['kind'] ?? null) ? $this->membersOf((int) ($alert['id'] ?? 0)) : [],
        ];

        try {
            $outcome = $this->mailer->send($alert, $context);
        } catch (\Throwable $e) {
            $outcome = ['ok' => false, 'message' => 'Mail could not be sent: ' . $e->getMessage(), 'recipients' => []];
        }

        $attempts = (int) ($alert['notify_attempts'] ?? 0) + 1;

        $data = [
            'notify_attempts' => $attempts,
            'notify_error'    => $outcome['ok'] ? null : mb_substr((string) $outcome['message'], 0, 500),
        ];

        if ($outcome['ok']) {
            $data['notified_at'] = date('Y-m-d H:i:s');
            $data['notified_to'] = implode(', ', array_slice($outcome['recipients'] ?? [], 0, 10));
        }

        try {
            $this->alertModel->update($alertId, $data);
        } catch (\Throwable $e) {
            log_message('error', 'Drift alert notification state could not be stored: ' . $e->getMessage());
        }

        $message = (string) $outcome['message'];

        if (!$outcome['ok'] && $attempts < self::MAX_NOTIFY_ATTEMPTS) {
            $message .= ' The alert stays open and the next sweep will try again.';
        } elseif (!$outcome['ok']) {
            $message .= ' Giving up after ' . $attempts . ' attempts; the alert remains open and un-notified.';
        }

        return [
            'ok'      => (bool) $outcome['ok'],
            'message' => $message,
            'alert'   => $this->alertModel->find($alertId) ?: $alert,
        ];
    }

    /**
     * The rollback job an alert is answered by, when there is one.
     *
     * Looked up here rather than threaded through every caller, so the email says the same thing whether
     * it went out the moment the revert was found or was retried by the next sweep an hour later — and so
     * a rollback that has since run, failed or been cancelled is described by what it actually did rather
     * than by what was hoped when the alert was written.
     */
    private function rollbackJobFor(array $alert): ?array
    {
        $id = (int) ($alert['rollback_job_id'] ?? 0);

        if ($id <= 0) {
            return null;
        }

        try {
            return $this->jobModel->find($id) ?: null;
        } catch (\Throwable $e) {
            log_message('error', 'Rollback job #' . $id . ' could not be read for an alert email: ' . $e->getMessage());

            return null;
        }
    }

    private function resolve(int $alertId, string $by, string $resolution): bool
    {
        // Read before the write: what happens next depends on what this row *was* (a member revert, a fleet
        // pattern, or an ordinary alert), and after the update that is no longer visible.
        try {
            $row = $this->alertModel->find($alertId) ?: [];
        } catch (\Throwable $e) {
            $row = [];
        }

        try {
            $closed = (bool) $this->alertModel->update($alertId, [
                'status'      => ConfigAlertModel::STATUS_RESOLVED,
                'resolved_at' => date('Y-m-d H:i:s'),
                'resolved_by' => mb_substr($by, 0, 120),
                'resolution'  => mb_substr($resolution, 0, 40),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Drift alert could not be resolved: ' . $e->getMessage());

            return false;
        }

        if ($closed) {
            $this->afterResolve($row);
        }

        return $closed;
    }

    /**
     * A member alert closed: the pattern that speaks for it has to be told.
     *
     * This is the other half of the grouping rule, and the reason a pattern cannot drift out of step with
     * its devices. A revert that has been repaired, rolled back, superseded or dismissed leaves the
     * pattern's count, wording and existence recomputed from the remaining members — and when the last one
     * goes, the pattern closes itself rather than standing there claiming a fleet is broken.
     *
     * Email is off for these refreshes: the caller is inside a repair, and a summary that suddenly owed a
     * message would send mail from a code path whose job was to close something. Recording it and leaving
     * the row owed (which `pendingNotifications()` picks up on the next sweep) is the same treatment every
     * other unsent alert gets.
     */
    private function afterResolve(array $row): void
    {
        // A pattern closing is handled by whoever closed it: its members are either already gone or being
        // dismissed with it, and refreshing from inside that loop would fight the caller.
        if ($row === [] || ConfigAlertModel::isPattern($row['kind'] ?? null)) {
            return;
        }

        if (!ConfigAlertModel::isRevert($row['kind'] ?? null)) {
            return;
        }

        $jobId = (int) ($row['job_id'] ?? 0);

        if ($jobId <= 0) {
            return;
        }

        try {
            $job = $this->jobModel->find($jobId);
        } catch (\Throwable $e) {
            return;
        }

        if ($job) {
            $this->refreshPattern($job, false);
        }
    }

    /**
     * The alerts the banner lists: one entry per *story*, with a pattern's devices inside it.
     *
     * A grouped member is deliberately not returned at the top level — the pattern speaks for it — but it
     * is never lost either: it travels inside its pattern's `members`, which is what keeps the per-device
     * actions (re-check, repair, rollback, resolve) exactly where they were. A member whose pattern is not
     * open any more is listed on its own rather than hidden behind a closed row: an alert that nothing
     * speaks for must not become invisible.
     *
     * @return list<array{alert:array, members:list<array>}>
     */
    public function openForBanner(int $limit = 50): array
    {
        $open = $this->openAlerts($limit);
        $patterns = [];
        $members  = [];
        $list     = [];

        foreach ($open as $row) {
            if (ConfigAlertModel::isPattern($row['kind'] ?? null)) {
                $patterns[(int) $row['id']] = $row;
            }
        }

        foreach ($open as $row) {
            if (ConfigAlertModel::isPattern($row['kind'] ?? null)) {
                continue;
            }

            $into = (int) ($row['grouped_into'] ?? 0);

            if ($into > 0) {
                $members[$into][] = $row;

                continue;
            }

            $list[] = ['alert' => $row, 'members' => []];
        }

        foreach ($patterns as $patternId => $pattern) {
            $list[] = ['alert' => $pattern, 'members' => $members[$patternId] ?? []];
        }

        foreach ($members as $into => $rows) {
            if (isset($patterns[$into])) {
                continue;
            }

            foreach ($rows as $row) {
                $list[] = ['alert' => $row, 'members' => []];
            }
        }

        usort($list, static fn (array $a, array $b): int => strcmp((string) ($b['alert']['raised_at'] ?? ''), (string) ($a['alert']['raised_at'] ?? '')));

        return $list;
    }

    /**
     * How many devices an alert stands for. Pure.
     *
     * A pattern is *n* devices and an ordinary alert is one. This is the number the nav badge and the banner
     * heading quote, and it deliberately is not the number of rows: grouping six reverts into one alert must
     * not make the fleet look six times healthier than it is. A pattern with a missing count reads as one
     * device rather than as zero — under-stating an open alert is the one direction that could hide work.
     *
     * @param array $alert config_alerts row
     */
    public static function deviceWeight(array $alert): int
    {
        return ConfigAlertModel::isPattern($alert['kind'] ?? null)
            ? max(1, (int) ($alert['device_count'] ?? 0))
            : 1;
    }

    /**
     * The number of devices a list of alert rows stands for. Pure.
     *
     * @param list<array> $alerts config_alerts rows
     */
    public static function deviceCount(array $alerts): int
    {
        $total = 0;

        foreach ($alerts as $alert) {
            $total += self::deviceWeight($alert);
        }

        return $total;
    }

    /** Devices currently affected by open alerts — what the badge and the heading should say. */
    public function openDeviceCount(int $limit = 50): int
    {
        return self::deviceCount(array_column($this->openForBanner($limit), 'alert'));
    }
}
