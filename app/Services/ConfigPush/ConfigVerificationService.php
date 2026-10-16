<?php

namespace App\Services\ConfigPush;

use App\Models\ConfigJobModel;
use App\Models\JobTargetModel;

/**
 * Did the change stick?
 *
 * "Applied" is a report about a write. It is not evidence that the device is *now* running what the
 * job intended — the write may have been rejected by pfSense, a package may have rewritten the file,
 * or somebody may have restored an older configuration twenty minutes later. This service reads the
 * device's configuration back and answers that question, for the audit trail and for the operator who
 * wants to know which branch never took the change.
 *
 * The check is a **convergence test**: re-applying the job's own operation must be a no-op.
 *
 *   - a section fragment is re-merged into the device's current config; if that would change
 *     anything, the intended settings are not all present;
 *   - a complete configuration is diffed against the current one.
 *
 * That definition is deliberate, and it is what makes the answer trustworthy: it reuses the *same*
 * operation the device performed, so it cannot disagree with it about what the job meant, and it
 * reads nothing but the device's own configuration — no stored "expected" copy of the config is
 * needed, and none can go stale.
 *
 * Two limits are stated rather than hidden. A `script` payload has no intended configuration to
 * compare, so it is `not_applicable`. And a device whose configuration cannot be read is
 * `unavailable`, not "verified" — an unverifiable change is exactly the thing an operator must not
 * be told is fine.
 */
class ConfigVerificationService
{
    /** How many differing settings to name in the summary before counting the rest. */
    private const MAX_NAMED = 3;

    /**
     * How many times a device may be unreachable for a read before the sweep stops asking.
     *
     * The row keeps `unavailable` when this is reached, which is the truth: nobody ever managed to
     * confirm it. Promoting it to `matched` to clear the queue would be inventing a verdict.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * The default window: when a freshly applied change is read back again, in minutes after the push.
     *
     * A single read-back straight after a push proves the write landed; the hourly sweep proves the
     * change survived an hour. Neither notices the failure that actually happens most often — pfSense
     * rewriting config.xml on its next package sync, a branch engineer restoring an older config, a
     * script that runs a minute later — because that revert happens *inside* that hour and looks like a
     * success at both ends of it. Four checks, front-loaded, close that gap: most reverts happen early,
     * so the first checks are the closest together.
     *
     * These are minutes after `applied_at`, and each one is a slot rather than a retry: a device that
     * cannot be reached at +5 minutes is asked again at +15 rather than being hammered. The window's
     * checks are deliberately cheaper than the sweep's — they never consume the sweep's attempt budget.
     *
     * It is the default rather than the rule: a job may choose a different window (see `WATCH_PROFILES`),
     * and the one it chose is frozen on the job row.
     */
    public const WATCH_SCHEDULE = [5, 15, 30, 60];

    /** Not watched at all. Honest for a script, and a choice for a change nobody will act on. */
    public const WATCH_PROFILE_NONE = 'none';

    /** A single read, ten minutes later: did the change stick at all. */
    public const WATCH_PROFILE_ONCE = 'once';

    /** The default: the first hour, front-loaded. */
    public const WATCH_PROFILE_STANDARD = 'standard';

    /** A full day, dense at first and then hourly-to-daily. */
    public const WATCH_PROFILE_EXTENDED = 'extended';

    /**
     * The windows a job may choose, and what each one is actually for.
     *
     * One schedule for every push is the wrong shape at both ends of the risk spectrum. A cosmetic change
     * — an alias, a description, a hostname — is watched for an hour nobody needs; a change that reroutes
     * traffic or rewrites a tunnel is unwatched after that same hour, even though the failure worth
     * catching there (a nightly restore, a weekly package sync, somebody "fixing" it back on Tuesday)
     * happens long after the window closed. So the window is a property of the *change*: the operator who
     * knows what the change touches picks how long it is watched for.
     *
     * The minutes are offsets from `applied_at`, in order. The extended profile is deliberately not a
     * uniform cadence: the first hour stays dense because a device-undoes-it revert happens in minutes,
     * and the tail is sparse because the thing being watched for is a daily or weekly event. Nine reads
     * over a day is cheap for one branch and, crucially, opt-in.
     *
     * @var array<string, array{minutes:list<int>, label:string, hint:string}>
     */
    public const WATCH_PROFILES = [
        self::WATCH_PROFILE_NONE => [
            'minutes' => [],
            'label'   => 'not watched',
            'hint'    => 'No re-read at all. Honest for a script, which has no configuration to compare, and a deliberate choice for a change whose outcome nobody would act on.',
        ],
        self::WATCH_PROFILE_ONCE => [
            'minutes' => [10],
            'label'   => 'watched once',
            'hint'    => 'One read ten minutes later: did the change stick at all. Long enough for a reload or a package sync to have settled, short enough that the operator is still around.',
        ],
        self::WATCH_PROFILE_STANDARD => [
            'minutes' => self::WATCH_SCHEDULE,
            'label'   => 'watched for the first hour',
            'hint'    => 'Five, fifteen, thirty and sixty minutes after the push. What most changes need.',
        ],
        self::WATCH_PROFILE_EXTENDED => [
            'minutes' => [5, 15, 30, 60, 120, 240, 480, 720, 1440],
            'label'   => 'watched for a day',
            'hint'    => 'The first hour as usual, then two, four, eight, twelve and twenty-four hours — for a change that routes traffic or rewrites a tunnel, where a nightly restore or a package sync is what you are watching for.',
        ],
    ];

    /** The window a job gets when it does not ask for one. */
    public const DEFAULT_WATCH_PROFILE = self::WATCH_PROFILE_STANDARD;

