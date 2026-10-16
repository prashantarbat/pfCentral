<?php

namespace App\Commands;

use App\Models\ConfigAlertModel;
use App\Models\ConfigJobModel;
use App\Models\JobTargetModel;
use App\Models\PfInventoryModel;
use App\Services\ConfigPush\ConfigAlertMailer;
use App\Services\ConfigPush\ConfigAlertService;
use App\Services\ConfigPush\ConfigJobService;
use App\Services\ConfigPush\ConfigVerificationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Several branches undoing one change is one alert, and this harness proves the whole of it.
 *
 * The unit tests pin the rules that can be read off a row — the threshold, the wording, the vocabulary, the
 * count that must never make the fleet look healthier than it is. What only a database can show is the
 * grouping *happening*: that the third device to undo a change produces one alert about the change instead
 * of a third alert about a device, that the two before it were already emailed (nothing is unsent), that the
 * grouped devices stop being emailed individually without disappearing, that the count on the row keeps up
 * as more branches join while the email is not re-sent, that a later drift on the same change is *not*
 * dragged into it, and that repairing or dismissing the pattern accounts for every device in it.
 *
 * Fixtures only, with a mailer that records instead of sending and auto-dispatch switched off in-process, so
 * nothing touches a real branch. Everything it creates is deleted again and the database is compared
 * against a baseline.
 */
class VerifyRevertPattern extends BaseCommand
{
    protected $group       = 'Config';
    protected $name        = 'verify:revertpattern';
    protected $description = 'Harness: several devices undoing one change become a single fleet-pattern alert.';
    protected $usage       = 'verify:revertpattern';

    /** @var list<string> */
    private array $pass = [];

    /** @var list<string> */
    private array $fail = [];

    /** @var list<int> */
    private array $jobIds = [];

    /** @var list<int> */
    private array $deviceIds = [];

    /** @var array<string, mixed> */
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
            $this->sectionH();
            $this->sectionI();
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

    // ------------------------------------------------------------------ A: two devices are still two alerts

    private function sectionA(): void
    {
        CLI::write('A. One or two branches undoing a change is still a branch problem', 'yellow');

        $fleet = $this->fleet('PAT-A', 5);
        $job   = $fleet['job'];

        $first = $this->revert($fleet, 0, 'Setting 1 differs.');
        $this->check('the first revert is its own alert', $first['kind'] === ConfigAlertModel::KIND_REVERT && $first['grouped_into'] === null);

        $second = $this->revert($fleet, 1, 'Setting 1 differs.');
        $this->check('and so is the second', $second['kind'] === ConfigAlertModel::KIND_REVERT && $second['grouped_into'] === null);

        $this->check('two reverts of one change are not grouped', $this->patternFor($job) === null);

        // Nothing has been sent yet, and that is the feature rather than a missing step: the email waits one
        // read cycle, which is exactly the interval in which a second and third device are read.
        $this->check('neither revert has been emailed yet', count(PatternHarnessMailer::$sent) === 0);
        $this->check('both are held', ($first['notify_after'] ?? null) !== null && ($second['notify_after'] ?? null) !== null);
        $this->check(
            'for one read cycle after they were raised',
            (string) $first['notify_after'] === date('Y-m-d H:i:s', strtotime((string) $first['raised_at']) + (ConfigAlertService::REVERT_EMAIL_GRACE_MINUTES * 60))
        );
        $this->check('and the hold is described as a hold, not as a mail failure', ConfigAlertService::notifyState($first)['state'] === 'deferred');
        $this->check('so nothing is queued for the sweep yet either', $this->pendingIds() === []);

        $fleet['alerts'] = [$first['id'], $second['id']];

        $this->fx['A'] = $fleet;
    }

    // ------------------------------------------------------------------ B: the third makes it one story

