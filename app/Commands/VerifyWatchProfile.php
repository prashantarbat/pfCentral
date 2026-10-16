<?php

namespace App\Commands;

use App\Models\ConfigJobModel;
use App\Models\JobTargetModel;
use App\Models\PfBakupModel;
use App\Models\PfInventoryModel;
use App\Services\ConfigPush\ConfigAlertMailer;
use App\Services\ConfigPush\ConfigAlertService;
use App\Services\ConfigPush\ConfigJobService;
use App\Services\ConfigPush\ConfigSnapshotStore;
use App\Services\ConfigPush\ConfigVerificationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * The verification window is a property of the *change*, and this harness proves the whole path of it.
 *
 * The unit tests pin the pure arithmetic — which minutes a profile means, what a stored schedule is allowed
 * to be, and where the revert boundary falls. What only a database can show is the wiring: that a job
 * created with a profile **stores** the schedule it resolved to, that the window it opens really runs that
 * long, that a *later* reading inside a day-long window is still classified as a revert rather than as an
 * ordinary drift, that the sweep still finds a target hours into such a window (the bound is the thing
 * that would silently drop it), that a script is never watched whatever the form said, and that a repair
 * and a rollback inherit the source change's window rather than the system default.
 *
 * Fixtures only; auto-dispatch is switched off in-process, so no runner is spawned and no device is ever
 * contacted. Everything it creates is deleted again, and the database is compared against a baseline.
 */
class VerifyWatchProfile extends BaseCommand
{
    protected $group       = 'Config';
    protected $name        = 'verify:watchprofile';
    protected $description = 'Harness: the per-job verification window (once / the first hour / a day / not watched).';
    protected $usage       = 'verify:watchprofile';

    /** @var list<string> */
    private array $pass = [];

    /** @var list<string> */
    private array $fail = [];

    /** @var list<int> */
    private array $jobIds = [];

    /** @var list<int> */
    private array $deviceIds = [];

    /** @var list<int> */
    private array $backupIds = [];

    /** @var list<string> */
    private array $files = [];

    /** @var array<string, int|array> */
    private array $fx = [];

    public function run(array $params)
    {
        $_ENV['configJobRunner.autoDispatch'] = 'false';

        $before = $this->counts();

        try {
            $this->sectionA();
            $this->sectionB();
            $this->sectionC();
            $this->sectionD();
            $this->sectionE();
            $this->sectionF();
            $this->sectionG();
        } finally {
            $this->cleanup();
        }

        $after = $this->counts();

        foreach ($before as $table => $count) {
            $this->check("baseline restored: {$table} back to {$count}", ($after[$table] ?? -1) === $count);
        }

        CLI::newLine();
        CLI::write('  PASS ' . count($this->pass) . '   FAIL ' . count($this->fail), $this->fail === [] ? 'green' : 'red');

        foreach ($this->fail as $message) {
            CLI::write('   - ' . $message, 'red');
        }

        return $this->fail === [] ? EXIT_SUCCESS : EXIT_ERROR;
    }

    // ------------------------------------------------------------------ A: a day-long window