    /**
     * The largest schedule any window may have.
     *
     * The queries that *find* a due check are bounded by this rather than by the length of each job's own
     * schedule (a per-row `JSON_LENGTH` would put the window's arithmetic into SQL, where it cannot be
     * tested). It is a belt-and-braces bound, not the real end of a window: `advanceWatch()` clears
     * `watch_next_at` on the last real slot, and that is what closes a window. A stored schedule longer
     * than this is therefore *trimmed* rather than read, and the unit tests assert no profile comes close.
     */
    public const MAX_WATCH_SLOTS = 25;

    /** pfCentral reads the device over SSH. */
    public const WATCH_VIA_SSH = 'ssh';

    /** The device reads itself, because nothing on this side can open a connection to it (agent v2.2+). */
    public const WATCH_VIA_AGENT = 'agent';

    protected JobTargetModel $targetModel;
    protected ConfigJobModel $jobModel;
    protected ConfigDiffService $diffs;
    protected PushLogWriter $logWriter;
    protected ConfigAlertService $alerts;
    protected AgentAuthService $agents;

    /** @var ConfigTransportInterface[] ways to read a device, tried in order */
    protected array $readers;

    public function __construct(
        ?JobTargetModel $targetModel = null,
        ?ConfigJobModel $jobModel = null,
        ?ConfigDiffService $diffs = null,
        ?PushLogWriter $logWriter = null,
        ?array $readers = null,
        ?ConfigAlertService $alerts = null,
        ?AgentAuthService $agents = null
    ) {
        $this->targetModel = $targetModel ?? new JobTargetModel();
        $this->jobModel    = $jobModel ?? new ConfigJobModel();
        $this->diffs       = $diffs ?? new ConfigDiffService();
        $this->logWriter   = $logWriter ?? new PushLogWriter();
        $this->alerts      = $alerts ?? new ConfigAlertService();
        $this->agents      = $agents ?? new AgentAuthService();
        // SSH is the only reader today: the agent channel is device-initiated, so a device that was
        // changed over it must report its own post-apply read instead.
        $this->readers     = $readers ?? [new SshTransport()];
    }

    /**
     * Verifies one applied target: reads the device, compares, records.
     *
     * This is the whole operation the runner, the sweep and the dashboard's re-verify action share,
     * so all three reach the same verdict on the same device. Never throws — a verification is
     * diagnostics, and it must not be able to fail a deployment that already succeeded.
     *
     * @param array                       $target job_targets row
     * @param array                       $job    config_jobs row
     * @param array                       $device pf_inventory row
     * @param ConfigTransportInterface[]|null $readers override the default readers
     *
     * @return array{status:string, detail:string, alert:?array}
     */
    public function verifyDevice(array $target, array $job, array $device, ?array $readers = null, bool $countAttempt = true): array
    {
        if (!$this->needsRead($job)) {
            return $this->verify($target, $job, $device, '');
        }

        if (!isset($target['verify_attempts'])) {
            $row = $this->targetModel->find((int) $target['id']);

            $target['verify_attempts'] = (int) (is_array($row) ? ($row['verify_attempts'] ?? 0) : 0);
        }

        $failures = [];

        foreach ($readers ?? $this->readers as $reader) {
            try {
                $read = $reader->readConfig($device);
            } catch (\Throwable $e) {
                $failures[] = $reader->name() . ': ' . $e->getMessage();
                continue;
            }

            if ($read['ok']) {
                return $this->verify($target, $job, $device, (string) $read['contents']);
            }

            $failures[] = $read['message'];
        }

        return $this->record(
            $target,
            $job,
            $device,
            JobTargetModel::VERIFY_UNAVAILABLE,
            'Could not confirm: ' . implode(' ', array_unique($failures)),
            true,
            $countAttempt
        );
    }

