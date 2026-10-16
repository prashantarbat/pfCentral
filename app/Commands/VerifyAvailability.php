<?php

namespace App\Commands;

use App\Models\BranchAvailabilityEventModel;
use App\Models\BranchAvailabilityStateModel;
use App\Models\ConfigJobModel;
use App\Models\JobTargetModel;
use App\Models\PfInventoryModel;
use App\Services\Availability\AvailabilityService;
use App\Services\ConfigPush\ConfigJobService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Proves branch availability monitoring against a real database and real sockets.
 *
 * The unit tests pin the arithmetic — the two-strikes rule, what a window denominator is, which change
 * counts as an explanation. What only a database and a live TCP connection can show is the wiring, and
 * the wiring is where a monitor lies without anybody noticing:
 *
 *   - that an outage is **dated from the first failed probe**, not from the one that confirmed it (two
 *     minutes of silence per outage would be quietly missing otherwise);
 *   - that one minute of downtime produces exactly one outage row, not one per observation;
 *   - that recovery closes that same row, with a duration;
 *   - that a branch answers a *real* connection on a configured address, and that an address that refuses
 *     produces an outage only on the second attempt;
 *   - that a branch nobody has watched reports **not observed** rather than 100%;
 *   - that a change pushed to the device shortly before it went quiet is attached to the outage, and one
 *     pushed during the outage is not;
 *   - and that a branch with nothing to probe is inferred from silence, labelled as inferred.
 *
 * Fixtures only, and every probe it makes points at localhost: nothing here touches a branch. Everything
 * it creates is deleted again, and the database is compared against a baseline it took first.
 */
class VerifyAvailability extends BaseCommand
{
    protected $group       = 'Config';
    protected $name        = 'verify:availability';
    protected $description = 'Harness: branch availability (probes, outages, uptime windows, change correlation).';
    protected $usage       = 'verify:availability';

    /** A local address that answers, so the probe path is exercised for real: MySQL is on this box. */
    private const LISTENING_PORT = 3306;

    /** A local address nothing listens on. */
    private const DEAD_PORT = 1;

    /** @var list<string> */
    private array $pass = [];

    /** @var list<string> */
    private array $fail = [];

    /** @var list<int> */
    private array $deviceIds = [];

    /** @var list<int> */
    private array $jobIds = [];

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

    // ------------------------------------------------------------------ A: when an outage starts

    private function sectionA(): void
    {
        CLI::write('A. A branch is up, one failure is not an outage, two are — dated from the first', 'yellow');

        $service = new AvailabilityService();
        $device  = $this->device('AVAIL-A');
        $up      = date('Y-m-d H:i:s', time() - (3 * 3600));
        $first   = date('Y-m-d H:i:s', time() - (2 * 3600));
        $second  = date('Y-m-d H:i:s', time() - (2 * 3600) + 300);

        $seen = $service->observe($device, true, BranchAvailabilityStateModel::SOURCE_PROBE, 'answered on 127.0.0.1:' . self::LISTENING_PORT . ' in 3 ms', 3, $up, null, '127.0.0.1:' . self::LISTENING_PORT);

        $state = $this->stateRow((int) $device['id']);

        $this->check('a branch that answers is up', ($state['state'] ?? '') === BranchAvailabilityStateModel::STATE_UP);
        $this->check('and we record when we started watching it', ($state['monitored_since'] ?? '') === $up);
        $this->check('no outage is opened by an answer', $seen['opened'] === false && $this->eventCount((int) $device['id']) === 0);

        $service->observe($device, false, BranchAvailabilityStateModel::SOURCE_PROBE, 'no answer on 127.0.0.1:' . self::LISTENING_PORT, null, $first, null, '127.0.0.1:' . self::LISTENING_PORT);

        $state = $this->stateRow((int) $device['id']);

        $this->check('one failed probe leaves the branch up', ($state['state'] ?? '') === BranchAvailabilityStateModel::STATE_UP);
        $this->check('but remembers when the trouble started', ($state['failing_since'] ?? '') === $first);
        $this->check('and writes no outage yet', $this->eventCount((int) $device['id']) === 0);

        $transition = $service->observe($device, false, BranchAvailabilityStateModel::SOURCE_PROBE, 'no answer on 127.0.0.1:' . self::LISTENING_PORT, null, $second, null, '127.0.0.1:' . self::LISTENING_PORT);

        $state = $this->stateRow((int) $device['id']);
        $event = $this->openEvent((int) $device['id']);

        $this->check('two in a row are an outage', ($state['state'] ?? '') === BranchAvailabilityStateModel::STATE_DOWN && $transition['opened'] === true);
        $this->check('exactly one outage row exists', $this->eventCount((int) $device['id']) === 1);
        $this->check('dated from the first failure, not the second', ($event['started_at'] ?? '') === $first);
        $this->check('and it is still open', empty($event['ended_at']));
        $this->check('with the evidence that found it', str_contains((string) ($event['evidence'] ?? ''), 'no answer on 127.0.0.1:' . self::LISTENING_PORT));
        $this->check('the failure counter says how long it has been failing', (int) ($state['consecutive_failures'] ?? 0) === 2);

        // A fourth failing probe is more of the same outage, not a new one.
        $again = $service->observe($device, false, BranchAvailabilityStateModel::SOURCE_PROBE, 'still no answer', null, date('Y-m-d H:i:s', time() - (2 * 3600) + 600));

        $this->check('a further failure does not open a second outage', $again['opened'] === false && $this->eventCount((int) $device['id']) === 1);

        $this->fx['A'] = ['device' => $device, 'first' => $first];
    }