    private function sectionA(): void
    {
        CLI::write('A. A job that asks to be watched for a day stores that, and gets it', 'yellow');

        $device   = $this->device('WATCH-A');
        $extended = ConfigVerificationService::watchProfileMinutes(ConfigVerificationService::WATCH_PROFILE_EXTENDED);
        $job      = $this->job($device, ['type' => 'group', 'name' => 'watch-a'], ConfigVerificationService::WATCH_PROFILE_EXTENDED);

        // Applied three hours ago, so the schedule has slots behind it as well as ahead — which is what the
        // sweep's bound is actually up against later in section B.
        $target = $this->applied($job, $device, '-3 hours', $extended);
        $jobRow = $this->jobRow($job);

        $this->check('the profile the operator chose is on the job', ($jobRow['watch_profile'] ?? '') === ConfigVerificationService::WATCH_PROFILE_EXTENDED);
        $this->check('and the schedule it resolved to is frozen there', ConfigVerificationService::watchScheduleFor($jobRow) === $extended);
        $this->check('which is a day long, not an hour', end($extended) === 1440 && count($extended) === 9);

        $this->check('the window opens', $this->verifier()->startWatch((int) $target['id'], $jobRow, $device) === true);

        $fresh = $this->targetRow((int) $target['id']);

        $this->check(
            'and it closes a day after the push, not an hour',
            ($fresh['watch_until'] ?? '') === date('Y-m-d H:i:s', strtotime((string) $target['applied_at']) + (24 * 60 * 60))
        );
        $this->check('the first check is the first slot of that schedule', ($fresh['watch_next_at'] ?? '') === ConfigVerificationService::watchDueAt((string) $target['applied_at'], 0, $extended));
        $this->check('pfCentral is the reader, because this device can be reached over SSH', ($fresh['watch_via'] ?? '') === ConfigVerificationService::WATCH_VIA_SSH);
        $this->check(
            'and the badge says how long it is watched for, in the operator\'s words',
            ConfigVerificationService::watchSummary($fresh, null, $jobRow)['profile_label'] === 'watched for a day'
        );

        $this->fx['A'] = ['job' => $job, 'target' => (int) $target['id'], 'device' => (int) $device['id']];
    }

    // ------------------------------------------------------------------ B: the sweep keeps finding it

    private function sectionB(): void
    {
        CLI::write('B. Four checks into a day-long window, it is still outstanding', 'yellow');

        $a        = $this->fx['A'];
        $extended = ConfigVerificationService::watchProfileMinutes(ConfigVerificationService::WATCH_PROFILE_EXTENDED);
        $target   = $this->targetRow((int) $a['target']);

        // The fifth slot of the day-long window has come due, two hours after the push. A finder bounded by
        // the *default* schedule's four slots would drop this target here and never read the device again —
        // the window would say \"watched for a day\" and quietly stop after an hour. That bound is exactly
        // what this section exists to pin.
        $this->db()->table('job_targets')->where('id', (int) $a['target'])->update([
            'watch_checks'  => 4,
            'watch_next_at' => ConfigVerificationService::watchDueAt((string) $target['applied_at'], 4, $extended),
        ]);

        $due = array_map('intval', array_column($this->verifier()->watchDue(50), 'id'));

        $this->check('the fifth slot of the day-long window is behind us', strtotime((string) $this->targetRow((int) $a['target'])['watch_next_at']) <= time());
        $this->check('the day-long profile has more slots than the default the sweep used to be bounded by', count($extended) > count(ConfigVerificationService::WATCH_SCHEDULE));
        $this->check('and the sweep still finds the target four checks in', in_array((int) $a['target'], $due, true));

        $this->check(
            'advancing it moves to the next slot rather than closing the window',
            $this->verifier()->advanceWatch((int) $a['target'], $target, 4, false, $extended)
                === ConfigVerificationService::watchDueAt((string) $target['applied_at'], 5, $extended)
        );
        $this->check(
            'and the last slot is what closes it',
            $this->verifier()->advanceWatch((int) $a['target'], $target, count($extended) - 1, false, $extended) === null
        );
        $this->check(
            'so there is no slot past the end for a hand-edited row to walk',
            ConfigVerificationService::watchDueAt((string) $target['applied_at'], count($extended), $extended) === null
        );

        // The bound that makes a hand-edited row fail safe rather than loop forever. It is a constant
        // rather than each job's own length on purpose (a per-row JSON_LENGTH would put the window's
        // arithmetic into SQL, where it could not be tested), so what matters is that it is *above* every
        // real profile — a test the profiles themselves carry — and that a row at the bound is dropped.
        $this->db()->table('job_targets')->where('id', (int) $a['target'])->update(['watch_checks' => ConfigVerificationService::MAX_WATCH_SLOTS]);

        $this->check(
            'a row at the bound is dropped rather than walked forever',
            !in_array((int) $a['target'], array_map('intval', array_column($this->verifier()->watchDue(50), 'id')), true)
        );

        $this->db()->table('job_targets')->where('id', (int) $a['target'])->update(['watch_checks' => 4]);
    }

    // ------------------------------------------------------------------ C: once, and not at all