    private function sectionB(): void
    {
        CLI::write('B. The third device turns the same reverts into one alert about the change', 'yellow');

        $fleet = $this->fx['A'];
        $job   = $fleet['job'];
        $before = count(PatternHarnessMailer::$sent);

        $third = $this->revert($fleet, 2, 'Setting 1 differs.');

        $pattern = $this->patternFor($job);

        $this->check('a fleet-pattern alert exists for the change', $pattern !== null && ($pattern['kind'] ?? '') === ConfigAlertModel::KIND_REVERT_PATTERN);
        // Read back through the column, not just from the insert: `kind` is an enum, and a value the schema
        // does not know is stored as an empty string with a warning rather than failing — a bug that is
        // invisible in the code that wrote it and indistinguishable from "no kind" everywhere it is read.
        $this->check(
            'and its kind survives the round trip through the column',
            ($pattern !== null ? ($this->alertRow((int) $pattern['id'])['kind'] ?? '') : '') === ConfigAlertModel::KIND_REVERT_PATTERN
        );
        $this->check('it counts all three devices', (int) ($pattern['device_count'] ?? 0) === 3);
        $this->check('it names the change and the scope in its summary', str_contains((string) ($pattern['summary'] ?? ''), 'REVERTED ON 3 DEVICES') && str_contains((string) ($pattern['summary'] ?? ''), '3 of its 5 device(s)'));
        $this->check('it names the group the change went to', str_contains((string) ($pattern['summary'] ?? ''), 'group "' . $fleet['group'] . '"'));
        $this->check('and it lists the devices it speaks for', str_contains((string) ($pattern['detail'] ?? ''), $fleet['devices'][0]['name']));

        $this->check('the third device joined it rather than being its own alert', (int) $third['grouped_into'] === (int) $pattern['id']);
        $this->check('and so did the two before it', $this->groupedCount((int) $pattern['id']) === 3);

        $sends = array_slice(PatternHarnessMailer::$sent, $before);

        // The whole point: three branches undoing one change produce one message. The two that were held are
        // grouped inside their grace window, so they are never sent at all — not sent late, not sent and
        // replaced, simply never sent.
        $this->check('exactly one email went out', count($sends) === 1);
        $this->check('and it is the whole conversation about this change', count(PatternHarnessMailer::$sent) === 1);
        $this->check('it is the pattern email, about the change', ($sends[0]['kind'] ?? '') === ConfigAlertModel::KIND_REVERT_PATTERN);
        $this->check('its subject leads with the count and the change', str_contains($sends[0]['subject'], 'REVERTED on 3 devices') && str_contains($sends[0]['subject'], 'job #' . $job));
        $this->check('its body says it is not three branch problems', str_contains($sends[0]['body'], 'That is not 3 branch problems'));
        $this->check('and it lists each branch with what is being done about it', str_contains($sends[0]['body'], $fleet['devices'][1]['name']));
        // The strongest form of the claim: across three devices and four alert rows, the *only* alert that
        // ever reached a mailbox is the one about the change.
        $this->check(
            'and the only alert ever emailed is the pattern one',
            array_column(PatternHarnessMailer::$sent, 'id') === [(int) $pattern['id']]
        );
        $this->check(
            'the held branches are visibly not owed an email either',
            $this->pendingIds() === []
        );

        $this->check('nobody is still owed an email for a grouped device', $this->pendingIds() === []);
    }

    // ------------------------------------------------------------------ C: one alert, honest count

    private function sectionC(): void
    {
        CLI::write('C. The banner reads one alert and four devices, not four and four', 'yellow');

        $fleet  = $this->fx['A'];
        $alerts = new ConfigAlertService();
        $groups = $alerts->openForBanner(50);
        $mine   = array_values(array_filter($groups, static fn (array $g): bool => (int) ($g['alert']['job_id'] ?? 0) === $fleet['job']));

        $this->check('one entry on the banner for this change', count($mine) === 1);
        $this->check('and it is the pattern row', ($mine[0]['alert']['kind'] ?? '') === ConfigAlertModel::KIND_REVERT_PATTERN);
        $this->check('carrying all three devices', count($mine[0]['members']) === 3);
        $this->check(
            'each device keeping its own repairs',
            array_reduce($mine[0]['members'], static fn (bool $carry, array $m): bool => $carry && (int) ($m['pf_id'] ?? 0) > 0 && (int) ($m['target_id'] ?? 0) > 0 && (int) ($m['job_id'] ?? 0) === $fleet['job'], true)
        );

        // The count an operator reads is devices. Grouping three reverts into one row must not make the
        // fleet look three times healthier than it is.
        $this->check('the heading counts devices rather than rows', $alerts->openDeviceCount(50) === 3);
        $this->check('while the raw alert rows are still all there', $this->openCountForJob($fleet['job']) === 4);
    }

    // ------------------------------------------------------------------ D: it grows without re-emailing