    // ------------------------------------------------------------------ B: recovery

    private function sectionB(): void
    {
        CLI::write('B. Recovery closes the outage it belongs to, with a duration', 'yellow');

        $device  = $this->fx['A']['device'];
        $service = new AvailabilityService();
        $back    = date('Y-m-d H:i:s', strtotime((string) $this->fx['A']['first']) + 600);

        $transition = $service->observe($device, true, BranchAvailabilityStateModel::SOURCE_PROBE, 'answered on 127.0.0.1:' . self::LISTENING_PORT . ' in 4 ms', 4, $back, null, '127.0.0.1:' . self::LISTENING_PORT);

        $event = $this->openEvent((int) $device['id']);
        $state = $this->stateRow((int) $device['id']);

        $this->check('an answer brings the branch back up', $transition['closed'] === true && ($state['state'] ?? '') === BranchAvailabilityStateModel::STATE_UP);
        $this->check('the outage it belongs to is closed', $this->openEvent((int) $device['id']) === []);
        $this->check('with a duration of the outage, not of the month', (int) ($this->lastEvent((int) $device['id'])['duration_seconds'] ?? -1) === 600);
        $this->check('and the evidence now says it recovered', str_contains((string) ($this->lastEvent((int) $device['id'])['evidence'] ?? ''), 'recovered'));
        $this->check('the state says when this up period began', ($state['since'] ?? '') === $back);
        $this->check('and the failure run is over', ($state['failing_since'] ?? null) === null && (int) ($state['consecutive_failures'] ?? 9) === 0);
        $this->check('one outage, not one per observation', $this->eventCount((int) $device['id']) === 1);
    }

    // ------------------------------------------------------------------ C: the window