    private function sectionC(): void
    {
        CLI::write('C. Watched once, and deliberately not watched at all', 'yellow');

        $device = $this->device('WATCH-C');
        $once   = $this->job($device, ['type' => 'group', 'name' => 'watch-c'], ConfigVerificationService::WATCH_PROFILE_ONCE);
        $target = $this->applied($once, $device, '-1 minutes', ConfigVerificationService::watchProfileMinutes(ConfigVerificationService::WATCH_PROFILE_ONCE));

        $this->check('a once-watched job opens a window', $this->verifier()->startWatch((int) $target['id'], $this->jobRow($once), $device) === true);

        $fresh = $this->targetRow((int) $target['id']);

        $this->check(
            'that closes after its single read',
            ($fresh['watch_until'] ?? '') === date('Y-m-d H:i:s', strtotime((string) $target['applied_at']) + (10 * 60))
        );
        $this->check(
            'and has nothing outstanding after that read',
            $this->verifier()->advanceWatch((int) $target['id'], $fresh, 0, false, [10]) === null
        );
        $this->check(
            'so one read is the whole of it',
            ConfigVerificationService::watchSummary($fresh, null, $this->jobRow($once))['total'] === 1
        );

        // Not watched at all: an XML change the operator decided nobody needs reading back.
        $device2 = $this->device('WATCH-C2');
        $none    = $this->job($device2, ['type' => 'group', 'name' => 'watch-c2'], ConfigVerificationService::WATCH_PROFILE_NONE);
        $target2 = $this->applied($none, $device2, '-1 minutes', []);

        $this->check('a job that asked not to be watched stores nothing to walk', ConfigVerificationService::watchScheduleFor($this->jobRow($none)) === []);
        $this->check('and no window opens', $this->verifier()->startWatch((int) $target2['id'], $this->jobRow($none), $device2) === false);

        $untouched = $this->targetRow((int) $target2['id']);

        $this->check('so nothing is outstanding on the device', ($untouched['watch_next_at'] ?? null) === null && ($untouched['watch_until'] ?? null) === null);
        $this->check(
            'and it is not offered by the sweep',
            !in_array((int) $target2['id'], array_map('intval', array_column($this->verifier()->watchDue(200), 'id')), true)
        );
        $this->check(
            'and the badge says so rather than showing an empty window',
            ConfigVerificationService::watchSummary($untouched, null, $this->jobRow($none))['profile_label'] === 'not watched'
        );
    }

    // ------------------------------------------------------------------ D: a script is never watched

    private function sectionD(): void
    {
        CLI::write('D. A script is never watched, whatever the form said', 'yellow');

        $device = $this->device('WATCH-D');

        // The form now sends a window with every push, and a script's is forced to `none`: there is no
        // intended configuration to compare, so a window could only ever record \"could not confirm\" —
        // records that would overwrite the verdict the device's own report gave.
        $jobId = $this->jobService()->createJob([
            'payload'       => "#!/bin/sh\necho harness\n",
            'payload_type'  => 'script',
            'target_spec'   => ['type' => 'group', 'name' => 'watch-d'],
            'watch_profile' => ConfigVerificationService::WATCH_PROFILE_EXTENDED,
        ], [$device]);

        $this->jobIds[] = $jobId;

        $jobRow = $this->jobRow($jobId);

        $this->check('the stored profile is "not watched"', ($jobRow['watch_profile'] ?? '') === ConfigVerificationService::WATCH_PROFILE_NONE);
        $this->check('with no schedule to walk', ConfigVerificationService::watchScheduleFor($jobRow) === []);
        $this->check('the paying-for-nothing question is decided from the job', $this->verifier()->needsRead($jobRow) === false);

        $target = $this->applied($jobId, $device, '-1 minutes', []);

        $this->check('and an applied script gets no window even on a reachable device', $this->verifier()->startWatch((int) $target['id'], $jobRow, $device) === false);

        $this->fx['D'] = $jobId;
    }

    // ------------------------------------------------------------------ E: repairs inherit the window