    /**
     * Targets that were applied and should be checked, oldest first.
     *
     * `unavailable` is deliberately re-checkable: a branch that was unreachable when it was first
     * asked may be reachable now, and "we could not confirm" should be able to become "confirmed"
     * without a human intervening. Once the attempt cap is reached the row drops out of the sweep
     * but keeps the status that describes it, rather than being quietly promoted to a verdict.
     *
     * @param int|null $jobId narrow the sweep to one job
     *
     * @return list<array>
     */
    public function pending(?int $jobId = null, int $limit = 10): array
    {
        try {
            $builder = $this->targetModel
                ->where('status', JobTargetModel::STATUS_APPLIED)
                ->whereIn('verify_status', [JobTargetModel::VERIFY_PENDING, JobTargetModel::VERIFY_UNAVAILABLE])
                ->where('verify_attempts <', self::MAX_ATTEMPTS)
                // A device inside its verification window belongs to the watch runner, not to the
                // sweep: the window is reading it every few minutes anyway, and two schedulers racing
                // over one branch would double the SSH traffic for no second opinion. The moment the
                // window closes — or never opened — the sweep picks the device up with its full budget.
                ->groupStart()
                ->where('watch_next_at', null)
                ->orWhere('watch_until <', date('Y-m-d H:i:s'))
                ->groupEnd()
                ->orderBy('id', 'ASC')
                ->limit(max(1, $limit));

            if ($jobId !== null) {
                $builder = $builder->where('job_id', $jobId);
            }

            return $builder->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'Verification sweep query failed: ' . $e->getMessage());

            return [];
        }
    }

    // ------------------------------------------------------------------ verification window

    /**
     * The minute offsets a profile resolves to. Pure.
     *
     * An unknown name resolves to the default rather than to nothing: a stored value nobody recognises is a
     * missing setting, and the failure mode of guessing "standard" is that a change is read for an hour,
     * while the failure mode of guessing "none" is that it is never read at all. A form POST is validated
     * instead (`isWatchProfile()`), so this leniency never covers a live choice.
     *
     * @return list<int>
     */
    public static function watchProfileMinutes(?string $profile): array
    {
        return self::WATCH_PROFILES[self::normaliseWatchProfile($profile)]['minutes'];
    }

    /** Whether a *submitted* profile is one this system offers. */
    public static function isWatchProfile(?string $profile): bool
    {
        return $profile !== null && array_key_exists(trim($profile), self::WATCH_PROFILES);
    }

    /** Anything unrecognised is the default, in one direction only. */
    public static function normaliseWatchProfile(?string $profile): string
    {
        $profile = $profile !== null ? trim($profile) : '';

        return array_key_exists($profile, self::WATCH_PROFILES) ? $profile : self::DEFAULT_WATCH_PROFILE;
    }

    /** The operator's name for a window. */
    public static function watchProfileLabel(?string $profile): string
    {
        return self::WATCH_PROFILES[self::normaliseWatchProfile($profile)]['label'];
    }

    /**
     * The window a job is actually watched under: the schedule frozen on the row, or its profile.
     *
     * One resolver, so every reader of a window agrees about its length — the command that walks it, the
     * agent's pickup, the sweep's bound, the badge on the dashboard and the revert/drift boundary. The
     * frozen schedule wins because `watch_checks` *indexes into it*: if editing a profile changed how a
     * half-walked window is interpreted, a job in mid-flight would silently be walked through a list it
     * never started on.
     *
     * An empty schedule means "not watched", which is a decision rather than a missing value, so it is
     * returned as it stands instead of falling back to a profile. `null` — a row created before this
     * existed — falls back to the profile name, and then to the default, which is exactly the schedule
     * such a job was created under.
     *
     * @param array|null $job config_jobs row
     *
     * @return list<int>
     */
    public static function watchScheduleFor(?array $job): array
    {
        $stored = $job['watch_schedule'] ?? null;

        if ($stored !== null) {
            if (is_string($stored)) {
                // A row read without the model's cast (a raw query, an older row): decode it here rather
                // than trusting every caller to have gone through the model.
                $stored = json_decode($stored, true);
            }

            if (is_array($stored)) {
                return self::cleanSchedule($stored);
            }
        }

        return self::cleanSchedule(self::watchProfileMinutes((string) ($job['watch_profile'] ?? self::DEFAULT_WATCH_PROFILE)));
    }

    /**
     * A schedule as it may safely be walked: positive, ascending, no repeated moment, and bounded.
     *
     * Applied to stored schedules as well as to the profiles, because a hand-edited row must not be able
     * to make a window read the same file twice in one minute or walk backwards. Trimming is the bound
     * rather than an error: `MAX_WATCH_SLOTS` is what the due-check queries can find, so a longer stored
     * schedule would otherwise describe checks that could never come due.
     *
     * @param list<mixed> $minutes
     *
     * @return list<int>
     */
    private static function cleanSchedule(array $minutes): array
    {
        $clean = [];

        foreach ($minutes as $minute) {
            $minute = (int) $minute;

            if ($minute > 0 && !in_array($minute, $clean, true)) {
                $clean[] = $minute;
            }
        }

        sort($clean);

        return array_slice($clean, 0, self::MAX_WATCH_SLOTS);
    }

    /**
     * When the window's next check is due, counting from the moment the change was applied.
     *
     * Pure, and the only place a schedule is interpreted: `$checks` is how many window checks have
     * already run, so it answers both "when is the first one" and "when is the one after this". A null
     * means the schedule is exhausted — the window is over and nothing more is outstanding.
     *
     * @param int        $checks   how many window checks have already run (0 = none yet)
     * @param list<int>|null $schedule the job's window; null is the default
     */
    public static function watchDueAt(?string $appliedAt, int $checks, ?array $schedule = null): ?string
    {
        $offset = ($schedule ?? self::WATCH_SCHEDULE)[$checks] ?? null;

        if ($offset === null || $appliedAt === null || trim($appliedAt) === '') {
            return null;
        }

        $applied = strtotime($appliedAt);

        if ($applied === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $applied + ($offset * 60));
    }

    /**
     * Whole minutes since a timestamp, or null when there is nothing to measure. Pure.
     *
     * Used to say how long after a push a revert was found — which is the diagnosis, not decoration: a
     * change that was gone four minutes later was undone by the device, and one that was gone four days
     * later was undone by a person.
     */
    public static function ageInMinutes(?string $when, ?int $now = null): ?int
    {
        if ($when === null || trim($when) === '') {
            return null;
        }

        $ts = strtotime($when);

        if ($ts === false) {
            return null;
        }

        return max(0, (int) floor((($now ?? time()) - $ts) / 60));
    }

    /**
     * Was this drift found while we were still watching the change — a revert — or long afterwards?
     *
     * The boundary is the watch, and it is the honest one because it is not a guess about intent: a
     * difference found *inside* the window the change asked for was found while this system was still
     * reading the device on that schedule, minutes after a push that reported success. That is a device undoing
     * what it was just told to do — a package sync rewriting config.xml, a scheduled restore, a local
     * script, or somebody at the branch who did not know a change had landed. A difference found after
     * the window closed is a later change to the device: a real problem, but not the same event, and
     * saying which one it is is the difference between an alert people act on and one they read as routine.
     *
     * Where no window was ever set the boundary is the schedule's first slot measured from `applied_at`,
     * because a difference found five minutes after a write is the same event by any name. That fallback is
     * the *shortest* slot, not the job's own first one, when a job chose not to be watched: a branch whose
     * change nobody promised to re-read is still a branch that was undone minutes after a push, and the loud
     * wording is about the event, not about what we said we would do about it. A target with no `applied_at`
     * at all is an ordinary drift, never a revert: the loud wording is only worth reading while it stays
     * rare, so it is never used on evidence we do not have.
     *
     * @param array           $target   job_targets row: `applied_at` and the window columns decide the answer
     * @param list<int>|null  $schedule the job's window; null is the default
     */
    public static function driftKind(array $target, ?int $now = null, ?array $schedule = null): string
    {
        $applied = strtotime((string) ($target['applied_at'] ?? ''));

        if ($applied === false) {
            return ConfigAlertService::KIND_DRIFT;
        }

        $first    = ($schedule ?? self::WATCH_SCHEDULE)[0] ?? self::WATCH_SCHEDULE[0];
        $until    = strtotime((string) ($target['watch_until'] ?? ''));
        $boundary = $until !== false ? $until : $applied + ($first * 60);

        return ($now ?? time()) <= $boundary
            ? ConfigAlertService::KIND_REVERT
            : ConfigAlertService::KIND_DRIFT;
    }

    /** When the window closes: the last slot of the schedule, measured from the same moment. */
    public static function watchEndsAt(?string $appliedAt, ?array $schedule = null): ?string
    {
        $minutes  = $schedule ?? self::WATCH_SCHEDULE;
        $last     = $minutes === [] ? null : $minutes[count($minutes) - 1];
        $applied  = $appliedAt !== null ? strtotime($appliedAt) : false;

        if ($last === null || $applied === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $applied + ($last * 60));
    }

    /**
     * Opens a verification window on a freshly applied target.
     *
     * Called from the two places a change is recorded as applied — the runner after an SSH push and the
     * agent service after a device reports success — because a window belongs to the *apply*, not to a
     * verdict: a device that could not be reached for its inline read-back is exactly the one worth
     * asking again a few minutes later.
     *
     * Refuses, and says so by returning false, when there is nothing to watch: a script payload has no
     * intended configuration to compare, a target that is not applied has no change to catch a revert
     * of, a missing `applied_at` gives the schedule nothing to hang from, a job that chose not to be
     * watched has no slots to walk, and a window whose whole extent is already in the past would be a
     * fiction rather than a watch.
     *
     * The schedule is the *job's* own (`watchScheduleFor()`), so the change that asked to be watched for a
     * day is read for a day and the one that asked once is read once.
     *
     * @param array|null $job    the job, used to decide whether a read makes sense and for how long
     * @param array|null $device the device, used to decide whether a read is *possible*
     */
    public function startWatch(int $targetId, ?array $job = null, ?array $device = null, ?int $now = null): bool
    {
        if ($targetId <= 0) {
            return false;
        }

        $now = $now ?? time();

        try {
            $target = $this->targetModel->find($targetId);

            if (!$target || (string) ($target['status'] ?? '') !== JobTargetModel::STATUS_APPLIED) {
                return false;
            }

            if ($job !== null && !$this->needsRead($job)) {
                return false;
            }

            // A window nobody can read is worse than no window: every check would record "could not
            // confirm", and those records would overwrite a verdict the device itself already gave. So a
            // window only opens where somebody can actually do the reading — either a transport pfCentral
            // can open a connection with, or the device's own agent (v2.2), which reads itself on the
            // schedule pfCentral hands it over the pickup call it was already making.
            if ($device !== null) {
                $via = $this->watchChannel($device);

                if ($via === null) {
                    return false;
                }
            } else {
                $via = self::WATCH_VIA_SSH;
            }

            $schedule = self::watchScheduleFor($job);

            if ($schedule === []) {
                return false;
            }

            $appliedAt = (string) ($target['applied_at'] ?? '');
            $next      = self::watchDueAt($appliedAt, 0, $schedule);
            $until     = self::watchEndsAt($appliedAt, $schedule);

            if ($next === null || $until === null || strtotime($until) <= $now) {
                return false;
            }

            $this->targetModel->update($targetId, [
                'watch_started_at' => date('Y-m-d H:i:s', $now),
                'watch_until'      => $until,
                'watch_next_at'    => $next,
                'watch_checks'     => 0,
                'watch_via'        => $via,
            ]);
        } catch (\Throwable $e) {
            // Never fatal, for the same reason verification itself is not: a change that was applied
            // successfully must not be reported as failed because an extra guard rail could not be set.
            log_message('error', 'Verification window could not be opened for target #' . $targetId . ': ' . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Targets whose next window check is due, oldest first.
     *
     * `watch_checks < MAX_WATCH_SLOTS` is a belt-and-braces bound: the real end of a window is
     * `advanceWatch()` clearing `watch_next_at` on its last slot, and this only makes a hand-edited row
     * fail safe rather than loop forever. It is a constant rather than each job's own length because the
     * alternative would put the window's arithmetic into SQL, where it could not be tested — and because
     * every profile is far below it, which a unit test asserts.
     *
     * @return list<array>
     */
    public function watchDue(int $limit = 25): array
    {
        try {
            return $this->targetModel
                ->where('status', JobTargetModel::STATUS_APPLIED)
                ->where('watch_next_at IS NOT NULL', null, false)
                ->where('watch_next_at <=', date('Y-m-d H:i:s'))
                ->where('watch_checks <', self::MAX_WATCH_SLOTS)
                ->orderBy('watch_next_at', 'ASC')
                ->limit(max(1, $limit))
                ->findAll();
        } catch (\Throwable $e) {
            log_message('error', 'Verification window query failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * One window check: read the device, record the verdict, and move the schedule on.
     *
     * The read and the verdict are the *same* code the sweep uses — same reader order, same convergence
     * test, same alerting — because a second opinion about a device must never be a second definition of
     * what drifted. The only difference is that an unreachable device does not spend the sweep's attempt
     * budget (`$countAttempt = false`): this window has its own, and the sweep must still get to ask with
     * a full three tries once the window is up.
     *
     * Drift **ends** the window: the change is gone, repeating the read will not bring it back, and the
     * alert has already been raised by `record()`. Everything else keeps the schedule, "could not
     * confirm" included — that is precisely the state where another try in ten minutes is worth the
     * connection.
     *
     * @return array{status:string, detail:string, alert:?array, checks:int, next:?string, stopped:bool}
     */
    public function watchCheck(array $target, array $job, array $device, ?array $readers = null): array
    {
        $checks  = (int) ($target['watch_checks'] ?? 0);
        $outcome = $this->verifyDevice($target, $job, $device, $readers, false);

        $stop = $outcome['status'] === JobTargetModel::VERIFY_DRIFTED;
        $next = $this->advanceWatch((int) $target['id'], $target, $checks, $stop, self::watchScheduleFor($job));

        return [
            'status'  => (string) $outcome['status'],
            'detail'  => (string) $outcome['detail'],
            'alert'   => $outcome['alert'] ?? null,
            'checks'  => $checks + 1,
            'next'    => $next,
            'stopped' => $stop || $next === null,
        ];
    }

    /**
     * Moves a target on to the next slot, or closes its window.
     *
     * `watch_until` is left as it was, including when drift cuts the window short: it records the extent
     * the window was *given*, and the fact that it stopped early is what `watch_next_at` being null while
     * the window has not elapsed already says. Rewriting it on a drift would erase that.
     *
     * @param list<int>|null $schedule the job's window; null is the default
     *
     * @return string|null the next due time, or null when nothing more is outstanding
     */
    public function advanceWatch(int $targetId, array $target, int $checks, bool $stop = false, ?array $schedule = null): ?string
    {
        $next = $stop ? null : self::watchDueAt((string) ($target['applied_at'] ?? ''), $checks + 1, $schedule);

        try {
            $this->targetModel->update($targetId, [
                'watch_checks'  => $checks + 1,
                'watch_next_at' => $next,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Verification window could not be advanced for target #' . $targetId . ': ' . $e->getMessage());
        }

        return $next;
    }

    /**
     * How a target's window reads, for the console and for the dashboard. Pure.
     *
     * One definition of "is this device being watched right now", so the command's log line and the
     * page's badge cannot disagree about it.
     *
     * `$job` is optional so a target can still be read without it, but the badge wants it: the window's
     * length and its name are properties of the *change*, so "2 of 9" and "watched for a day" are the
     * facts worth showing, and neither can be read off the target row alone.
     *
     * @param array|null $job config_jobs row
     *
     * @return array{active:bool, checks:int, total:int, next:?string, minutes_until:?int, until:?string, via:?string, by_device:bool, profile:string, profile_label:string}
     */
    public static function watchSummary(array $target, ?int $now = null, ?array $job = null): array
    {
        $now     = $now ?? time();
        $next    = $target['watch_next_at'] ?? null;
        $until   = $target['watch_until'] ?? null;
        $checks  = (int) ($target['watch_checks'] ?? 0);
        $via     = $target['watch_via'] ?? null;
        $profile = self::normaliseWatchProfile($job['watch_profile'] ?? null);

        return [
            'active'        => $next !== null,
            'checks'        => $checks,
            'total'         => count($job !== null ? self::watchScheduleFor($job) : self::WATCH_SCHEDULE),
            'next'          => $next !== null ? (string) $next : null,
            'minutes_until' => $next !== null ? max(0, (int) ceil((strtotime((string) $next) - $now) / 60)) : null,
            'until'         => $until !== null ? (string) $until : null,
            'via'           => $via !== null ? (string) $via : null,
            // Worth saying plainly: a device reading itself is a different kind of evidence from pfCentral
            // reading it, and an operator should never have to guess which one they are looking at.
            'by_device'     => $via === self::WATCH_VIA_AGENT,
            'profile'       => $profile,
            'profile_label' => self::watchProfileLabel($profile),
        ];
    }

    /**
     * Who will do the reading for this device, or null when nobody can.
     *
     * SSH first: it is pfCentral's own read, and it needs nothing from the branch. The agent is the second
     * answer and the reason this method exists at all — a branch whose only path to pfCentral is the
     * connection it opens itself has no reader on this side, so the window asks the device to read *itself*
     * on the schedule the pickup response carries.
     */
    private function watchChannel(array $device): ?string
    {
        foreach ($this->readers as $reader) {
            try {
                if ($reader->supports($device)) {
                    return self::WATCH_VIA_SSH;
                }
            } catch (\Throwable $e) {
                // A reader that cannot even answer the question is not a reader.
                log_message('debug', 'Reader ' . $reader->name() . ' could not be asked about device #' . ($device['id'] ?? '?') . ': ' . $e->getMessage());
            }
        }

        $agent = $this->agents->agentFor((int) ($device['id'] ?? 0));

        return ($this->agents->isOnline($agent) && $this->agents->agentCanWatch($agent))
            ? self::WATCH_VIA_AGENT
            : null;
    }

    /**
     * Whether answering this job's question requires reading the device at all.
     *
     * A script payload has no intended configuration and a job with no payload has nothing to compare,
     * so both are decided from the job alone — spending an SSH connection to reach a conclusion that
     * does not depend on the device would be pure cost.
     */
    public function needsRead(array $job): bool
    {
        if ((string) ($job['payload_type'] ?? 'xml') !== 'xml') {
            return false;
        }

        return trim((string) ($job['rendered_payload'] ?? '')) !== '';
    }

    /**
     * Does the device's current configuration still contain what this job intended?
     *
     * @param array  $job     config_jobs row
     * @param string $current the configuration read back from the device
     *
     * @return array{status:string, detail:string}
     */
    public function check(array $job, string $current): array
    {
        $payloadType = (string) ($job['payload_type'] ?? 'xml');

        if ($payloadType !== 'xml') {
            return [
                'status' => JobTargetModel::VERIFY_NOT_APPLICABLE,
                'detail' => 'A ' . $payloadType . ' payload is executed rather than installed, so there is no intended configuration to compare against.',
            ];
        }

        $payload = (string) ($job['rendered_payload'] ?? '');

        if (trim($payload) === '') {
            return [
                'status' => JobTargetModel::VERIFY_UNAVAILABLE,
                'detail' => 'The job carries no payload, so there is nothing to compare the device against.',
            ];
        }

        $problem = ConfigSnapshotStore::validate($current);
        if ($problem !== null) {
            return [
                'status' => JobTargetModel::VERIFY_UNAVAILABLE,
                'detail' => 'The configuration read back from the device cannot be used: ' . $problem,
            ];
        }

        return ConfigFragmentMerger::isMergeJob(['payload_type' => $payloadType, 'payload' => $payload])
            ? $this->checkMerge($payload, $current)
            : $this->checkReplace($payload, $current);
    }

    /**
     * A fragment has landed when re-merging it changes nothing.
     *
     * This is the strongest form of the check available, and the most forgiving of irrelevant change:
     * another push touching a different section does not make this job look drifted.
     *
     * @return array{status:string, detail:string}
     */
    private function checkMerge(string $fragment, string $current): array
    {
        $again = ConfigFragmentMerger::merge($current, $fragment);

        if (!$again['ok']) {
            return [
                'status' => JobTargetModel::VERIFY_DRIFTED,
                'detail' => 'The fragment no longer applies to this device: ' . $again['message'],
            ];
        }

        if ($again['changes'] === []) {
            return [
                'status' => JobTargetModel::VERIFY_MATCHED,
                'detail' => 'Re-applying this fragment would change nothing, so every intended setting is present.',
            ];
        }

        return [
            'status' => JobTargetModel::VERIFY_DRIFTED,
            'detail' => 'Re-applying this fragment would still change ' . count($again['changes']) . ' setting(s): '
                . $this->summariseStrings($again['changes']),
        ];
    }

    /**
     * A complete configuration has landed when the device matches it.
     *
     * Stricter than the merge case by nature: the job claimed to be the device's configuration, so a
     * setting the device has and the payload does not is a difference worth reporting too.
     *
     * @return array{status:string, detail:string}
     */
    private function checkReplace(string $payload, string $current): array
    {
        $diff = $this->diffs->diff($current, $payload);

        if (!$diff['ok']) {
            return [
                'status' => JobTargetModel::VERIFY_UNAVAILABLE,
                'detail' => 'The device configuration could not be compared: ' . $diff['error'],
            ];
        }

        if ($diff['conflicts'] !== []) {
            return [
                'status' => JobTargetModel::VERIFY_DRIFTED,
                'detail' => 'The device no longer has the structure this payload needs: ' . implode('; ', $diff['conflicts']),
            ];
        }

        $totals = $diff['totals'];
        $total  = $totals['added'] + $totals['removed'] + $totals['changed'];

        if ($total === 0) {
            return [
                'status' => JobTargetModel::VERIFY_MATCHED,
                'detail' => 'The device configuration matches this payload'
                    . ($totals['filtered'] > 0 ? ' (' . $totals['filtered'] . ' save-time field(s) ignored)' : '') . '.',
            ];
        }

        return [
            'status' => JobTargetModel::VERIFY_DRIFTED,
            'detail' => 'The device configuration still differs in ' . $total . ' setting(s): ' . $this->summariseChanges($diff['changes']),
        ];
    }

    /**
     * Stores an outcome on the target and, when a change did not stick, writes an audit row.
     *
     * A drift verdict also raises the alert (once per distinct difference) and a match resolves the
     * target's open alerts, so "is anything drifting that nobody has been told about?" is answered by
     * the same code that decides drift in the first place.
     *
     * @param array $target the job_targets row
     * @param array $job    the config_jobs row
     *
     * @return array{status:string, detail:string, alert:?array}
     */
    public function record(array $target, array $job, array $device, string $status, string $detail, bool $attempted = false, bool $countAttempt = true): array
    {
        $data = [
            'verify_status' => $status,
            'verify_detail' => mb_substr($detail, 0, 600),
        ];

        // Drift ends the verification window, wherever it was found — the inline read seconds after the
        // push, a window check, a manual Re-check, or an `--all` sweep. The change is gone, and four more
        // reads would cost four SSH connections and tell nobody anything new. Written in the same update
        // as the verdict, so a window cannot survive a drift because a second write failed.
        if ($status === JobTargetModel::VERIFY_DRIFTED) {
            $data['watch_next_at'] = null;
        }

        $terminal = in_array($status, [JobTargetModel::VERIFY_MATCHED, JobTargetModel::VERIFY_DRIFTED, JobTargetModel::VERIFY_NOT_APPLICABLE], true);

        // `verified_at` is when the device's configuration was last looked at — whether that produced a
        // verdict, a refusal to compare, or an unreachable device. Leaving it null for a failed read
        // would make "never checked" and "checked, could not reach" indistinguishable on screen.
        if ($attempted || $terminal) {
            $data['verified_at'] = date('Y-m-d H:i:s');
        }

        // `$countAttempt` is false for a read the verification window made: that window has its own
        // budget (the schedule), and spending the sweep's attempts on it would let a device that was
        // asleep at +5 minutes reach "gave up after 3 attempts" before the sweep ever asked it.
        if ($attempted && $countAttempt) {
            $attempts = (int) ($target['verify_attempts'] ?? 0) + 1;
            $data['verify_attempts'] = $attempts;

            if (!$terminal && $attempts >= self::MAX_ATTEMPTS) {
                $data['verify_detail'] = mb_substr($detail . ' (gave up after ' . $attempts . ' attempts)', 0, 600);
            }
        }

        try {
            $this->targetModel->update((int) $target['id'], $data);
        } catch (\Throwable $e) {
            log_message('error', 'Verification result could not be stored: ' . $e->getMessage());
        }

        // Drift is an event: it belongs in the per-device history, where an operator will meet it.
        // A match is not, and writing a row for every successful check would bury the real ones.
        if ($status === JobTargetModel::VERIFY_DRIFTED) {
            $this->logDrift($target, $job, $device, $detail);
        }

        // Alerting hangs off this single point on purpose: every route that can reach a drift verdict
        // — the hourly sweep, the runner right after an SSH push, the agent's own report, a manual
        // Re-check — comes through here, so drift is raised (once) and cleared (only on a real match)
        // in exactly one place rather than in four that would slowly disagree.
        //
        // The *kind* is decided here too, and from the target rather than from the caller: whether this is
        // a revert or a later drift is a fact about when the change was applied and watched, not about
        // which cron happened to read it. Deciding it at the four call sites guaranteed four answers.
        $kind = self::driftKind($target, null, self::watchScheduleFor($job));

        // The repair is raised *before* the alert, so the alert can carry the answer instead of the old
        // "nothing was changed or rolled back by us" — which stopped being true the moment a revert
        // could be put back automatically. Same single point, same reason: four call sites deciding who
        // repairs a revert would be four policies.
        $rollback = $status === JobTargetModel::VERIFY_DRIFTED
            ? $this->autoRollback($target, $job, $device, $kind)
            : null;

        $alert = $this->alert($target, $job, $device, $status, $detail, $kind, $rollback);

        // A reconcile job proving the change on a device is the trigger to re-open the *source* job's
        // target for its own read-back. The source is not marked fixed here, and its drift alert is not
        // closed here: this job's read says the change is back, and the source is confirmed the only way
        // any device is confirmed in this system, by being read itself.
        $source = $status === JobTargetModel::VERIFY_MATCHED ? $this->reopenSourceTarget($job, $device) : null;

        // A rollback that is read back and confirmed closes the loop on the change it undid: the device
        // really is running its pre-push configuration, so the alert that reported the revert has been
        // answered and should stop asking somebody to do something about it.
        $settled = $status === JobTargetModel::VERIFY_MATCHED ? $this->settleRollbackSource($job, $device) : null;

        return [
            'status'   => $status,
            'detail'   => $detail,
            'alert'    => $alert,
            'source'   => $source,
            'rollback' => $rollback,
            'settled'  => $settled,
        ];
    }

    /**
     * Puts a device back on its pre-push snapshot when the verification window catches an immediate revert.
     *
     * This is the only place a revert is answered automatically, and it is deliberate that it hangs off the
     * *window's* event rather than off drift in general. A revert is a device undoing something minutes
     * after being told to do it — a package sync rewriting `config.xml`, a scheduled restore, a local
     * script — and the change is gone either way, so the useful question is not "should we put it back?"
     * but "is it safe to put it back without waking anybody?". The pre-push snapshot answers the first,
     * and the approval gate answers the second: an ordinary one-branch XML push's rollback is queued and
     * runs; anything that needed a second approver the first time waits for one again.
     *
     * A later drift is never rolled back here. A device that stopped matching a change applied long ago
     * has had something *else* done to it since, and restoring the pre-push snapshot would silently
     * discard that too — so it is reported and left for a person.
     *
     * Never throws, for the same reason the rest of verification does not: a repair we could not raise is
     * a worse outcome than no repair at all only if it also takes the alert down with it.
     *
     * @param string $kind the drift kind already decided by `driftKind()`
     *
     * @return array{queued:bool, job_id:?int, gated:bool, message:string}|null null when this was not a revert
     */
    private function autoRollback(array $target, array $job, array $device, string $kind): ?array
    {
        if (!ConfigAlertService::isRevert($kind)) {
            return null;
        }

        try {
            $result = (new ConfigJobService())->rollback(
                (int) ($job['id'] ?? 0),
                (int) ($target['pf_id'] ?? 0),
                // No requester: nobody asked for this one, least of all the person who pushed. That is
                // also what makes a gated rollback approvable by any administrator rather than blocked
                // by a separation-of-duties rule that has no first party to separate from.
                null
            );
        } catch (\Throwable $e) {
            log_message('error', 'Automatic rollback could not be raised for target #' . ($target['id'] ?? '?') . ': ' . $e->getMessage());

            return ['queued' => false, 'job_id' => null, 'gated' => false, 'message' => 'The rollback could not be queued: ' . $e->getMessage()];
        }

        return [
            'queued'  => (bool) $result['ok'],
            'job_id'  => $result['job_id'] ?? null,
            'gated'   => (bool) ($result['requires_approval'] ?? false),
            'message' => (string) $result['message'],
        ];
    }

    /**
     * Confirms a rollback on the source change it undid.
     *
     * The source target keeps saying `drifted`, and that is the honest answer: the change it carried is
     * not on the device any more, because we deliberately took it off. What changes is that the drift is
     * *answered* — its alert is closed with a reason that says so, the target carries a note naming the
     * rollback, and `rollback_job_id` keeps it out of the repair list, where "re-apply this change"
     * would undo the rollback.
     *
     * Returns a note for the caller, or null when this was not a rollback (the common case).
     */
    private function settleRollbackSource(array $job, array $device): ?string
    {
        $sourceJobId = (int) ($job['rollback_of_job_id'] ?? 0);
        $pfId        = (int) ($device['id'] ?? 0);
        $rollbackId  = (int) ($job['id'] ?? 0);

        if ($sourceJobId <= 0 || $pfId <= 0) {
            return null;
        }

        try {
            $row = $this->targetModel
                ->where('job_id', $sourceJobId)
                ->where('pf_id', $pfId)
                ->first();

            if (!$row) {
                return null;
            }

            $this->targetModel->update((int) $row['id'], [
                'verify_detail' => mb_substr(
                    'Rolled back by job #' . $rollbackId . ': the device is running the configuration it had '
                        . 'before job #' . $sourceJobId . ' was applied, confirmed by reading it back.',
                    0,
                    600
                ),
            ]);

            $closed = $this->alerts->resolveForTarget((int) $row['id'], 'rollback', ConfigAlertService::RESOLVED_ROLLED_BACK);
        } catch (\Throwable $e) {
            log_message('error', 'Rollback #' . $rollbackId . ' could not be settled on job #' . $sourceJobId . ': ' . $e->getMessage());

            return null;
        }

        return 'Job #' . $sourceJobId . ' is no longer expected on device #' . $pfId
            . ': rollback #' . $rollbackId . ' was confirmed'
            . ($closed > 0 ? ', and its drift alert was closed.' : '.');
    }

    /**
     * Queues the original job's drifted target for a fresh check after its reconcile succeeded.
     *
     * Returns a note for the caller, or null when this was not a reconcile (the common case).
     */
    private function reopenSourceTarget(array $job, array $device): ?string
    {
        $sourceJobId = (int) ($job['reconcile_of_job_id'] ?? 0);
        $pfId = (int) ($device['id'] ?? 0);

        if ($sourceJobId <= 0 || $pfId <= 0) {
            return null;
        }

        try {
            $row = $this->targetModel
                ->where('job_id', $sourceJobId)
                ->where('pf_id', $pfId)
                ->where('verify_status', JobTargetModel::VERIFY_DRIFTED)
                ->first();

            if (!$row) {
                return null;
            }

            $this->targetModel->update((int) $row['id'], [
                'verify_status' => JobTargetModel::VERIFY_PENDING,
                'verify_detail' => 'Reconciled by job #' . (int) ($job['id'] ?? 0) . '; its own read-back will confirm it.',
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Source target could not be reopened after a reconcile: ' . $e->getMessage());

            return null;
        }

        return 'Job #' . $sourceJobId . ' target re-opened for verification; it stays drifted until its own read agrees.';
    }

    /**
     * Raises or clears the drift alert for a recorded verdict. Never lets an alerting problem break
     * the verification that produced it.
     *
     * @return array{action:string, message:string, notified:bool}|null
     */
    private function alert(array $target, array $job, array $device, string $status, string $detail, string $kind = ConfigAlertService::KIND_DRIFT, ?array $rollback = null): ?array
    {
        try {
            if ($status === JobTargetModel::VERIFY_DRIFTED) {
                $result = $this->alerts->raise($target, $job, $device, $detail, $kind, $rollback);

                return [
                    'action'    => $result['raised'] ? ($result['escalated'] ?? false ? 'escalated' : 'raised') : 'unchanged',
                    'message'   => $result['message'],
                    'notified'  => $result['notified'],
                    'kind'      => $result['alert']['kind'] ?? $kind,
                    'escalated' => (bool) ($result['escalated'] ?? false),
                    'rollback'  => $rollback,
                    // Whether this revert turned out to be part of a fleet pattern, and which alert now
                    // speaks for it. It travels with the verdict so the runner's log line and the harness
                    // can say that a group was recognised rather than that an email went out per branch.
                    'pattern'   => $result['pattern'] ?? null,
                ];
            }

            if ($status === JobTargetModel::VERIFY_MATCHED) {
                $closed = $this->alerts->resolveForTarget((int) ($target['id'] ?? 0), 'verification', ConfigAlertService::RESOLVED_MATCHED);

                return $closed > 0
                    ? ['action' => 'resolved', 'message' => 'Device matches the job again; ' . $closed . ' drift alert(s) resolved.', 'notified' => false]
                    : null;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Drift alerting failed for target #' . ($target['id'] ?? '?') . ': ' . $e->getMessage());
        }

        return null;
    }

    private function logDrift(array $target, array $job, array $device, string $detail): void
    {
        $deviceName = (string) ($device['name'] ?? ('device #' . $target['pf_id']));

        $logId = $this->logWriter->start(
            (string) ($job['batch_id'] ?? ''),
            $device,
            $deviceName,
            $job['target_spec']['type'] ?? null,
            (string) ($job['payload_type'] ?? 'xml'),
            isset($job['requested_by']) ? (int) $job['requested_by'] : null,
            'Post-apply verification.'
        );

        $this->logWriter->finish($logId, [
            'status'      => 'drifted',
            'message'     => 'Verification: ' . $detail,
            'ip'          => $device['man_ip'] ?? null,
            'backup_id'   => $target['backup_id'] ?? null,
            'backup_file' => $target['backup_file'] ?? null,
            'log'         => 'Job #' . ($job['id'] ?? '?') . ' was applied but the device no longer matches it.',
        ]);
    }

    /**
     * Verify one target against a configuration that has already been obtained.
     *     * @param array $target job_targets row
     * @param array $job     config_jobs row
     * @param array $device  pf_inventory row
     * @param string $current configuration read from (or reported by) the device
     *
     * @return array{status:string, detail:string, alert:?array}
     */
    public function verify(array $target, array $job, array $device, string $current): array
    {
        $outcome = $this->check($job, $current);

        return $this->record($target, $job, $device, $outcome['status'], $outcome['detail']);
    }

    /**
     * @param list<string> $lines
     */
    private function summariseStrings(array $lines): string
    {
        $named = array_slice($lines, 0, self::MAX_NAMED);
        $extra = count($lines) - count($named);

        return implode('; ', $named) . ($extra > 0 ? ' (+' . $extra . ' more)' : '');
    }

    /**
     * @param list<array> $changes
     */
    private function summariseChanges(array $changes): string
    {
        $named = [];

        foreach (array_slice($changes, 0, self::MAX_NAMED) as $change) {
            $named[] = $change['path'] . ' (expected ' . $this->value($change['after']) . ', found ' . $this->value($change['before']) . ')';
        }

        $extra = count($changes) - count($named);

        return implode('; ', $named) . ($extra > 0 ? ' (+' . $extra . ' more)' : '');
    }

    private function value(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return 'nothing';
        }

        return mb_strlen($value) > 60 ? mb_substr($value, 0, 60) . '…' : $value;
    }
}