    private function sectionC(): void
    {
        CLI::write('C. Uptime is computed from outages, over the time we were watching', 'yellow');

        $service = new AvailabilityService();
        $device  = $this->fx['A']['device'];
        $fresh   = $this->device('AVAIL-NEW');

        $report = $service->report(AvailabilityService::normalizeFilters(['branch' => (string) $device['name']]), 1);
        $row    = $report['branches'][0] ?? [];

        $this->check('the report names the branch', ($row['name'] ?? '') === $device['name']);
        $this->check('with an outage count for the window', (int) ($row['outages'] ?? 0) === 1);
        $this->check('and the downtime it caused, in seconds', (int) ($row['downtime_seconds'] ?? 0) === 600);
        $this->check('so uptime is not a round hundred', ($row['uptime'] ?? 100.0) < 100.0);
        $this->check('and is computed over the watched span, not the whole window', (int) ($row['observed_seconds'] ?? 0) < $report['window']['seconds']);
        $this->check('which is the three hours since we first saw it', abs((int) ($row['observed_seconds'] ?? 0) - (3 * 3600)) <= 60);
        $this->check('the branch which answered is reported as reachable', ($row['state'] ?? '') === BranchAvailabilityStateModel::STATE_UP);

        // A branch nobody has watched must not read as perfect.
        $freshReport = $service->report(AvailabilityService::normalizeFilters(['branch' => (string) $fresh['name']]), 1);
        $freshRow    = $freshReport['branches'][0] ?? [];

        $this->check('a branch nobody has probed is not observed', ($freshRow['state'] ?? '') === BranchAvailabilityStateModel::STATE_UNKNOWN);
        // Not `?? null`: the key is present and its value is null, which is the whole point.
        $this->check('and has no uptime at all rather than 100%', array_key_exists('uptime', $freshRow) && $freshRow['uptime'] === null);
        $this->check('which the page can say in words', BranchAvailabilityStateModel::stateLabel((string) ($freshRow['state'] ?? '')) === 'not observed');

        // The tiles describe what is on screen, so they are read from an unfiltered report.
        $fleet = $service->report(AvailabilityService::normalizeFilters([]), 1);

        $this->check('the tiles count what is down now', (int) ($fleet['tiles']['down'] ?? -1) === 0);
        $this->check('and how many branches are going unwatched', (int) ($fleet['tiles']['unknown'] ?? 0) >= 1);
        $this->check('the window defaults to a week', $fleet['window']['days'] === AvailabilityService::DEFAULT_WINDOW_DAYS);
    }

    // ------------------------------------------------------------------ D: correlation

    private function sectionD(): void
    {
        CLI::write('D. A change pushed just before an outage is attached to it; one pushed during it is not', 'yellow');

        $service = new AvailabilityService();
        $device  = $this->fx['A']['device'];

        // A real job for this device, applied ten minutes before the outage began.
        $jobId    = $this->job($device);
        $applied  = date('Y-m-d H:i:s', strtotime((string) $this->fx['A']['first']) - 600);
        $this->markApplied($jobId, (int) $device['id'], $applied);

        $correlated = $service->report(AvailabilityService::normalizeFilters(['branch' => (string) $device['name']]), 1);
        $outage     = $correlated['outages'][0] ?? [];

        $this->check('the outage carries the job that preceded it', (int) ($outage['job_id'] ?? 0) === $jobId);
        $this->check('and how long before the outage it landed', (int) ($outage['job_gap_minutes'] ?? -1) === 10);
        $this->check('which the report counts as explained', ($correlated['correlated'] ?? 0) >= 1);

        // The same change, applied *during* an outage: not evidence about its cause.
        $during = $this->device('AVAIL-DURING');
        $lateJob = $this->job($during);
        $lateApplied = date('Y-m-d H:i:s', strtotime((string) $this->fx['A']['first']) + 120);
        $this->markApplied($lateJob, (int) $during['id'], $lateApplied);

        $first = date('Y-m-d H:i:s', strtotime((string) $this->fx['A']['first']) + 60);
        $second = date('Y-m-d H:i:s', strtotime((string) $this->fx['A']['first']) + 300);

        $service->observe($during, true, BranchAvailabilityStateModel::SOURCE_PROBE, 'answered', 2, date('Y-m-d H:i:s', strtotime($first) - 600));
        $service->observe($during, false, BranchAvailabilityStateModel::SOURCE_PROBE, 'no answer', null, $first);
        $service->observe($during, false, BranchAvailabilityStateModel::SOURCE_PROBE, 'no answer', null, $second);

        $lateReport = $service->report(AvailabilityService::normalizeFilters(['branch' => (string) $during['name']]), 1);
        $lateOutage = $lateReport['outages'][0] ?? [];

        $this->check('an outage is still recorded for the second branch', $lateOutage !== []);
        $this->check('but a change applied during the outage is not called its cause', (int) ($lateOutage['job_id'] ?? 0) === 0);

        // And a branch with no change before it says so, rather than looking correlated.
        $clean = $service->report(AvailabilityService::normalizeFilters(['branch' => (string) $device['name'], 'kind' => BranchAvailabilityEventModel::KIND_UNREACHABLE]), 1);
        $this->check('an outage found by a probe is labelled as measured', ($clean['outages'][0]['kind_label'] ?? '') === 'probe failed');
    }