    private function sectionE(): void
    {
        CLI::write('E. A repair, and a rollback, are watched as long as the change they answer', 'yellow');

        $a        = $this->fx['A'];
        $device   = (new PfInventoryModel())->find($a['device']);
        $extended = ConfigVerificationService::watchProfileMinutes(ConfigVerificationService::WATCH_PROFILE_EXTENDED);

        // A drifted target, which is what a repair is aimed at.
        $this->drift((int) $a['target'], 'The device configuration still differs in 1 setting(s).');

        $repair = $this->jobService()->reconcile((int) $a['job'], 0);

        $this->check('a repair is queued', ($repair['ok'] ?? false) === true);

        $repairJob = $this->jobRow((int) ($repair['job_id'] ?? 0));

        $this->check('and inherits the profile the change was pushed with', ($repairJob['watch_profile'] ?? '') === ConfigVerificationService::WATCH_PROFILE_EXTENDED);
        $this->check('with the same schedule, frozen rather than re-derived later', ConfigVerificationService::watchScheduleFor($repairJob) === $extended);

        // A revert of that same change, found two hours later — *inside* the day-long window this job asked
        // for, and well outside the default hour. This is the case the per-job window exists for: before it,
        // this reading would have been an ordinary drift and the pre-push snapshot unreachable.
        $snapshot = $this->snapshot($device, 'watch-e-pre-push.xml');
        $target   = $this->applied((int) $a['job'], $device, '-2 hours', $extended, [
            'backup_id'   => $snapshot['id'],
            'backup_file' => $snapshot['file'],
        ]);

        $this->drift((int) $target['id'], 'The device configuration no longer matches the pushed change.');

        $this->check(
            'the same difference two hours in is a revert under a day-long window',
            ConfigVerificationService::driftKind($this->targetRow((int) $target['id']), null, ConfigVerificationService::watchScheduleFor($this->jobRow((int) $a['job']))) === ConfigAlertService::KIND_REVERT
        );
        // The counterfactual, spelled out rather than implied: the row as the *default* window would have
        // left it — same push, same reading, a boundary an hour after it instead of a day. The window is
        // what decides; nothing else about the change differs.
        $asDefault = array_merge($this->targetRow((int) $target['id']), [
            'watch_until' => ConfigVerificationService::watchEndsAt((string) $target['applied_at'], ConfigVerificationService::WATCH_SCHEDULE),
        ]);

        $this->check(
            'and the same reading under the default hour would be an ordinary drift',
            ConfigVerificationService::driftKind($asDefault, null, ConfigVerificationService::WATCH_SCHEDULE) === ConfigAlertService::KIND_DRIFT
        );
        $this->check(
            'so the only thing that made it a revert is the window this job chose',
            ConfigVerificationService::driftKind($this->targetRow((int) $target['id']), null, $extended) === ConfigAlertService::KIND_REVERT
        );

        $rollback = $this->jobService()->rollback((int) $a['job'], (int) $device['id'], 0);

        $this->check('so a rollback is queued for it', ($rollback['ok'] ?? false) === true);

        $rollbackJob = $this->jobRow((int) ($rollback['job_id'] ?? 0));

        $this->check('and the restore inherits the same window', ($rollbackJob['watch_profile'] ?? '') === ConfigVerificationService::WATCH_PROFILE_EXTENDED);
        $this->check('frozen, like every other window', ConfigVerificationService::watchScheduleFor($rollbackJob) === $extended);
        $this->check('the restore is an ordinary job carrying the snapshot as its payload', str_contains((string) ($rollbackJob['rendered_payload'] ?? ''), '<pfsense>'));

        // And a repair of a *default* change stays on the default, so the inheritance is the source's
        // profile rather than \"everything inherits the longest window\".
        $plain     = $this->device('WATCH-E2');
        $plainJob  = $this->job($plain, ['type' => 'group', 'name' => 'watch-e2'], ConfigVerificationService::WATCH_PROFILE_STANDARD);
        $plainTgt  = $this->applied($plainJob, $plain, '-5 minutes', ConfigVerificationService::WATCH_SCHEDULE);
        $this->drift((int) $plainTgt['id'], 'Still differs in 1 setting(s).');

        $plainRepair    = $this->jobService()->reconcile($plainJob, 0);
        $plainRepairJob = $this->jobRow((int) ($plainRepair['job_id'] ?? 0));

        $this->check('a repair of a default-watched change stays on the default', ($plainRepairJob['watch_profile'] ?? '') === ConfigVerificationService::WATCH_PROFILE_STANDARD);
        $this->check('with the default four slots', ConfigVerificationService::watchScheduleFor($plainRepairJob) === ConfigVerificationService::WATCH_SCHEDULE);
    }