    private function sectionD(): void
    {
        CLI::write('D. A fourth branch joins the alert, and nobody is emailed twice', 'yellow');

        $fleet   = $this->fx['A'];
        $before  = count(PatternHarnessMailer::$sent);
        $pattern = $this->patternFor($fleet['job']);

        $fourth = $this->revert($fleet, 3, 'Setting 1 differs.');

        $updated = $this->alertRow((int) $pattern['id']);

        $this->check('the fourth device joined the pattern', (int) $fourth['grouped_into'] === (int) $pattern['id']);
        $this->check('the count on the row is current', (int) ($updated['device_count'] ?? 0) === 4);
        $this->check('and so is the wording', str_contains((string) ($updated['summary'] ?? ''), 'REVERTED ON 4 DEVICES') && str_contains((string) ($updated['summary'] ?? ''), '4 of its 5 device(s)'));
        $this->check('no second email was sent for the same change', count(PatternHarnessMailer::$sent) === $before);
        $this->check('and the pattern still carries its original notification record', ($updated['notified_at'] ?? null) !== null);
    }

    // ------------------------------------------------------------------ E: what does not join

    private function sectionE(): void
    {
        CLI::write('E. A later drift on the same change is not part of the story', 'yellow');

        $fleet   = $this->fx['A'];
        $pattern = $this->patternFor($fleet['job']);

        // The fifth device's change was applied two days ago, so its window closed long before now: whatever
        // it is missing, it is not the device undoing this push, and it must not join the pattern.
        $this->older($fleet, 4);
        $this->recordDrift($fleet, 4, 'Setting 1 differs.');

        $later     = $this->alertForTarget($this->targetIdFor($fleet['job'], (int) $fleet['devices'][4]['id']));
        $stillOpen = $this->alertRow((int) $pattern['id']);

        $this->check('the later difference is an ordinary drift', ($later['kind'] ?? '') === ConfigAlertModel::KIND_DRIFT);
        $this->check('it was not grouped into the pattern', ($later['grouped_into'] ?? null) === null);
        $this->check('and the pattern is unchanged by it', (int) ($stillOpen['device_count'] ?? 0) === 4);
        $this->check('it is visible on the banner as its own alert', $this->bannerTopLevel($fleet['job'], ConfigAlertModel::KIND_DRIFT) === 1);

        // And another change is another story.
        $other = $this->fleet('PAT-E2', 3);
        $this->revert($other, 0, 'Setting 2 differs.');
        $this->revert($other, 1, 'Setting 2 differs.');
        $this->revert($other, 2, 'Setting 2 differs.');

        $otherPattern = $this->patternFor($other['job']);

        $this->check('a second change forms its own pattern', $otherPattern !== null && (int) $otherPattern['id'] !== (int) $pattern['id']);
        $this->check('counting only its own devices', (int) ($otherPattern['device_count'] ?? 0) === 3);
        $this->check('and the first pattern did not absorb them', (int) ($this->alertRow((int) $pattern['id'])['device_count'] ?? 0) === 4);

        $this->fx['E'] = $other;
    }

    // ------------------------------------------------------------------ F: the fleet repair

    private function sectionF(): void
    {
        CLI::write('F. The pattern repairs the change on every device that lost it', 'yellow');

        $fleet = $this->fx['A'];

        // Aimed at the pattern's own members, which is the whole reason `reconcile()` takes a set: the
        // fifth device stopped matching this change two days later, and re-applying a change to a branch
        // that has moved on since is exactly the decision that belongs to a person.
        $members = array_map(
            static fn (array $member): int => (int) $member['pf_id'],
            $this->membersOf($this->patternFor($fleet['job']))
        );

        $this->check('the pattern speaks for four devices', count($members) === 4);

        $result = (new ConfigJobService())->reconcile($fleet['job'], 0, null, $members);

        $this->check('the repair is queued', ($result['ok'] ?? false) === true);
        $this->check('it covers exactly the devices in the pattern', (int) ($result['devices'] ?? 0) === 4);
        $this->check('and it did not need approval, being an ordinary group push', ($result['requires_approval'] ?? true) === false);

        $repairedPfIds = array_map(
            static fn (array $row): int => (int) $row['pf_id'],
            $this->db()->table('job_targets')->where('job_id', (int) $result['job_id'])->get()->getResultArray()
        );

        $this->check('leaving the branch that drifted later alone', !in_array((int) $fleet['devices'][4]['id'], $repairedPfIds, true));
        $this->check(
            'and naming nothing outside the pattern',
            array_diff($repairedPfIds, $members) === []
        );

        $this->jobIds[] = (int) $result['job_id'];

        $repair = (new ConfigJobModel())->find((int) $result['job_id']);

        $this->check('it is recorded as a repair of that change', (int) ($repair['reconcile_of_job_id'] ?? 0) === $fleet['job']);

        // Per-device repair is still available: each member row carries the ids the button needs.
        $shaped = array_map(static fn (array $m): array => ConfigAlertService::uiRow($m), $this->membersOf($this->patternFor($fleet['job'])));

        $this->check('every member row can still repair one device', array_reduce($shaped, static fn (bool $c, array $m): bool => $c && $m['job_id'] === $fleet['job'] && $m['pf_id'] > 0, true));
    }