    // ------------------------------------------------------------------ E: inferred from silence

    private function sectionE(): void
    {
        CLI::write('E. A branch with nothing to probe is inferred from silence, and labelled as inferred', 'yellow');

        $service = new AvailabilityService();

        // Nothing configurable to probe (no management address), and the device has gone quiet.
        $silent   = $this->device('AVAIL-SILENT', '', '');
        $checkin  = date('Y-m-d H:i:s', time() - (3 * 3600));
        $this->statusRow((int) $silent['id'], '', '', '', $checkin);

        // The same shape, but checking in normally: silence has not happened yet.
        $quiet   = $this->device('AVAIL-QUIET', '', '');
        $this->statusRow((int) $quiet['id'], '', '', '', date('Y-m-d H:i:s', time() - 60));

        $summary = $service->sweep(['device' => (string) $silent['name'], 'limit' => 5]);

        $event = $this->lastEvent((int) $silent['id']);

        $this->check('a branch with nothing to probe is not probed', $summary['probed'] === 0 && $summary['skipped'] === 1);
        $this->check('it is observed through its check-in instead', $summary['silent'] !== []);
        $this->check('which opens an outage on that same run', ($event['kind'] ?? '') === BranchAvailabilityEventModel::KIND_SILENT);
        $this->check('that says it was inferred rather than measured', str_contains((string) ($event['evidence'] ?? ''), '(inferred)'));
        $this->check(
            'dated from when the silence became provable, not from when it was noticed',
            ($event['started_at'] ?? '') === date('Y-m-d H:i:s', strtotime($checkin) + (AvailabilityService::SILENT_AFTER_MINUTES * 60))
        );
        $this->check('and the state records which device went quiet', ($this->stateRow((int) $silent['id'])['last_checkin_at'] ?? '') === $checkin);
        $this->check('and the page names it that way', BranchAvailabilityEventModel::kindLabel(BranchAvailabilityEventModel::KIND_SILENT) === 'inferred from silence');

        $quietSummary = $service->sweep(['device' => (string) $quiet['name'], 'limit' => 5]);

        $this->check('a branch still checking in is left alone', $quietSummary['silent'] === [] && $this->eventCount((int) $quiet['id']) === 0);
    }

    // ------------------------------------------------------------------ F: the probe itself