    // ------------------------------------------------------------------ F: a day-long window classifies differently

    private function sectionF(): void
    {
        CLI::write('F. Inside a day-long window, two hours later is still a revert', 'yellow');

        $device   = $this->device('WATCH-F');
        $extended = ConfigVerificationService::watchProfileMinutes(ConfigVerificationService::WATCH_PROFILE_EXTENDED);

        // Applied two hours ago, on a window that is still open. A difference found now was found *while we
        // were watching*: the same event the window exists for, just later in it.
        $longJob    = $this->job($device, ['type' => 'group', 'name' => 'watch-f-long'], ConfigVerificationService::WATCH_PROFILE_EXTENDED);
        $longTarget = $this->applied($longJob, $device, '-2 hours', $extended);

        $this->recordDrift((int) $longTarget['id'], $device, 'Long window, two hours in.');

        $longAlert = $this->alertFor((int) $longTarget['id']);

        $this->check('the long window really is open two hours in', ($this->targetRow((int) $longTarget['id'])['watch_until'] ?? '') === date('Y-m-d H:i:s', strtotime((string) $longTarget['applied_at']) + (24 * 60 * 60)));
        $this->check('so the difference is recorded as a revert', ($longAlert['kind'] ?? '') === ConfigAlertService::KIND_REVERT);
        $this->check('and the alert says REVERTED, not "no longer matches"', str_starts_with((string) ($longAlert['summary'] ?? ''), 'REVERTED:'));
        $this->check('naming how long after the push it was undone', (bool) preg_match('/undid job #\d+ 120 minute\(s\) after it was applied/', (string) ($longAlert['summary'] ?? '')));

        // The same reading on a default-watched change of the same age: that window closed after an hour, so
        // this is a later change rather than the device undoing the push — the distinction the window is for.
        $shortJob    = $this->job($device, ['type' => 'group', 'name' => 'watch-f-short'], ConfigVerificationService::WATCH_PROFILE_STANDARD);
        $shortTarget = $this->applied($shortJob, $device, '-2 hours', ConfigVerificationService::WATCH_SCHEDULE);

        $this->recordDrift((int) $shortTarget['id'], $device, 'Default window, two hours in.');

        $shortAlert = $this->alertFor((int) $shortTarget['id']);

        $this->check('the same difference after a one-hour window is ordinary drift', ($shortAlert['kind'] ?? '') === ConfigAlertService::KIND_DRIFT);
        $this->check('worded as a drift, not a revert', !str_contains((string) ($shortAlert['summary'] ?? ''), 'REVERTED'));
        $this->check('and nothing was rolled back for it', ($shortAlert['rollback_job_id'] ?? null) === null);
    }

    // ------------------------------------------------------------------ G: what the dashboard reads

    private function sectionG(): void
    {
        CLI::write('G. The dashboard describes the job\'s window, not the system\'s', 'yellow');

        $a      = $this->fx['A'];
        $detail = $this->jobService()->jobDetail((int) $a['job']);

        $this->check('the job detail carries the window', $detail !== null && ($detail['window']['profile'] ?? '') === ConfigVerificationService::WATCH_PROFILE_EXTENDED);
        $this->check('named for a person to read', ($detail['window']['profile_label'] ?? '') === 'watched for a day');
        $this->check('with the number of reads it stands for', (int) ($detail['window']['total'] ?? 0) === 9);

        $target = null;

        foreach ($detail['targets'] as $candidate) {
            if ((int) $candidate['id'] === (int) $a['target']) {
                $target = $candidate;
            }
        }

        $this->check('and the device badge counts the checks that window actually has', $target !== null && (int) ($target['watch']['total'] ?? 0) === 9);
        $this->check('and names it', ($target['watch']['profile_label'] ?? '') === 'watched for a day');

        $script = $this->jobService()->jobDetail((int) $this->fx['D']);

        $this->check('a script job reads as not watched', $script !== null && ($script['window']['profile_label'] ?? '') === 'not watched');
        $this->check('with no reads behind it', (int) ($script['window']['total'] ?? -1) === 0);
    }