    // ------------------------------------------------------------------ G: it closes when the devices are fixed

    private function sectionG(): void
    {
        CLI::write('G. The pattern closes itself when the last device is repaired', 'yellow');

        $fleet   = $this->fx['A'];
        $pattern = $this->patternFor($fleet['job']);

        // One device matches the job again.
        $this->recordMatched($fleet, 0);

        $this->check('its own alert is resolved', ($this->alertForTarget($this->targetIdFor($fleet['job'], (int) $fleet['devices'][0]['id']))['status'] ?? '') === ConfigAlertModel::STATUS_RESOLVED);

        $open = $this->alertRow((int) $pattern['id']);

        $this->check('the pattern is still open while devices are still wrong', ($open['status'] ?? '') === ConfigAlertModel::STATUS_OPEN);
        $this->check('and its count has dropped to three', (int) ($open['device_count'] ?? 0) === 3);
        $this->check('its wording kept up too', str_contains((string) ($open['summary'] ?? ''), 'REVERTED ON 3 DEVICES'));

        foreach ([1, 2, 3] as $index) {
            $this->recordMatched($fleet, $index);
        }

        $closed = $this->alertRow((int) $pattern['id']);

        $this->check('when the last device is confirmed the pattern closes', ($closed['status'] ?? '') === ConfigAlertModel::STATUS_RESOLVED);
        $this->check('recording that it was cleared rather than matched', ($closed['resolution'] ?? '') === ConfigAlertService::RESOLVED_CLEARED);
        $this->check('and nothing of this change is left on the banner', $this->bannerTopLevel($fleet['job'], ConfigAlertModel::KIND_REVERT_PATTERN) === 0);

        $this->check('the later drift on it is still open and ungrouped', ($this->alertForTarget($this->targetIdFor($fleet['job'], (int) $fleet['devices'][4]['id']))['status'] ?? '') === ConfigAlertModel::STATUS_OPEN);

        // And an aimed repair says so when the devices it names no longer need one, rather than quietly
        // repairing whatever else the change is missing from: the one device still drifted here is the
        // later drift, which is not part of the pattern and is a person's decision.
        $aimed = (new ConfigJobService())->reconcile($fleet['job'], 0, null, [(int) $fleet['devices'][0]['id']]);

        $this->check('an aimed repair refuses devices that no longer need one', ($aimed['ok'] ?? true) === false);
        $this->check('and says which devices it meant', str_contains((string) $aimed['message'], '1 device(s) named'));
        $this->check('queuing nothing', ($aimed['job_id'] ?? null) === null);
    }

    // ------------------------------------------------------------------ H: one decision, several devices

    private function sectionH(): void
    {
        CLI::write('H. Dismissing a pattern decides for the devices it speaks for', 'yellow');

        $other   = $this->fx['E'];
        $pattern = $this->patternFor($other['job']);

        $this->check('the second change still has an open pattern', $pattern !== null);

        $result = (new ConfigAlertService())->dismiss((int) $pattern['id'], 'harness');

        $this->check('the dismissal is accepted', ($result['ok'] ?? false) === true);
        $this->check('and says how many devices it covered', str_contains($result['message'], '3 device(s)'));

        $closed = $this->alertRow((int) $pattern['id']);

        $this->check('the pattern is resolved by a person', ($closed['status'] ?? '') === ConfigAlertModel::STATUS_RESOLVED && ($closed['resolved_by'] ?? '') === 'harness');
        $this->check('recording the dismissal, not an automatic clear', ($closed['resolution'] ?? '') === ConfigAlertService::RESOLVED_DISMISSED);
        $this->check('and every device it grouped was resolved with it', $this->openCountForJob($other['job']) === 0);
        $this->check('none of them is left on the banner', $this->bannerTopLevel($other['job'], null) === 0);
    }