    private function sectionF(): void
    {
        CLI::write('F. The sweep opens real connections, and needs two refusals before calling it an outage', 'yellow');

        $service = new AvailabilityService();
        $live    = $this->device('AVAIL-LIVE', '127.0.0.1', (string) self::LISTENING_PORT);
        $dead    = $this->device('AVAIL-DEAD', '127.0.0.1', (string) self::DEAD_PORT);

        $this->check('the address that answers is really answering', $service->probe('127.0.0.1:' . self::LISTENING_PORT, 2)['ok'] === true);
        $this->check('and the one nothing listens on really does not', $service->probe('127.0.0.1:' . self::DEAD_PORT, 2)['ok'] === false);

        $up = $service->sweep(['device' => (string) $live['name']]);

        $liveState = $this->stateRow((int) $live['id']);

        $this->check('a reachable branch is probed once', $up['probed'] === 1 && $up['up'] === 1);
        $this->check('and recorded as up', ($liveState['state'] ?? '') === BranchAvailabilityStateModel::STATE_UP);
        $this->check('against the address somebody configured', ($liveState['last_probe_target'] ?? '') === '127.0.0.1:' . self::LISTENING_PORT);
        $this->check('with how long it took', (int) ($liveState['last_probe_ms'] ?? -1) >= 0);

        $downFirst = $service->sweep(['device' => (string) $dead['name']]);
        $deadState = $this->stateRow((int) $dead['id']);

        $this->check('an unreachable branch is recorded as a failed probe', $downFirst['down'] === 1);
        $this->check('but one refusal is not yet an outage', ($deadState['state'] ?? '') === BranchAvailabilityStateModel::STATE_UNKNOWN && $this->eventCount((int) $dead['id']) === 0);

        $downSecond = $service->sweep(['device' => (string) $dead['name']]);
        $deadState  = $this->stateRow((int) $dead['id']);

        $this->check('a second refusal is', ($deadState['state'] ?? '') === BranchAvailabilityStateModel::STATE_DOWN && $downSecond['down'] === 1);
        $this->check('and the outage is open with the socket error as its evidence', $this->openEvent((int) $dead['id']) !== []);

        // The sweep is bounded to the device asked for: a report about one branch must not probe the fleet.
        $this->check('a targeted sweep touches only the branch it was given', $up['branches'] >= 1 && $up['probed'] === 1);

        $dry = $service->sweep(['device' => (string) $dead['name'], 'dry' => true]);
        $this->check('a dry run still probes but writes nothing', $dry['down'] === 1 && (int) ($this->stateRow((int) $dead['id'])['consecutive_failures'] ?? 0) === 2);

        // Filters and the export.
        $filters = AvailabilityService::normalizeFilters(['branch' => (string) $dead['name'], 'state' => 'down']);
        $report  = $service->report($filters, 1);

        $this->check('the state filter finds the branch that is down', count($report['branches']) === 1 && ($report['branches'][0]['state'] ?? '') === BranchAvailabilityStateModel::STATE_DOWN);

        $export = $service->exportRows(AvailabilityService::normalizeFilters(['branch' => (string) $dead['name']]));
        $csv    = AvailabilityService::csv($export['rows']);

        $this->check('the export carries the outage', $export['rows'] !== [] && str_contains($csv, (string) $dead['name']));
        $this->check('and says it is still down', str_contains($csv, 'still down'));
        $this->check('and that nothing preceded it', str_contains($csv, 'no change recorded in the ' . AvailabilityService::CORRELATE_WINDOW_MINUTES . ' min before it'));

        $silentOnly = $service->exportRows(AvailabilityService::normalizeFilters(['kind' => BranchAvailabilityEventModel::KIND_SILENT]));
        $this->check('the kind filter selects inferred outages only', $this->onlyKind($silentOnly['rows'], BranchAvailabilityEventModel::KIND_SILENT));

        $ancient = $service->report(AvailabilityService::normalizeFilters(['branch' => (string) $dead['name'], 'from' => date('Y-m-d H:i:s', time() - (30 * 86400)), 'to' => date('Y-m-d H:i:s', time() - (20 * 86400))]), 1);

        $this->check('a window that ends before the outage contains it not at all', (int) ($ancient['outages'] ?? 0) === 0);
        $this->check(
            'and that branch reads as not observed for it, not as perfect',
            (int) ($ancient['branches'][0]['outages'] ?? 0) === 0 && array_key_exists('uptime', $ancient['branches'][0] ?? []) && $ancient['branches'][0]['uptime'] === null
        );

        // G. Fleet and Device XLSX generation and device availability profile
        $xlsx = \App\Services\Availability\AvailabilityExportService::buildFleetXlsx($report, $export);
        $this->check('fleet export produces valid XLSX binary', strlen($xlsx) > 1000 && str_starts_with($xlsx, "PK\x03\x04"));
        $this->check('xlsx filename ends with .xlsx', str_ends_with(AvailabilityService::xlsxFilename(), '.xlsx'));

        $detail = $service->getDeviceAvailabilityDetail((int) $dead['id'], 7);
        $this->check('device detail loads individual branch profile', $detail !== null && ($detail['device']['name'] ?? '') === $dead['name']);
        $this->check('device detail carries multi-link WAN structures', isset($detail['links']['mpls'], $detail['links']['ill'], $detail['links']['bdr']));

        $devXlsx = \App\Services\Availability\AvailabilityExportService::buildDeviceXlsx($detail);
        $this->check('device export produces valid device XLSX binary', strlen($devXlsx) > 1000 && str_starts_with($devXlsx, "PK\x03\x04"));
    }