    // ------------------------------------------------------------------ fixtures

    private function device(string $label): array
    {
        $model = new PfInventoryModel();
        $id    = (int) $model->insert([
            'name'     => 'ZZZ-' . $label . '-' . bin2hex(random_bytes(3)),
            'active'   => 0,
            'man_ip'   => '',
            'man_port' => '',
            // The SSH transport's own test for \"can this reader read it\": credentials present. No
            // connection is ever opened — nothing in this harness delivers anything.
            'username' => 'harness',
            'password' => 'harness',
        ], true);

        $this->deviceIds[] = $id;

        return $model->find($id) ?? ['id' => $id, 'name' => $label];
    }

    private function job(array $device, array $spec, string $profile): int
    {
        $jobId = $this->jobService()->createJob([
            'payload'       => '<?xml version="1.0"?><pfsense><system><hostname>' . ($device['name'] ?? 'x') . '</hostname></system></pfsense>',
            'payload_type'  => 'xml',
            'target_spec'   => $spec,
            'watch_profile' => $profile,
        ], [$device]);

        $this->jobIds[] = $jobId;

        return $jobId;
    }

    /**
     * Makes the target `createJob()` already made look like a push that ran, under a given window.
     *
     * @param list<int> $schedule
     */
    private function applied(int $jobId, array $device, string $when, array $schedule, array $extra = []): array
    {
        $appliedAt = date('Y-m-d H:i:s', strtotime($when));

        $this->db()->table('job_targets')
            ->where('job_id', $jobId)
            ->where('pf_id', (int) $device['id'])
            ->update(array_merge([
                'status'           => JobTargetModel::STATUS_APPLIED,
                'attempts'         => 1,
                'applied_at'       => $appliedAt,
                'delivered_at'     => $appliedAt,
                'verify_status'    => JobTargetModel::VERIFY_PENDING,
                'watch_started_at' => $appliedAt,
                'watch_until'      => $schedule === [] ? null : ConfigVerificationService::watchEndsAt($appliedAt, $schedule),
                'watch_next_at'    => $schedule === [] ? null : ConfigVerificationService::watchDueAt($appliedAt, 0, $schedule),
                'watch_checks'     => 0,
                'watch_via'        => ConfigVerificationService::WATCH_VIA_SSH,
                'updated_at'       => date('Y-m-d H:i:s'),
            ], $extra));

        return $this->targetRow($this->targetIdFor($jobId, (int) $device['id']));
    }