    // ------------------------------------------------------------------ I: a lone revert still gets told

    private function sectionI(): void
    {
        CLI::write('I. A branch that reverts alone is still reported, one read cycle later', 'yellow');

        $solo = $this->fleet('PAT-I', 1);
        $held = $this->revert($solo, 0, 'Solo branch differs.');

        $this->check('a lone revert cannot be a pattern', $this->patternFor($solo['job']) === null);
        $this->check('so nothing was sent when it was found', !in_array((int) $held['id'], array_column(PatternHarnessMailer::$sent, 'id'), true));
        $this->check('its email is held rather than failed', ConfigAlertService::notifyState($held)['state'] === 'deferred');

        // A drift is never held: it cannot join a pattern, so waiting would buy nothing and cost latency on an
        // alert about a device that has been wrong for days.
        $later = $this->fleet('PAT-I2', 1);
        $this->older($later, 0);
        $this->recordDrift($later, 0, 'A difference found days later.');

        $drift = $this->alertForTarget($this->targetIdFor($later['job'], (int) $later['devices'][0]['id']));
        $driftEntry = $this->sentEntry((int) $drift['id']);

        $this->check('a later drift is not held at all', ($drift['notify_after'] ?? null) === null);
        $this->check('and it was emailed the moment it was found', $driftEntry !== null);
        $this->check('as a drift, not as a fleet revert', $driftEntry !== null && str_contains($driftEntry['subject'], 'Config drift'));

        // A revert that heals inside the grace is never emailed: the alert is resolved before its email is
        // due, and a message about a difference that no longer exists is worse than no message.
        $healed = $this->fleet('PAT-I3', 1);
        $blip   = $this->revert($healed, 0, 'A blip that came back by itself.');
        $this->recordMatched($healed, 0);

        // The clock moves on to the next sweep. Every held email is now due — including the members of the
        // closed pattern, which must still be excluded because the pattern already said everything.
        $this->due();

        // The sweep, with the recording mailer: a harness must never attempt a real send, and the retry path
        // is the same service the two runners use (`notifyPending()`).
        $sent  = (new ConfigAlertService(null, new PatternHarnessMailer(), null, null))->notifyPending(25);
        $entry = $this->sentEntry((int) $held['id']);

        $this->check('the sweep sends the held revert once it is due', $entry !== null);
        $this->check('as the per-device email, not the fleet one', $entry !== null && str_contains($entry['subject'], 'undid a config change') && !str_contains($entry['subject'], 'REVERTED on'));
        $this->check('it does not email the one that healed', !in_array((int) $blip['id'], array_column(PatternHarnessMailer::$sent, 'id'), true));
        $this->check('and it sent exactly one message for the whole sweep', count($sent) === 1);
        $this->check(
            'and a grouped member stayed silent even once its hold had expired',
            $this->sentEntry((int) $this->fx['A']['alerts'][0]) === null
        );
    }

    // ------------------------------------------------------------------ fixtures

    /**
     * A change pushed to a group of N fixture devices, each applied minutes ago under a window that is
     * still open — so a difference found now is a revert.
     *
     * @return array{job:int, group:string, devices:list<array>, applied:list<string>}
     */
    private function fleet(string $label, int $count): array
    {
        $group   = 'ZZZ-' . $label . '-' . bin2hex(random_bytes(3));
        $devices = [];

        for ($i = 1; $i <= $count; $i++) {
            $devices[] = $this->device($label . '-' . $i);
        }

        $job = (new ConfigJobService())->createJob([
            'payload'      => '<?xml version="1.0"?><pfsense><system><hostname>' . $label . '</hostname></system></pfsense>',
            'payload_type' => 'xml',
            'target_spec'  => ['type' => 'group', 'name' => $group],
            'watch_profile' => ConfigVerificationService::WATCH_PROFILE_STANDARD,
        ], $devices);

        $this->jobIds[] = $job;

        foreach ($devices as $device) {
            $this->applied($job, $device, '-5 minutes');
        }

        return ['job' => $job, 'group' => $group, 'devices' => $devices, 'applied' => []];
    }