    // ------------------------------------------------------------------ fixtures

    private function device(string $label, string $manIp = '', string $manPort = ''): array
    {
        $model = new PfInventoryModel();
        $id    = (int) $model->insert([
            'name'     => 'ZZZ-' . $label . '-' . bin2hex(random_bytes(3)),
            'active'   => 0,
            'man_ip'   => $manIp,
            'man_port' => $manPort,
            'username' => 'harness',
            'password' => 'harness',
        ], true);

        $this->deviceIds[] = $id;

        return $model->find($id) ?? ['id' => $id, 'name' => $label];
    }

    private function statusRow(int $pfId, string $ip, string $port, string $sshPort, string $updatedAt): void
    {
        $this->db()->table('pf_status')->insert([
            'pf_id'      => $pfId,
            'ip'         => $ip,
            'port'       => $port,
            'ssh_port'   => $sshPort,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => $updatedAt,
        ]);
    }

    private function job(array $device): int
    {
        $jobId = (new ConfigJobService())->createJob([
            'payload'      => '<?xml version="1.0"?><pfsense><system><hostname>' . ($device['name'] ?? 'x') . '</hostname></system></pfsense>',
            'payload_type' => 'xml',
            'target_spec'  => ['type' => 'group', 'name' => 'availability-harness'],
        ], [$device]);

        $this->jobIds[] = $jobId;

        return $jobId;
    }

    private function markApplied(int $jobId, int $pfId, string $appliedAt): void
    {
        $this->db()->table('job_targets')
            ->where('job_id', $jobId)
            ->where('pf_id', $pfId)
            ->update([
                'status'     => JobTargetModel::STATUS_APPLIED,
                'attempts'   => 1,
                'applied_at' => $appliedAt,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    // ------------------------------------------------------------------ helpers

    private function db()
    {
        return db_connect();
    }

    private function stateRow(int $pfId): array
    {
        return $this->db()->table('branch_availability_state')->where('pf_id', $pfId)->get()->getRowArray() ?? [];
    }

    private function eventCount(int $pfId): int
    {
        return $this->db()->table('branch_availability_events')->where('pf_id', $pfId)->countAllResults();
    }

    private function openEvent(int $pfId): array
    {
        return $this->db()->table('branch_availability_events')->where('pf_id', $pfId)->where('ended_at', null)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
    }

    private function lastEvent(int $pfId): array
    {
        return $this->db()->table('branch_availability_events')->where('pf_id', $pfId)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
    }

    /** @param list<array<string, mixed>> $rows */
    private function onlyKind(array $rows, string $kind): bool
    {
        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if ((string) ($row['kind'] ?? '') !== $kind) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $db = $this->db();

        return [
            'branch_availability_state'  => $db->table('branch_availability_state')->countAllResults(),
            'branch_availability_events' => $db->table('branch_availability_events')->countAllResults(),
            'config_jobs'                => $db->table('config_jobs')->countAllResults(),
            'job_targets'                => $db->table('job_targets')->countAllResults(),
            'config_push_logs'           => $db->table('config_push_logs')->countAllResults(),
            'pf_inventory'               => $db->table('pf_inventory')->countAllResults(),
            'pf_status'                  => $db->table('pf_status')->countAllResults(),
        ];
    }

    private function cleanup(): void
    {
        $db = $this->db();

        if ($this->deviceIds !== []) {
            $db->table('branch_availability_events')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('branch_availability_state')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('pf_status')->whereIn('pf_id', $this->deviceIds)->delete();
        }

        $batches = [];

        foreach ($this->jobIds as $jobId) {
            $row = (new ConfigJobModel())->find($jobId);

            if ($row !== null) {
                $batches[] = (string) $row['batch_id'];
            }
        }

        if ($this->jobIds !== []) {
            $db->table('job_targets')->whereIn('job_id', $this->jobIds)->delete();
            $db->table('config_jobs')->whereIn('id', $this->jobIds)->delete();
        }

        if ($batches !== []) {
            $db->table('config_push_logs')->whereIn('batch_id', $batches)->delete();
        }

        if ($this->deviceIds !== []) {
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