    /** Records that the device has been read back and found wanting. */
    private function drift(int $targetId, string $detail): void
    {
        $this->db()->table('job_targets')->where('id', $targetId)->update([
            'verify_status' => JobTargetModel::VERIFY_DRIFTED,
            'verify_detail' => $detail,
            'verified_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{id:int, file:string, path:string, contents:string}
     */
    private function snapshot(array $device, string $fileName): array
    {
        $contents = '<?xml version="1.0"?>' . "\n" . '<pfsense><system><hostname>watch-harness</hostname></system></pfsense>' . "\n";
        $dir      = WRITEPATH . 'noc_backup/' . ConfigSnapshotStore::safeName((string) $device['name']);
        $file     = $dir . '/' . $fileName;

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents($file, $contents);
        $this->files[] = $file;

        $backupId = (int) (new PfBakupModel())->insert([
            'pf_id'      => (int) $device['id'],
            'file_name'  => $fileName,
            'file_path'  => $file,
            'quantity'   => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        $this->backupIds[] = $backupId;

        return ['id' => $backupId, 'file' => $fileName, 'path' => $file, 'contents' => $contents];
    }

    private function recordDrift(int $targetId, array $device, string $detail): array
    {
        $target = $this->targetRow($targetId);

        return $this->verifier()->record($target, $this->jobRow((int) $target['job_id']), $device, JobTargetModel::VERIFY_DRIFTED, $detail);
    }

    /** The verification service with a mailer that records instead of sending. */
    private function verifier(): ConfigVerificationService
    {
        $mailer = new class extends ConfigAlertMailer {
            protected function setting(string $name, string $default = ''): string
            {
                return ['app_name' => 'pfCentral', 'app_url' => 'https://pfcentral.example.test/'][$name] ?? $default;
            }

            public function send(array $alert, array $context = []): array
            {
                return ['ok' => true, 'message' => 'recorded by the harness', 'recipients' => ['harness@example.test']];
            }
        };

        return new ConfigVerificationService(null, null, null, null, null, new ConfigAlertService(null, $mailer, null, null), null);
    }

    private function jobService(): ConfigJobService
    {
        return new ConfigJobService();
    }

    // ------------------------------------------------------------------ helpers

    private function jobRow(int $jobId): ?array
    {
        return (new ConfigJobModel())->find($jobId) ?: null;
    }

    private function targetIdFor(int $jobId, int $pfId): int
    {
        $row = $this->db()->table('job_targets')->where('job_id', $jobId)->where('pf_id', $pfId)->get()->getRowArray();

        return (int) ($row['id'] ?? 0);
    }

    private function targetRow(int $targetId): array
    {
        return $this->db()->table('job_targets')->where('id', $targetId)->get()->getRowArray() ?? [];
    }

    private function alertFor(int $targetId): array
    {
        return $this->db()->table('config_alerts')->where('target_id', $targetId)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
    }

    private function db()
    {
        return db_connect();
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $db = $this->db();

        return [
            'config_jobs'      => $db->table('config_jobs')->countAllResults(),
            'job_targets'      => $db->table('job_targets')->countAllResults(),
            'config_alerts'    => $db->table('config_alerts')->countAllResults(),
            'config_push_logs' => $db->table('config_push_logs')->countAllResults(),
            'pf_backups'       => $db->table('pf_backups')->countAllResults(),
            'pf_inventory'     => $db->table('pf_inventory')->countAllResults(),
        ];
    }

    private function cleanup(): void
    {
        $db      = $this->db();
        $allJobs = $db->table('config_jobs')->select('id, batch_id, rollback_of_job_id, reconcile_of_job_id')->get()->getResultArray();
        $mine    = [];
        $batches = [];

        foreach ($allJobs as $row) {
            $id = (int) $row['id'];

            // This run's jobs, plus the repairs and rollbacks they raised.
            if (
                in_array($id, $this->jobIds, true)
                || in_array((int) ($row['rollback_of_job_id'] ?? 0), $this->jobIds, true)
                || in_array((int) ($row['reconcile_of_job_id'] ?? 0), $this->jobIds, true)
            ) {
                $mine[]    = $id;
                $batches[] = (string) $row['batch_id'];
            }
        }

        if ($mine !== []) {
            $db->table('config_alerts')->whereIn('job_id', $mine)->delete();
            $db->table('config_push_logs')->whereIn('batch_id', $batches)->delete();
            $db->table('job_targets')->whereIn('rollback_job_id', $mine)->update(['rollback_job_id' => null]);
            $db->table('job_targets')->whereIn('job_id', $mine)->delete();
            $db->table('config_jobs')->whereIn('id', $mine)->delete();
        }

        if ($this->backupIds !== []) {
            $db->table('pf_backups')->whereIn('id', $this->backupIds)->delete();
        }

        if ($this->deviceIds !== []) {
            // Alerts the drift reports raised for this run's devices, and any log rows keyed to them.
            $db->table('config_alerts')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('config_push_logs')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('pf_inventory')->whereIn('id', $this->deviceIds)->delete();
        }

        foreach ($this->files as $file) {
            @unlink($file);
            @rmdir(dirname($file));
        }
    }

    private function check(string $description, bool $ok): void
    {
        if ($ok) {
            $this->pass[] = $description;
            CLI::write('   PASS  ' . $description, 'green');

            return;
        }

        $this->fail[] = $description;
        CLI::write('   FAIL  ' . $description, 'red');
    }
}