    private function device(string $label): array
    {
        $model = new PfInventoryModel();
        $id    = (int) $model->insert([
            'name'     => 'ZZZ-' . $label . '-' . bin2hex(random_bytes(3)),
            'active'   => 0,
            'man_ip'   => '',
            'man_port' => '',
            'username' => 'harness',
            'password' => 'harness',
        ], true);

        $this->deviceIds[] = $id;

        return $model->find($id) ?? ['id' => $id, 'name' => $label];
    }

    /** Marks a target as applied just now, with the given window start. */
    private function applied(int $jobId, array $device, string $when): void
    {
        $appliedAt = date('Y-m-d H:i:s', strtotime($when));

        $this->db()->table('job_targets')
            ->where('job_id', $jobId)
            ->where('pf_id', (int) $device['id'])
            ->update([
                'status'           => JobTargetModel::STATUS_APPLIED,
                'attempts'         => 1,
                'applied_at'       => $appliedAt,
                'delivered_at'     => $appliedAt,
                'verify_status'    => JobTargetModel::VERIFY_PENDING,
                'watch_started_at' => $appliedAt,
                'watch_until'      => ConfigVerificationService::watchEndsAt($appliedAt, ConfigVerificationService::WATCH_SCHEDULE),
                'watch_next_at'    => null,
                'watch_checks'     => 1,
                'watch_via'        => ConfigVerificationService::WATCH_VIA_SSH,
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
    }

    /** Pushes one device's window back two days, so a difference now is a later drift rather than a revert. */
    private function older(array $fleet, int $index): void
    {
        $appliedAt = date('Y-m-d H:i:s', strtotime('-2 days'));

        $this->db()->table('job_targets')
            ->where('job_id', $fleet['job'])
            ->where('pf_id', (int) $fleet['devices'][$index]['id'])
            ->update([
                'applied_at'  => $appliedAt,
                'delivered_at' => $appliedAt,
                'watch_until' => ConfigVerificationService::watchEndsAt($appliedAt, ConfigVerificationService::WATCH_SCHEDULE),
            ]);
    }

    /**
     * Stands in for the next sweep arriving: every held email is now due.
     *
     * The harness cannot wait five minutes, and what the deferral actually depends on is the database fact
     * (is it due yet), so that is what this moves. It deliberately makes *every* held row due, including the
     * grouped members, so the exclusion of grouped rows is tested rather than assumed.
     */
    private function due(): void
    {
        $this->db()->table('config_alerts')
            ->where('notify_after IS NOT NULL', null, false)
            ->where('status', ConfigAlertModel::STATUS_OPEN)
            ->update(['notify_after' => date('Y-m-d H:i:s', strtotime('-1 minute'))]);
    }

    /** The recorded email for one alert, or null when it was never sent. */
    private function sentEntry(int $alertId): ?array
    {
        foreach (PatternHarnessMailer::$sent as $entry) {
            if ((int) $entry['id'] === $alertId) {
                return $entry;
            }
        }

        return null;
    }

    /** Reads one device back and finds it no longer matches the change: the revert the feature groups. */
    private function revert(array $fleet, int $index, string $detail): array
    {
        $this->recordDrift($fleet, $index, $detail);

        return $this->alertForTarget($this->targetIdFor($fleet['job'], (int) $fleet['devices'][$index]['id']));
    }

    private function recordDrift(array $fleet, int $index, string $detail): void
    {
        $target = $this->targetRow($this->targetIdFor($fleet['job'], (int) $fleet['devices'][$index]['id']));

        $this->verifier()->record($target, $this->jobRow($fleet['job']), $fleet['devices'][$index], JobTargetModel::VERIFY_DRIFTED, $detail);
    }

    private function recordMatched(array $fleet, int $index): void
    {
        $target = $this->targetRow($this->targetIdFor($fleet['job'], (int) $fleet['devices'][$index]['id']));

        $this->verifier()->record($target, $this->jobRow($fleet['job']), $fleet['devices'][$index], JobTargetModel::VERIFY_MATCHED, 'The device matches the job again.');
    }

    private function verifier(): ConfigVerificationService
    {
        return new ConfigVerificationService(null, null, null, null, null, new ConfigAlertService(null, new PatternHarnessMailer(), null, null), null);
    }

    // ------------------------------------------------------------------ helpers

    private function jobRow(int $jobId): array
    {
        return (new ConfigJobModel())->find($jobId) ?? [];
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

    private function alertRow(int $alertId): array
    {
        return $this->db()->table('config_alerts')->where('id', $alertId)->get()->getRowArray() ?? [];
    }

    private function alertForTarget(int $targetId): array
    {
        return $this->db()->table('config_alerts')->where('target_id', $targetId)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
    }

    private function patternFor(int $jobId): ?array
    {
        $row = $this->db()->table('config_alerts')
            ->where('job_id', $jobId)
            ->where('kind', ConfigAlertModel::KIND_REVERT_PATTERN)
            ->where('status', ConfigAlertModel::STATUS_OPEN)
            ->get()->getRowArray();

        return $row ?: null;
    }

    private function groupedCount(int $patternId): int
    {
        return $this->db()->table('config_alerts')->where('grouped_into', $patternId)->where('status', ConfigAlertModel::STATUS_OPEN)->countAllResults();
    }

    private function openCountForJob(int $jobId): int
    {
        return $this->db()->table('config_alerts')->where('job_id', $jobId)->where('status', ConfigAlertModel::STATUS_OPEN)->countAllResults();
    }

    /** How many top-level banner entries this job has, optionally of one kind. */
    private function bannerTopLevel(int $jobId, ?string $kind): int
    {
        $count = 0;

        foreach ((new ConfigAlertService())->openForBanner(50) as $group) {
            if ((int) ($group['alert']['job_id'] ?? 0) !== $jobId) {
                continue;
            }

            if ($kind !== null && ($group['alert']['kind'] ?? '') !== $kind) {
                continue;
            }

            $count++;
        }

        return $count;
    }

    /** @return list<array> */
    private function membersOf(?array $pattern): array
    {
        if ($pattern === null) {
            return [];
        }

        return $this->db()->table('config_alerts')->where('grouped_into', (int) $pattern['id'])->get()->getResultArray();
    }

    /** @return list<int> */
    private function pendingIds(): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['id'],
            (new ConfigAlertService())->pendingNotifications(25)
        );
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
            'pf_inventory'     => $db->table('pf_inventory')->countAllResults(),
        ];
    }

    private function cleanup(): void
    {
        $db      = $this->db();
        $allJobs = $db->table('config_jobs')->select('id, batch_id, reconcile_of_job_id')->get()->getResultArray();
        $mine    = [];
        $batches = [];

        foreach ($allJobs as $row) {
            $id = (int) $row['id'];

            if (in_array($id, $this->jobIds, true) || in_array((int) ($row['reconcile_of_job_id'] ?? 0), $this->jobIds, true)) {
                $mine[]    = $id;
                $batches[] = (string) $row['batch_id'];
            }
        }

        if ($mine !== []) {
            $db->table('config_alerts')->whereIn('job_id', $mine)->delete();
            $db->table('config_push_logs')->whereIn('batch_id', $batches)->delete();
            $db->table('job_targets')->whereIn('job_id', $mine)->delete();
            $db->table('config_jobs')->whereIn('id', $mine)->delete();
        }

        if ($this->deviceIds !== []) {
            $db->table('config_alerts')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('config_push_logs')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('pf_inventory')->whereIn('id', $this->deviceIds)->delete();
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

/**
 * A mailer that records what it would have sent instead of sending it.
 *
 * It builds the real subject and body, so the harness proves the actual email a person would receive —
 * including that a pattern's subject names the change and the count, and that a single device's did not
 * change. Statics rather than an injected recorder because the service constructs the mailer internally.
 */
class PatternHarnessMailer extends ConfigAlertMailer
{
    /** @var list<array{id:int, kind:string, subject:string, body:string}> */
    public static array $sent = [];

    protected function setting(string $name, string $default = ''): string
    {
        return ['app_name' => 'pfCentral', 'app_url' => 'https://pfcentral.example.test/'][$name] ?? $default;
    }

    public function send(array $alert, array $context = []): array
    {
        self::$sent[] = [
            'id'      => (int) ($alert['id'] ?? 0),
            'kind'    => (string) ($alert['kind'] ?? ''),
            'subject' => $this->subject($alert, $context['device'] ?? [], $context['rollback'] ?? null, $context),
            'body'    => $this->body($alert, $context),
        ];

        return ['ok' => true, 'message' => 'recorded by the harness', 'recipients' => ['harness@example.test']];
    }
}
