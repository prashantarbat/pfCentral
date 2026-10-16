<?php

namespace App\Services\Availability;

use App\Models\BranchAvailabilityEventModel;
use App\Models\BranchAvailabilityStateModel;
use App\Models\PfInventoryModel;

/**
 * Per-branch availability: is each site's link up, and every time it was not.
 *
 * The measurements come from pfCentral looking *at* the branches, not from the branches reporting in. That
 * ordering matters more than it sounds: a branch that is offline cannot tell anybody it is offline, so a
 * monitor built on agent check-ins is blind exactly when it is needed. So each run opens a TCP connection
 * to the device's own management ports (the same address a config push uses) and treats an answer on any
 * of them as proof of life.
 *
 * Three rules keep the numbers trustworthy:
 *
 *  1. **A single failure is not an outage.** Two consecutive failed probes are needed before a branch is
 *     down, and the outage is dated from the *first* of them (`failing_since`), so the report is neither
 *     trigger-happy nor late.
 *  2. **A branch is only reported on where it was actually watched.** An uptime percentage is computed
 *     over the time we were observing, from `monitored_since` — never over a window that began before we
 *     were looking, which would read as a perfect month we cannot vouch for.
 *  3. **Measured and inferred are labelled differently.** `unreachable` is a probe that failed; `silent`
 *     is a branch with no probe-able address that stopped checking in. The second is the only evidence
 *     available for such a device, and the page says so rather than presenting it as a measurement.
 *
 * An outage also carries the change that preceded it, because that correlation is the question an operator
 * cannot answer by hand: a job applied to the device in the thirty minutes before the branch went quiet.
 * A job applied *during* an outage is not evidence about its cause, so it is not attached to it.
 */
class AvailabilityService
{
    /** Consecutive failed probes before a branch is called down. One timeout is not an outage. */
    public const FAILS_TO_DOWN = 2;

    /** Seconds to wait for a management port to answer. */
    public const PROBE_TIMEOUT = 3;

    /** How many addresses we are willing to try before calling a branch unreachable. */
    public const MAX_PROBE_TARGETS = 4;

    /** A device that has not checked in for this long is called silent (when we cannot probe it). */
    public const SILENT_AFTER_MINUTES = 20;

    /**
     * How many silent observations confirm an inferred outage.
     *
     * One, because the de-bouncing has already happened: the two-sample rule exists so that a probe which
     * happened to time out is not reported as an outage, and a device that has been quiet for twenty
     * minutes is not a timing artefact — the threshold *is* the evidence.
     */
    public const SILENCE_CONFIRMS = 1;

    /** A change applied within this many minutes before an outage is attached to it as its likely cause. */
    public const CORRELATE_WINDOW_MINUTES = 30;

    /** The window the page opens with, and the longest one it will compute. */
    public const DEFAULT_WINDOW_DAYS = 7;
    public const MAX_WINDOW_DAYS     = 180;

    /** Outages per page, and the ceiling on an export. */
    public const PER_PAGE   = 50;
    public const MAX_EXPORT = 20000;

    /** A branch's uptime below this is worth flagging on the report. */
    public const POOR_UPTIME = 99.0;

    /** The pfSense webGUI port, used when a device's own port field is empty. */
    public const DEFAULT_PORT = 443;

    /** Outage events older than this many days are pruned to keep the database bounded. */
    public const RETENTION_DAYS = 90;

    /** Human label for multi-link health. */
    public static function linkHealthLabel(string $health): string
    {
        return match ($health) {
            'healthy'  => 'All Links OK',
            'degraded' => 'Failover (Backup)',
            'down'     => 'All Links Down',
            default    => 'Not Observed',
        };
    }

    protected \CodeIgniter\Database\BaseConnection $db;
    protected BranchAvailabilityStateModel $state;
    protected BranchAvailabilityEventModel $events;
    protected PfInventoryModel $inventory;

    public function __construct(
        ?\CodeIgniter\Database\BaseConnection $db = null,
        ?BranchAvailabilityStateModel $state = null,
        ?BranchAvailabilityEventModel $events = null,
        ?PfInventoryModel $inventory = null
    ) {
        $this->db        = $db ?? db_connect();
        $this->state     = $state ?? new BranchAvailabilityStateModel();
        $this->events    = $events ?? new BranchAvailabilityEventModel();
        $this->inventory = $inventory ?? new PfInventoryModel();
    }

    // ======================================================================= pure rules

    /**
     * The state machine, as a function: given where a branch is and what we just saw, where is it now?
     *
     * Pure on purpose — this is the rule that decides whether an outage is opened, so it is the one thing
     * worth pinning in a unit test rather than discovering in production at 03:00.
     *
     * @return array{state:string, opens:bool, closes:bool}
     */
    public static function nextState(string $current, int $failures, bool $ok, int $failuresToDown = self::FAILS_TO_DOWN): array
    {
        if ($ok) {
            // Recovery is never delayed: a single successful probe is proof of life, and holding a branch
            // down because it failed twice before would report an outage that has already ended.
            return [
                'state'  => BranchAvailabilityStateModel::STATE_UP,
                'opens'  => false,
                'closes' => $current === BranchAvailabilityStateModel::STATE_DOWN,
            ];
        }

        $failures++;
        $down = $failures >= max(1, $failuresToDown);

        if ($current === BranchAvailabilityStateModel::STATE_UP) {
            return [
                'state'  => $down ? BranchAvailabilityStateModel::STATE_DOWN : BranchAvailabilityStateModel::STATE_UP,
                'opens'  => $down,
                'closes' => false,
            ];
        }

        if ($current === BranchAvailabilityStateModel::STATE_DOWN) {
            // Already down: keep the outage that is open rather than opening a second one.
            return ['state' => BranchAvailabilityStateModel::STATE_DOWN, 'opens' => false, 'closes' => false];
        }

        return [
            'state'  => $down ? BranchAvailabilityStateModel::STATE_DOWN : BranchAvailabilityStateModel::STATE_UNKNOWN,
            'opens'  => $down,
            'closes' => false,
        ];
    }

    /**
     * The addresses worth trying for a branch, most authoritative first.
     *
     * Nothing is invented: a host is only probed on a port somebody configured for it. `pf_status.ip` is
     * the address the device reports for itself, so it comes first, then the management address from the
     * inventory (which is what a config push would use). A host with no port at all is tried on 443 — the
     * pfSense webGUI default — because these devices answer there far more often than not, and a branch
     * that answers is up.
     *
     * @return list<string> "host:port"
     */
    public static function probeTargets(array $device, ?array $status = null): array
    {
        $candidates = [];

        $statusIp = trim((string) ($status['ip'] ?? ''));
        $statusPorts = self::ports($status['port'] ?? null);
        $sshPorts = self::ports($status['ssh_port'] ?? null);
        $manIp = trim((string) ($device['man_ip'] ?? ''));
        $manPorts = self::ports($device['man_port'] ?? null);

        foreach ($statusPorts as $port) {
            $candidates[] = [$statusIp, $port];
        }

        foreach ($sshPorts as $port) {
            $candidates[] = [$statusIp, $port];
        }

        foreach ($manPorts as $port) {
            $candidates[] = [$manIp, $port];
        }

        if ($statusIp !== '' && $statusPorts === [] && $sshPorts === []) {
            $candidates[] = [$statusIp, self::DEFAULT_PORT];
        }

        if ($manIp !== '' && $manPorts === []) {
            $candidates[] = [$manIp, self::DEFAULT_PORT];
        }

        $targets = [];

        foreach ($candidates as [$host, $port]) {
            $host = trim((string) $host);
            $port = (int) $port;

            if ($host === '' || $port < 1 || $port > 65535) {
                continue;
            }

            $target = $host . ':' . $port;

            if (!in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        return array_slice($targets, 0, self::MAX_PROBE_TARGETS);
    }

    /**
     * The window a report describes, clamped.
     *
     * A "from" before any monitoring began is honest — the report then says how long each branch was
     * actually observed inside it, rather than pretending to have watched for longer.
     *
     * @return array{from:string, to:string, seconds:int, days:int}
     */
    public static function windowBounds(?string $from, ?string $to, ?int $now = null): array
    {
        $now = $now ?? time();
        $end = self::parseTime($to) ?? $now;

        if ($end > $now) {
            $end = $now;
        }

        $start = self::parseTime($from) ?? ($end - (self::DEFAULT_WINDOW_DAYS * 86400));

        if ($start >= $end) {
            $start = $end - 86400;
        }

        $earliest = $end - (self::MAX_WINDOW_DAYS * 86400);

        if ($start < $earliest) {
            $start = $earliest;
        }

        return [
            'from'    => date('Y-m-d H:i:s', $start),
            'to'      => date('Y-m-d H:i:s', $end),
            'seconds' => $end - $start,
            'days'    => (int) round(($end - $start) / 86400),
        ];
    }

    /** Seconds of an outage that fall inside `[from, to]`. An open outage runs to `now`. */
    public static function overlapSeconds(array $event, string $from, string $to, int $now): int
    {
        $started = self::parseTime($event['started_at'] ?? null);

        if ($started === null) {
            return 0;
        }

        $ended = self::parseTime($event['ended_at'] ?? null) ?? $now;

        if ($ended < $started) {
            $ended = $started;
        }

        $low  = max($started, (int) strtotime($from));
        $high = min($ended, (int) strtotime($to), $now);

        return max(0, $high - $low);
    }

    /**
     * A branch's outage summary over a window.
     *
     * `uptime` is null when we did not observe any of the window — a device nobody is watching must read
     * as "not observed", never as 100%.
     *
     * A null `$monitoredSince` means *never observed*, which is a different answer from "observed for the
     * whole window": it returns no uptime at all. Treating the absence of a monitoring record as a clean
     * sheet is the single most flattering lie this page could tell.
     *
     * @param list<array<string, mixed>> $events
     *
     * @return array{observed_seconds:int, downtime_seconds:int, uptime:?float, outages:int, longest_seconds:int}
     */
    public static function outageSummary(array $events, string $from, string $to, ?string $monitoredSince, int $now): array
    {
        if ($monitoredSince === null || trim($monitoredSince) === '') {
            return ['observed_seconds' => 0, 'downtime_seconds' => 0, 'uptime' => null, 'outages' => 0, 'longest_seconds' => 0];
        }

        $observedFrom = max((int) strtotime($from), self::parseTime($monitoredSince) ?? (int) strtotime($from));
        $end          = (int) strtotime($to);

        if ($end <= $observedFrom) {
            return ['observed_seconds' => 0, 'downtime_seconds' => 0, 'uptime' => null, 'outages' => 0, 'longest_seconds' => 0];
        }

        $observed = $end - $observedFrom;
        $downtime = 0;
        $outages  = 0;
        $longest  = 0;

        $windowFrom = date('Y-m-d H:i:s', $observedFrom);

        foreach ($events as $event) {
            $overlap = self::overlapSeconds($event, $windowFrom, $to, $now);

            if ($overlap <= 0) {
                continue;
            }

            $outages++;
            $downtime += $overlap;
            $longest = max($longest, $overlap);
        }

        // Overlapping or duplicated events must not produce negative uptime: the sum of the outages can
        // never be more than the time they happened in.
        $downtime = min($downtime, $observed);

        return [
            'observed_seconds' => $observed,
            'downtime_seconds' => $downtime,
            'uptime'           => round((($observed - $downtime) / $observed) * 100, 2),
            'outages'          => $outages,
            'longest_seconds'  => $longest,
        ];
    }

    /**
     * Attaches the change that preceded each outage, when there is one.
     *
     * Deliberately narrow: only jobs applied to *that same device* within the correlation window before
     * the outage began. A job applied elsewhere in the fleet, or one applied while the branch was already
     * down, says nothing about why it went down — and a report that implies otherwise would send people
     * looking in the wrong place at the worst moment.
     *
     * @param list<array<string, mixed>> $outages
     * @param list<array<string, mixed>> $jobs
     *
     * @return list<array<string, mixed>>
     */
    public static function correlate(array $outages, array $jobs, int $windowMinutes = self::CORRELATE_WINDOW_MINUTES): array
    {
        foreach ($outages as $index => $outage) {
            $started = self::parseTime($outage['started_at'] ?? null);
            $pfId    = (int) ($outage['pf_id'] ?? 0);

            if ($started === null || $pfId <= 0) {
                continue;
            }

            $best = null;

            foreach ($jobs as $job) {
                if ((int) ($job['pf_id'] ?? 0) !== $pfId) {
                    continue;
                }

                $applied = self::parseTime($job['applied_at'] ?? null);

                if ($applied === null) {
                    continue;
                }

                $gap = $started - $applied;

                if ($gap < 0 || $gap > $windowMinutes * 60) {
                    continue;
                }

                if ($best === null || $gap < $best['gap']) {
                    $best = ['gap' => $gap, 'job' => $job];
                }
            }

            if ($best !== null) {
                $outages[$index]['job_id']          = (int) ($best['job']['job_id'] ?? 0);
                $outages[$index]['job_gap_minutes'] = (int) round($best['gap'] / 60);
                $outages[$index]['job']             = $best['job'];
            }
        }

        return $outages;
    }

    /**
     * The filters, clamped to something safe to run.
     *
     * @return array{branch:string, state:string, kind:string, group:string, from:string, to:string}
     */
    public static function normalizeFilters(array $input): array
    {
        $state = strtolower((string) ($input['state'] ?? ''));
        $kind  = strtolower((string) ($input['kind'] ?? ''));

        return [
            'branch' => mb_substr(trim((string) ($input['branch'] ?? '')), 0, 80),
            'state'  => in_array($state, ['up', 'down', 'unknown', 'degraded'], true) ? $state : '',
            'kind'   => in_array($kind, [BranchAvailabilityEventModel::KIND_UNREACHABLE, BranchAvailabilityEventModel::KIND_SILENT], true) ? $kind : '',
            'group'  => mb_substr(trim((string) ($input['group'] ?? '')), 0, 80),
            'from'   => self::dateish((string) ($input['from'] ?? '')),
            'to'     => self::dateish((string) ($input['to'] ?? '')),
        ];
    }

    /** The export as CSV: one row per outage, with the branch's window figures beside it. */
    public static function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'Branch', 'Location', 'How', 'Started', 'Ended', 'Duration (min)', 'Evidence',
            'Change before it', 'Minutes between', 'Uptime in window (%)', 'Downtime in window (min)', 'Outages in window',
            'MPLS Link', 'ILL Link', 'BDR Link', 'Total Flaps',
        ]);

        foreach ($rows as $row) {
            $mplsStatus = $row['links']['mpls']['status'] ?? null;
            $illStatus  = $row['links']['ill']['status'] ?? null;
            $bdrStatus  = $row['links']['bdr']['status'] ?? null;

            $mplsStr = $mplsStatus !== null ? ($mplsStatus === 1 ? 'UP' : 'DOWN') : '—';
            $illStr  = $illStatus !== null ? ($illStatus === 1 ? 'UP' : 'DOWN') : '—';
            $bdrStr  = $bdrStatus !== null ? ($bdrStatus === 1 ? 'UP' : 'DOWN') : '—';
            $flaps   = isset($row['total_flaps']) ? (string) (int) $row['total_flaps'] : '0';

            fputcsv($handle, [
                (string) ($row['device_name'] ?? ''),
                (string) ($row['location'] ?? ''),
                BranchAvailabilityEventModel::kindLabel((string) ($row['kind'] ?? BranchAvailabilityEventModel::KIND_UNREACHABLE)),
                (string) ($row['started_at'] ?? ''),
                $row['ended_at'] !== null && $row['ended_at'] !== '' ? (string) $row['ended_at'] : 'still down',
                $row['duration_seconds'] !== null ? (string) round(((int) $row['duration_seconds']) / 60, 1) : '',
                (string) ($row['evidence'] ?? ''),
                isset($row['job_id']) && (int) $row['job_id'] > 0 ? 'job #' . (int) $row['job_id'] : 'no change recorded in the ' . self::CORRELATE_WINDOW_MINUTES . ' min before it',
                isset($row['job_gap_minutes']) && $row['job_gap_minutes'] !== null ? (string) (int) $row['job_gap_minutes'] : '',
                $row['uptime'] !== null ? (string) $row['uptime'] : 'not observed',
                isset($row['downtime_seconds']) ? (string) round(((int) $row['downtime_seconds']) / 60, 1) : '',
                isset($row['outages']) ? (string) (int) $row['outages'] : '',
                $mplsStr,
                $illStr,
                $bdrStr,
                $flaps,
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public static function filename(): string
    {
        return 'branch-availability-' . date('Ymd-His') . '.csv';
    }

    public static function xlsxFilename(?string $branchName = null): string
    {
        $prefix = $branchName ? 'branch-' . preg_replace('/[^a-zA-Z0-9_\-]+/', '-', $branchName) : 'branch-availability';
        return rtrim($prefix, '-') . '-' . date('Ymd-His') . '.xlsx';
    }

    public static function kindLabel(string $kind): string
    {
        return BranchAvailabilityEventModel::kindLabel($kind);
    }

    // ======================================================================= observing

    /**
     * Records one observation of a branch — a probe result, or a check-in's absence — and opens or closes
     * an outage if the state changed.
     *
     * @param array<string, mixed> $device
     *
     * @return array{pf_id:int, device:string, from:string, to:string, opened:bool, closed:bool, event_id:?int}
     */
    public function observe(
        array $device,
        bool $ok,
        string $source = BranchAvailabilityStateModel::SOURCE_PROBE,
        ?string $evidence = null,
        ?int $latencyMs = null,
        ?string $at = null,
        ?string $kind = null,
        ?string $target = null,
        ?string $sinceAt = null,
        int $failuresToDown = self::FAILS_TO_DOWN
    ): array {
        $pfId = (int) ($device['id'] ?? 0);
        $name = trim((string) ($device['name'] ?? ''));

        if ($pfId <= 0) {
            return ['pf_id' => 0, 'device' => $name, 'from' => '', 'to' => '', 'opened' => false, 'closed' => false, 'event_id' => null];
        }

        $at = $at ?? date('Y-m-d H:i:s');
        $row = $this->stateRow($pfId);

        $current   = (string) ($row['state'] ?? BranchAvailabilityStateModel::STATE_UNKNOWN);
        $failures  = (int) ($row['consecutive_failures'] ?? 0);
        $successes = (int) ($row['consecutive_successes'] ?? 0);

        // The first failure of a run is the outage's start, so it is recorded the moment it happens rather
        // than reconstructed from the probe that confirmed it.
        // `sinceAt` is for an observation that knows when the trouble *began* rather than when it was seen:
        // an inferred silence is dated from the moment it became provable, not from the moment we noticed.
        $failingSince = (string) ($row['failing_since'] ?? '');
        $failingSince = $ok ? null : ($failingSince !== '' ? $failingSince : ($sinceAt ?? $at));

        $next = self::nextState($current, $failures, $ok, $failuresToDown);
        $now  = date('Y-m-d H:i:s');

        $this->writeState([
            'pf_id'                 => $pfId,
            'device_name'           => $name !== '' ? $name : ('device #' . $pfId),
            'location_id'           => $device['location'] ?? null,
            'state'                 => $next['state'],
            'since'                 => $next['state'] === $current ? ($row['since'] ?? $at) : $at,
            'monitored_since'       => $row['monitored_since'] ?? $at,
            'last_probe_at'         => $at,
            'last_probe_ok'         => $ok ? 1 : 0,
            'last_probe_ms'         => $latencyMs,
            'last_probe_target'     => $target,
            'failing_since'         => $failingSince,
            'consecutive_failures'  => $ok ? 0 : ($failures + 1),
            'consecutive_successes' => $ok ? ($successes + 1) : 0,
            'last_checkin_at'       => $this->checkinAt($device),
            'source'                => $source,
            'updated_at'            => $now,
        ]);

        $eventId = null;
        $opened  = false;
        $closed  = false;

        if ($next['opens']) {
            $eventId = $this->openOutage(
                $device,
                (string) ($kind ?? BranchAvailabilityEventModel::KIND_UNREACHABLE),
                $failingSince ?? $at,
                $evidence
            );
            $opened = true;
        }

        if ($next['closes']) {
            $this->closeOutage($pfId, $at, $evidence);
            $closed = true;
        }

        return [
            'pf_id'    => $pfId,
            'device'   => $name !== '' ? $name : ('device #' . $pfId),
            'from'     => $current,
            'to'       => $next['state'],
            'opened'   => $opened,
            'closed'   => $closed,
            'event_id' => $eventId,
        ];
    }

    /**
     * Connects to one address and reports whether it answered.
     *
     * A TCP connect to the device's own management port is the least intrusive proof of life available:
     * it needs no credentials, changes nothing, and answers the only question that matters — can pfCentral
     * reach this branch.
     *
     * @return array{ok:bool, ms:int, detail:string}
     */
    public function probe(string $target, ?int $timeout = null): array
    {
        $timeout = $timeout ?? self::PROBE_TIMEOUT;
        $started = microtime(true);
        $error   = '';
        $errno   = 0;

        $socket = @stream_socket_client(
            'tcp://' . $target,
            $errno,
            $error,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        $ms = (int) round((microtime(true) - $started) * 1000);

        if ($socket === false) {
            return [
                'ok'     => false,
                'ms'     => $ms,
                'detail' => trim($error) !== '' ? 'no answer on ' . $target . ': ' . trim($error) : 'no answer on ' . $target,
            ];
        }

        fclose($socket);

        return ['ok' => true, 'ms' => $ms, 'detail' => 'answered on ' . $target . ' in ' . $ms . ' ms'];
    }

    /**
     * Probes every branch we can reach and records what happened.
     *
     * @param array{limit:?int, device:?string, timeout:?int, dry:bool} $options
     *
     * @return array{branches:int, probed:int, up:int, down:int, skipped:int, transitions:list<array<string, mixed>>, silent:list<array<string, mixed>>, results:list<array<string, mixed>>}
     */
    public function sweep(array $options = []): array
    {
        $limit    = $options['limit'] ?? null;
        $deviceId = isset($options['device_id']) && $options['device_id'] !== null ? (int) $options['device_id'] : (isset($options['pf_id']) ? (int) $options['pf_id'] : null);
        $device   = $options['device'] ?? null;
        $timeout  = $options['timeout'] ?? null;
        $dry      = (bool) ($options['dry'] ?? false);
        $now      = time();

        $branches = $this->branches();
        $probed   = 0;
        $up       = 0;
        $down     = 0;
        $skipped  = 0;
        $transitions = [];
        $silent      = [];
        $results     = [];

        foreach ($branches as $branch) {
            if ($limit !== null && $probed >= $limit) {
                break;
            }

            if ($deviceId !== null && $deviceId > 0) {
                if ((int) $branch['id'] !== $deviceId) {
                    continue;
                }
            } elseif ($device !== null && $device !== '') {
                if (!$this->matchesDevice($branch, (string) $device)) {
                    continue;
                }
            }

            $targets = self::probeTargets($branch, $branch['status'] ?? null);

            if ($targets === []) {
                // No address to probe: the only evidence about this branch is whether it is checking in.
                $skipped++;
                $silence = $this->silenceObservation($branch, $now);

                if ($silence !== null) {
                    $silent[] = $silence;
                }

                if ($silence !== null && !$dry) {
                    $transition = $this->observe(
                        $branch,
                        false,
                        BranchAvailabilityStateModel::SOURCE_AGENT,
                        $silence['evidence'],
                        null,
                        null,
                        BranchAvailabilityEventModel::KIND_SILENT,
                        null,
                        $silence['since'],
                        self::SILENCE_CONFIRMS
                    );

                    if ($transition['opened'] || $transition['closed']) {
                        $transitions[] = $transition;
                    }
                }

                continue;
            }

            $probed++;
            $answer = null;

            foreach ($targets as $target) {
                // Probing is read-only — it opens a TCP connection and closes it — so a dry run still
                // probes for real and simply records nothing. Reporting "up" without asking would make
                // `--dry` a way of producing an answer that was never measured.
                $result = $this->probe($target, $timeout);

                if ($result['ok']) {
                    $answer = ['target' => $target] + $result;

                    break;
                }

                $answer = ['target' => $target] + $result;
            }

            if ($answer === null) {
                continue;
            }

            if ($answer['ok']) {
                $up++;
            } else {
                $down++;
            }

            $results[] = ['device' => $branch['name'], 'target' => $answer['target'], 'ok' => $answer['ok'], 'ms' => $answer['ms']];

            if ($dry) {
                continue;
            }

            $transition = $this->observe(
                $branch,
                $answer['ok'],
                BranchAvailabilityStateModel::SOURCE_PROBE,
                $answer['detail'],
                $answer['ms'],
                null,
                null,
                $answer['target']
            );

            if ($transition['opened'] || $transition['closed']) {
                $transitions[] = $transition;
            }
        }

        if (!$dry) {
            $this->pruneEvents(self::RETENTION_DAYS);
        }

        return [
            'branches'    => count($branches),
            'probed'      => $probed,
            'up'          => $up,
            'down'        => $down,
            'skipped'     => $skipped,
            'transitions' => $transitions,
            'silent'      => $silent,
            'results'     => $results,
        ];
    }

    // ======================================================================= reading

    /**
     * Every branch, with its device metadata, its current state, and (when asked) its window figures.
     * Enriched with real-time multi-WAN link status (MPLS, ILL, BDR) and flap counts from locations.
     *
     * @return list<array<string, mixed>>
     */
    public function branches(): array
    {
        $rows = [];

        try {
            foreach ($this->inventory->findAll() as $device) {
                $pfId = (int) ($device['id'] ?? 0);

                if ($pfId <= 0) {
                    continue;
                }

                $rows[$pfId] = [
                    'id'       => $pfId,
                    'name'     => trim((string) ($device['name'] ?? '')) !== '' ? (string) $device['name'] : ('device #' . $pfId),
                    'active'   => (string) ($device['active'] ?? '') === '1',
                    'groups'   => (string) ($device['groups'] ?? ''),
                    'man_ip'   => (string) ($device['man_ip'] ?? ''),
                    'man_port' => (string) ($device['man_port'] ?? ''),
                    'location' => $device['location'] ?? null,
                    'status'   => null,
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'Inventory read failed while listing branches: ' . $e->getMessage());
        }

        try {
            foreach ($this->db->table('pf_status')->select('pf_id, ip, port, ssh_port, updated_at, status')->get()->getResultArray() as $row) {
                $pfId = (int) $row['pf_id'];

                if (!isset($rows[$pfId])) {
                    continue;
                }

                $rows[$pfId]['status'] = $row;
                $rows[$pfId]['last_checkin_at'] = $row['updated_at'];
            }
        } catch (\Throwable $e) {
            log_message('error', 'pf_status read failed while listing branches: ' . $e->getMessage());
        }

        // Fetch location metadata and multi-WAN links (MPLS, ILL, BDR)
        $locations = [];
        try {
            foreach ($this->db->table('pf_locations')->select('id, name, ip, wg_mpls, wg_ill, wg_bdr')->get()->getResultArray() as $loc) {
                $locations[(int) $loc['id']] = $loc;
            }
        } catch (\Throwable $e) {
            log_message('error', 'pf_locations read failed while listing branches: ' . $e->getMessage());
        }

        $mplsMap = [];
        try {
            foreach ($this->db->table('wg_mpls')->select('location_id, interface, status, flap_count, updated_at')->get()->getResultArray() as $m) {
                $mplsMap[(int) $m['location_id']] = $m;
            }
        } catch (\Throwable $e) {}

        $illMap = [];
        try {
            foreach ($this->db->table('wg_ill')->select('location_id, interface, status, flap_count, updated_at')->get()->getResultArray() as $i) {
                $illMap[(int) $i['location_id']] = $i;
            }
        } catch (\Throwable $e) {}

        $bdrMap = [];
        try {
            foreach ($this->db->table('wg_bdr')->select('location_id, interface, status, flap_count, updated_at')->get()->getResultArray() as $b) {
                $bdrMap[(int) $b['location_id']] = $b;
            }
        } catch (\Throwable $e) {}

        foreach ($rows as $pfId => $row) {
            $locId = (int) ($row['location'] ?? 0);
            $loc   = $locations[$locId] ?? null;

            $mplsRow = $locId > 0 ? ($mplsMap[$locId] ?? null) : null;
            $illRow  = $locId > 0 ? ($illMap[$locId] ?? null) : null;
            $bdrRow  = $locId > 0 ? ($bdrMap[$locId] ?? null) : null;

            $mplsFlaps  = (int) ($mplsRow['flap_count'] ?? 0);
            $illFlaps   = (int) ($illRow['flap_count'] ?? 0);
            $bdrFlaps   = (int) ($bdrRow['flap_count'] ?? 0);
            $totalFlaps = $mplsFlaps + $illFlaps + $bdrFlaps;

            $links = [
                'mpls' => [
                    'interface'  => $loc['wg_mpls'] ?? ($mplsRow['interface'] ?? null),
                    'status'     => isset($mplsRow['status']) ? (int) $mplsRow['status'] : null,
                    'flap_count' => $mplsFlaps,
                    'updated_at' => $mplsRow['updated_at'] ?? null,
                ],
                'ill' => [
                    'interface'  => $loc['wg_ill'] ?? ($illRow['interface'] ?? null),
                    'status'     => isset($illRow['status']) ? (int) $illRow['status'] : null,
                    'flap_count' => $illFlaps,
                    'updated_at' => $illRow['updated_at'] ?? null,
                ],
                'bdr' => [
                    'interface'  => $loc['wg_bdr'] ?? ($bdrRow['interface'] ?? null),
                    'status'     => isset($bdrRow['status']) ? (int) $bdrRow['status'] : null,
                    'flap_count' => $bdrFlaps,
                    'updated_at' => $bdrRow['updated_at'] ?? null,
                ],
            ];

            $rows[$pfId]['location_name'] = $loc['name'] ?? null;
            $rows[$pfId]['location_ip']   = $loc['ip'] ?? null;
            $rows[$pfId]['links']         = $links;
            $rows[$pfId]['total_flaps']   = $totalFlaps;
            $rows[$pfId]['is_flapping']   = $totalFlaps > 0;
        }

        return array_values($rows);
    }

    /**
     * The report: the fleet's state now, a branch-by-branch window, and the outages inside it.
     *
     * @param array{branch:string, state:string, kind:string, group:string, from:string, to:string} $filters
     *
     * @return array<string, mixed>
     */
    public function report(array $filters, int $page = 1): array
    {
        $page   = max(1, $page);
        $now    = time();
        $window = self::windowBounds($filters['from'] ?: null, $filters['to'] ?: null, $now);
        $from   = $window['from'];
        $to     = $window['to'];

        $states = $this->stateRows();

        $branchRows = [];

        foreach ($this->branches() as $branch) {
            $pfId  = (int) $branch['id'];
            $state = $states[$pfId] ?? null;

            if (!$this->matchesFilters($branch, $state, $filters)) {
                continue;
            }

            $checkin = $state['last_checkin_at'] ?? ($branch['last_checkin_at'] ?? null);

            $rawState   = (string) ($state['state'] ?? BranchAvailabilityStateModel::STATE_UNKNOWN);
            $linkHealth = 'unknown';

            if ($rawState === BranchAvailabilityStateModel::STATE_DOWN) {
                $linkHealth = 'down';
            } elseif ($rawState === BranchAvailabilityStateModel::STATE_UP) {
                $mplsStatus = $branch['links']['mpls']['status'] ?? null;
                $illStatus  = $branch['links']['ill']['status'] ?? null;
                $bdrStatus  = $branch['links']['bdr']['status'] ?? null;

                if ($mplsStatus === 1) {
                    $linkHealth = 'healthy';
                } elseif ($illStatus === 1 || $bdrStatus === 1) {
                    $linkHealth = 'degraded'; // Primary MPLS down, running on backup link!
                } elseif ($mplsStatus !== null && $mplsStatus === 0 && $illStatus === 0 && $bdrStatus === 0) {
                    $linkHealth = 'down';
                } else {
                    $linkHealth = 'healthy';
                }
            }

            $branchRows[] = $branch + [
                'state'             => $rawState,
                'state_label'       => BranchAvailabilityStateModel::stateLabel($rawState),
                'link_health'       => $linkHealth,
                'link_health_label' => self::linkHealthLabel($linkHealth),
                'since'             => $state['since'] ?? null,
                'monitored_since'   => $state['monitored_since'] ?? null,
                'last_probe_at'     => $state['last_probe_at'] ?? null,
                'last_probe_ok'     => $state['last_probe_ok'] ?? null,
                'last_probe_ms'     => $state['last_probe_ms'] ?? null,
                'last_probe_target' => $state['last_probe_target'] ?? null,
                'last_checkin_at'   => $checkin,
                'targets'           => self::probeTargets($branch, $branch['status']),
                'silent_since'      => $this->silentSince($checkin, $now),
            ];
        }

        // Outages that touch the window: one that began before it still counts for the time it covers.
        $events = $this->eventsIn($from, $to);

        $jobs = $this->appliedJobs($from, $to);

        $byPfId = [];

        foreach ($events as $event) {
            $byPfId[(int) $event['pf_id']][] = $event;
        }

        foreach ($branchRows as $index => $branch) {
            $own     = $byPfId[(int) $branch['id']] ?? [];
            $summary = self::outageSummary($own, $from, $to, $branch['monitored_since'], $now);

            $lastOutage = null;

            foreach ($own as $event) {
                if ($lastOutage === null || (string) $event['started_at'] > (string) $lastOutage['started_at']) {
                    $lastOutage = $event;
                }
            }

            $branchRows[$index]['uptime']           = $summary['uptime'];
            $branchRows[$index]['downtime_seconds'] = $summary['downtime_seconds'];
            $branchRows[$index]['observed_seconds'] = $summary['observed_seconds'];
            $branchRows[$index]['outages']          = $summary['outages'];
            $branchRows[$index]['longest_seconds']  = $summary['longest_seconds'];
            $branchRows[$index]['last_outage_at']   = $lastOutage['started_at'] ?? null;
            $branchRows[$index]['open_outage']      = $lastOutage !== null && empty($lastOutage['ended_at']) ? $lastOutage : null;
        }

        // Outages for the detail list, newest first, each carrying the change that preceded it.
        //
        // The branch filter has to narrow this list too: a report for one site whose outage table still
        // lists the rest of the fleet is a report nobody can hand to anybody.
        $allowedIds = [];

        foreach ($branchRows as $branch) {
            $allowedIds[(int) $branch['id']] = true;
        }

        $events = array_values(array_filter($events, static fn (array $event): bool => isset($allowedIds[(int) $event['pf_id']])));

        $outages = self::correlate($events, $jobs);

        if ($filters['kind'] !== '') {
            $outages = array_values(array_filter($outages, static fn (array $event): bool => (string) $event['kind'] === $filters['kind']));
        }

        if ($filters['state'] === BranchAvailabilityStateModel::STATE_DOWN) {
            $downIds = [];

            foreach ($branchRows as $branch) {
                if ($branch['state'] === BranchAvailabilityStateModel::STATE_DOWN) {
                    $downIds[] = (int) $branch['id'];
                }
            }

            $outages = array_values(array_filter($outages, static fn (array $event): bool => in_array((int) $event['pf_id'], $downIds, true)));
        }

        usort($outages, static fn (array $a, array $b): int => strcmp((string) $b['started_at'], (string) $a['started_at']));

        // Sorting is by name so the page reads like a site list, not like a database.
        usort($branchRows, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));

        foreach ($branchRows as $index => $branch) {
            $location = $branch['location'] ?? null;

            if ($location !== null && $location !== '') {
                $branchRows[$index]['location'] = $this->locationName((int) $location);
            } else {
                $branchRows[$index]['location'] = '';
            }

            $branchRows[$index]['poor'] = $branch['uptime'] !== null && $branch['uptime'] < self::POOR_UPTIME;
        }

        foreach ($outages as $index => $outage) {
            $outages[$index]['location'] = $this->locationName((int) ($outage['location_id'] ?? 0));
            $outages[$index]['kind_label'] = BranchAvailabilityEventModel::kindLabel((string) $outage['kind']);
            $outages[$index]['duration_minutes'] = $outage['duration_seconds'] !== null ? round(((int) $outage['duration_seconds']) / 60, 1) : null;
        }

        $total = count($outages);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);
        $slice = array_slice($outages, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        // The branch figures are attached to each outage row so the CSV can carry both without a second
        // document: an outage list that does not say how bad the month was answers half the question.
        $figures = [];

        foreach ($branchRows as $branch) {
            $figures[(int) $branch['id']] = [
                'uptime'           => $branch['uptime'],
                'downtime_seconds' => $branch['downtime_seconds'],
                'outages'          => $branch['outages'],
            ];
        }

        foreach ($slice as $index => $outage) {
            $fig = $figures[(int) $outage['pf_id']] ?? [];
            $slice[$index]['uptime']           = $fig['uptime'] ?? null;
            $slice[$index]['downtime_seconds'] = $fig['downtime_seconds'] ?? null;
            $slice[$index]['outages']          = $fig['outages'] ?? null;
        }

        return [
            'filters'     => $filters,
            'window'      => $window,
            'branches'    => $branchRows,
            'outages'     => $slice,
            'tiles'       => $this->tiles($branchRows, $outages),
            'groups'      => $this->groupOptions(),
            'total'       => $total,
            'page'        => $page,
            'pages'       => $pages,
            'per_page'    => self::PER_PAGE,
            'correlated'  => count(array_filter($outages, static fn (array $event): bool => (int) ($event['job_id'] ?? 0) > 0)),
        ];
    }

    /**
     * Every outage row for the filters, for the export.
     *
     * @param array{branch:string, state:string, kind:string, group:string, from:string, to:string} $filters
     *
     * @return array{rows:list<array<string, mixed>>, total:int, capped:bool}
     */
    public function exportRows(array $filters): array
    {
        $report = $this->report($filters, 1);
        $window = $report['window'];

        // The report paginates; the export does not. Recomputing the detail list unpaginated keeps the two
        // from drifting apart, at the cost of one more query for a file somebody asked for explicitly.
        $events = self::correlate($this->eventsIn($window['from'], $window['to']), $this->appliedJobs($window['from'], $window['to']));

        $allowed = [];

        foreach ($report['branches'] as $branch) {
            $allowed[(int) $branch['id']] = $branch;
        }

        $rows = [];

        foreach ($events as $event) {
            $pfId = (int) $event['pf_id'];

            if (!isset($allowed[$pfId])) {
                continue;
            }

            if ($filters['kind'] !== '' && (string) $event['kind'] !== $filters['kind']) {
                continue;
            }

            $branch = $allowed[$pfId];

            $rows[] = $event + [
                'uptime'           => $branch['uptime'],
                'downtime_seconds' => $branch['downtime_seconds'],
                'outages'          => $branch['outages'],
                'location'         => $branch['location'],
                'links'            => $branch['links'] ?? [],
                'total_flaps'      => $branch['total_flaps'] ?? 0,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp((string) $b['started_at'], (string) $a['started_at']));

        return ['rows' => $rows, 'total' => count($rows), 'capped' => count($rows) > self::MAX_EXPORT];
    }

    /**
     * Complete availability, multi-WAN telemetry, and outage profile for an individual branch device.
     *
     * @param int $pfId Device inventory ID
     * @param int $windowDays Days to look back for SLA window (default 7 days)
     * @return array<string, mixed>|null
     */
    public function getDeviceAvailabilityDetail(int $pfId, int $windowDays = self::DEFAULT_WINDOW_DAYS): ?array
    {
        $pfId = (int) $pfId;
        if ($pfId <= 0) {
            return null;
        }

        $device = $this->inventory->find($pfId);
        if ($device === null) {
            return null;
        }

        $state   = $this->stateRow($pfId);
        $status  = $this->db->table('pf_status')->where('pf_id', $pfId)->get()->getRowArray() ?? [];
        $locId   = (int) ($device['location'] ?? 0);
        $loc     = $locId > 0 ? ($this->db->table('pf_locations')->where('id', $locId)->get()->getRowArray() ?? []) : [];

        // WAN Links
        $mplsRow = $locId > 0 ? ($this->db->table('wg_mpls')->where('location_id', $locId)->get()->getRowArray() ?? []) : [];
        $illRow  = $locId > 0 ? ($this->db->table('wg_ill')->where('location_id', $locId)->get()->getRowArray() ?? []) : [];
        $bdrRow  = $locId > 0 ? ($this->db->table('wg_bdr')->where('location_id', $locId)->get()->getRowArray() ?? []) : [];

        $mplsFlaps  = (int) ($mplsRow['flap_count'] ?? 0);
        $illFlaps   = (int) ($illRow['flap_count'] ?? 0);
        $bdrFlaps   = (int) ($bdrRow['flap_count'] ?? 0);
        $totalFlaps = $mplsFlaps + $illFlaps + $bdrFlaps;

        $links = [
            'mpls' => [
                'interface'  => $loc['wg_mpls'] ?? ($mplsRow['interface'] ?? null),
                'status'     => isset($mplsRow['status']) ? (int) $mplsRow['status'] : null,
                'flap_count' => $mplsFlaps,
                'updated_at' => $mplsRow['updated_at'] ?? null,
            ],
            'ill' => [
                'interface'  => $loc['wg_ill'] ?? ($illRow['interface'] ?? null),
                'status'     => isset($illRow['status']) ? (int) $illRow['status'] : null,
                'flap_count' => $illFlaps,
                'updated_at' => $illRow['updated_at'] ?? null,
            ],
            'bdr' => [
                'interface'  => $loc['wg_bdr'] ?? ($bdrRow['interface'] ?? null),
                'status'     => isset($bdrRow['status']) ? (int) $bdrRow['status'] : null,
                'flap_count' => $bdrFlaps,
                'updated_at' => $bdrRow['updated_at'] ?? null,
            ],
        ];

        $linkHealth = 'healthy';
        $configuredCount = 0;
        $downCount = 0;
        foreach (['mpls', 'ill', 'bdr'] as $lk) {
            if (!empty($links[$lk]['interface'])) {
                $configuredCount++;
                if ($links[$lk]['status'] === 0) {
                    $downCount++;
                }
            }
        }
        if ($configuredCount === 0) {
            $linkHealth = 'unknown';
        } elseif ($downCount === $configuredCount) {
            $linkHealth = 'down';
        } elseif ($downCount > 0) {
            $linkHealth = 'degraded';
        }

        // Window bounds
        $windowDays = max(1, min(self::MAX_WINDOW_DAYS, $windowDays));
        $now = time();
        $from = date('Y-m-d H:i:s', $now - ($windowDays * 86400));
        $to   = date('Y-m-d H:i:s', $now);

        // Outages for this device in window (and open outages)
        $eventsRaw = $this->db->table('branch_availability_events')
            ->where('pf_id', $pfId)
            ->where('started_at <=', $to)
            ->groupStart()
                ->where('ended_at', null)
                ->orWhere('ended_at >=', $from)
            ->groupEnd()
            ->orderBy('started_at', 'DESC')
            ->limit(200)
            ->get()
            ->getResultArray();

        $correlatedEvents = self::correlate($eventsRaw, $this->appliedJobs($from, $to));

        $downtimeSeconds = 0;
        foreach ($correlatedEvents as $idx => $ev) {
            $startTs = max((int) strtotime($from), (int) strtotime((string) $ev['started_at']));
            $endTs   = $ev['ended_at'] !== null ? min((int) strtotime($to), (int) strtotime((string) $ev['ended_at'])) : $now;
            $duration = max(0, $endTs - $startTs);
            $downtimeSeconds += $duration;

            $correlatedEvents[$idx]['kind_label'] = BranchAvailabilityEventModel::kindLabel((string) $ev['kind']);
            $correlatedEvents[$idx]['duration_minutes'] = $ev['duration_seconds'] !== null ? round(((int) $ev['duration_seconds']) / 60, 1) : null;
        }

        $monitoredSince = $state['monitored_since'] ?? null;
        $windowStart = (int) strtotime($from);
        $observedFrom = $monitoredSince !== null ? max($windowStart, (int) strtotime($monitoredSince)) : null;
        $observedSeconds = $observedFrom !== null ? max(0, $now - $observedFrom) : null;

        $uptime = null;
        if ($observedSeconds !== null && $observedSeconds > 0) {
            $uptime = round(max(0.0, min(100.0, 100.0 * (1.0 - ($downtimeSeconds / $observedSeconds)))), 2);
        }

        $deviceSummary = [
            'id'                => $pfId,
            'name'              => (string) ($device['name'] ?? ''),
            'ip'                => (string) (!empty($device['man_ip']) ? $device['man_ip'] : ($status['ip'] ?? '')),
            'man_ip'            => (string) ($device['man_ip'] ?? ''),
            'webgui_port'       => (int) ($device['webgui_port'] ?? self::DEFAULT_PORT),
            'location_id'       => $locId,
            'location_name'     => (string) ($loc['name'] ?? ''),
            'state'             => (string) ($state['state'] ?? BranchAvailabilityStateModel::STATE_UNKNOWN),
            'link_health'       => $linkHealth,
            'link_health_label' => self::linkHealthLabel($linkHealth),
            'total_flaps'       => $totalFlaps,
            'is_flapping'       => $totalFlaps > 0,
            'uptime'            => $uptime,
            'downtime_seconds'  => $downtimeSeconds,
            'outages'           => count($correlatedEvents),
            'observed_seconds'  => $observedSeconds,
            'last_probe_at'     => $state['last_probe_at'] ?? null,
            'last_probe_target' => $state['last_probe_target'] ?? null,
            'last_probe_ms'     => $state['last_probe_ms'] ?? null,
            'last_checkin_at'   => $this->checkinAt(['id' => $pfId, 'status' => $status]),
            'silent_since'      => $this->silentSince($status['updated_at'] ?? null, $now),
            'monitored_since'   => $monitoredSince,
        ];

        return [
            'device'   => $deviceSummary,
            'status'   => $status,
            'location' => $loc,
            'links'    => $links,
            'outages'  => $correlatedEvents,
            'window'   => [
                'from' => $from,
                'to'   => $to,
                'days' => $windowDays,
            ],
        ];
    }

    // ======================================================================= plumbing

    /**
     * @param array<string, mixed> $data
     */
    private function writeState(array $data): void
    {
        try {
            if ($this->state->find((int) $data['pf_id']) === null) {
                $this->state->insert($data);

                return;
            }

            $this->state->update((int) $data['pf_id'], $data);
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability state write failed for device ' . ($data['pf_id'] ?? 0) . ': ' . $e->getMessage());
        }
    }


    /** @return array<string, mixed> */
    private function stateRow(int $pfId): array
    {
        try {
            return $this->state->find($pfId) ?? [];
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability state read failed: ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<int, array<string, mixed>> keyed by pf_id */
    private function stateRows(): array
    {
        $rows = [];

        try {
            foreach ($this->state->findAll() as $row) {
                $rows[(int) $row['pf_id']] = $row;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability state list failed: ' . $e->getMessage());
        }

        return $rows;
    }

    private function openOutage(array $device, string $kind, string $startedAt, ?string $evidence): ?int
    {
        $pfId = (int) ($device['id'] ?? 0);

        try {
            // One open outage per branch: closing and re-opening on every failed probe would turn one
            // outage into a row per probe.
            $existing = $this->db->table('branch_availability_events')
                ->where('pf_id', $pfId)
                ->where('ended_at', null)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();

            if ($existing !== null) {
                return (int) $existing['id'];
            }

            $id = $this->events->insert([
                'pf_id'       => $pfId,
                'device_name' => (string) ($device['name'] ?? ('device #' . $pfId)),
                'location_id' => $device['location'] ?? null,
                'kind'        => $kind,
                'started_at'  => $startedAt,
                'ended_at'    => null,
                'evidence'    => $evidence === null ? null : mb_substr($evidence, 0, 255),
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ], true);

            return $id === false ? null : (int) $id;
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability outage open failed for device ' . $pfId . ': ' . $e->getMessage());

            return null;
        }
    }

    private function closeOutage(int $pfId, string $at, ?string $evidence): void
    {
        try {
            $open = $this->db->table('branch_availability_events')
                ->where('pf_id', $pfId)
                ->where('ended_at', null)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();

            if ($open === null) {
                return;
            }

            $started  = (int) strtotime((string) $open['started_at']);
            $duration = max(0, (int) strtotime($at) - $started);

            $this->db->table('branch_availability_events')->where('id', (int) $open['id'])->update([
                'ended_at'         => $at,
                'duration_seconds' => $duration,
                'evidence'         => $evidence === null
                    ? ($open['evidence'] ?? null)
                    : mb_substr((string) $open['evidence'] . ' → recovered: ' . $evidence, 0, 255),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability outage close failed for device ' . $pfId . ': ' . $e->getMessage());
        }
    }

    /** @return list<array<string, mixed>> */
    private function eventsIn(string $from, string $to): array
    {
        try {
            return $this->db->table('branch_availability_events')
                ->where('started_at <=', $to)
                ->groupStart()
                    ->where('ended_at', null)
                    ->orWhere('ended_at >=', $from)
                ->groupEnd()
                ->orderBy('started_at', 'DESC')
                ->limit(self::MAX_EXPORT)
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability events read failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Jobs applied to devices in (and just before) the window, for correlating with an outage.
     *
     * @return list<array<string, mixed>>
     */
    private function appliedJobs(string $from, string $to): array
    {
        try {
            return $this->db->table('job_targets t')
                ->select('t.pf_id, t.job_id, t.applied_at, t.status AS target_status, j.payload_type, j.payload_mode, j.status AS job_status, j.watch_profile')
                ->join('config_jobs j', 'j.id = t.job_id', 'left')
                ->where('t.pf_id >', 0)
                ->where('t.applied_at IS NOT NULL')
                ->where('t.applied_at <=', $to)
                ->where('t.applied_at >=', date('Y-m-d H:i:s', (int) strtotime($from) - (self::CORRELATE_WINDOW_MINUTES * 60)))
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Config job read failed while correlating outages: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $branches
     * @param list<array<string, mixed>> $outages
     *
     * @return array<string, int|float>
     */
    private function tiles(array $branches, array $outages): array
    {
        $up = 0;
        $down = 0;
        $unknown = 0;
        $silent = 0;
        $poor = 0;
        $degraded = 0;
        $flapping = 0;
        $downtime = 0;
        $uptimeSum = 0;
        $uptimeCount = 0;

        foreach ($branches as $branch) {
            match ($branch['state']) {
                BranchAvailabilityStateModel::STATE_UP   => $up++,
                BranchAvailabilityStateModel::STATE_DOWN => $down++,
                default                                  => $unknown++,
            };

            if (($branch['link_health'] ?? '') === 'degraded') {
                $degraded++;
            }

            if (!empty($branch['is_flapping'])) {
                $flapping++;
            }

            if ($branch['silent_since'] !== null) {
                $silent++;
            }

            if (!empty($branch['poor'])) {
                $poor++;
            }

            if ($branch['uptime'] !== null) {
                $uptimeSum += (float) $branch['uptime'];
                $uptimeCount++;
            }

            $downtime += (int) ($branch['downtime_seconds'] ?? 0);
        }

        $openIds = [];

        foreach ($branches as $branch) {
            if (!empty($branch['open_outage'])) {
                $openIds[] = (int) $branch['id'];
            }
        }

        $fleetUptime = $uptimeCount > 0 ? round($uptimeSum / $uptimeCount, 2) : null;

        return [
            'branches'          => count($branches),
            'up'                => $up,
            'down'              => $down,
            'unknown'           => $unknown,
            'silent'            => $silent,
            'poor'              => $poor,
            'degraded'          => $degraded,
            'flapping'          => $flapping,
            'fleet_uptime'      => $fleetUptime,
            'outages'           => count($outages),
            'downtime_minutes'  => (int) round($downtime / 60),
            'open'              => count($openIds),
            'retention_days'    => self::RETENTION_DAYS,
        ];
    }

    /** When a device's check-in became old enough to be called silence, or null while it is current. */
    private function silentSince(?string $checkin, int $now): ?string
    {
        if ($checkin === null || $checkin === '') {
            return null;
        }

        $at = self::parseTime($checkin);

        if ($at === null || ($now - $at) < (self::SILENT_AFTER_MINUTES * 60)) {
            return null;
        }

        return $checkin;
    }

    /**
     * The observation that a branch with nothing to probe has gone quiet.
     *
     * @return array{device:string, at:string, evidence:string}|null
     */
    private function silenceObservation(array $branch, int $now): ?array
    {
        $checkin = $branch['last_checkin_at'] ?? null;

        if ($checkin === null || $checkin === '') {
            return null;
        }

        $at = self::parseTime($checkin);

        if ($at === null || ($now - $at) < (self::SILENT_AFTER_MINUTES * 60)) {
            return null;
        }

        return [
            'device'   => (string) $branch['name'],
            // When the silence became *provable*: the last check-in plus the threshold. Between those two
            // points the device may have been perfectly fine and merely quiet, so the outage is not dated
            // back to the last time it spoke.
            'since'    => date('Y-m-d H:i:s', $at + (self::SILENT_AFTER_MINUTES * 60)),
            'evidence' => 'no check-in since ' . $checkin . ', silence past the ' . self::SILENT_AFTER_MINUTES . '-minute threshold, and no address is configured to probe (inferred)',
        ];
    }

    /** The device's own last check-in, if there is one. */
    private function checkinAt(array $device): ?string
    {
        $status = $device['status'] ?? null;

        if (is_array($status) && !empty($status['updated_at'])) {
            return (string) $status['updated_at'];
        }

        $pfId = (int) ($device['id'] ?? 0);

        if ($pfId <= 0) {
            return null;
        }

        try {
            $row = $this->db->table('pf_status')->select('updated_at')->where('pf_id', $pfId)->get()->getRowArray();
        } catch (\Throwable $e) {
            return null;
        }

        return $row !== null && !empty($row['updated_at']) ? (string) $row['updated_at'] : null;
    }

    private function matchesDevice(array $branch, string $needle): bool
    {
        $needle = trim($needle);
        if ($needle === '') {
            return true;
        }

        if (ctype_digit($needle)) {
            // Exact ID match
            if ((string) $branch['id'] === $needle) {
                return true;
            }

            // Or exact match against the leading numeric branch code (e.g. "1011" in "1011-MALEGAON")
            if (preg_match('/^(\d+)/', (string) $branch['name'], $m) && $m[1] === $needle) {
                return true;
            }

            // If 3+ digits, allow substring match in branch name (e.g. searching "101" or "1011")
            if (strlen($needle) >= 3 && stripos((string) $branch['name'], $needle) !== false) {
                return true;
            }

            return false;
        }

        return (string) $branch['id'] === $needle || stripos((string) $branch['name'], $needle) !== false;
    }

    private function matchesFilters(array $branch, ?array $state, array $filters): bool
    {
        if ($filters['branch'] !== '' && !$this->matchesDevice($branch, $filters['branch'])) {
            return false;
        }

        if ($filters['group'] !== '') {
            $groups = array_map('trim', explode(',', (string) ($branch['groups'] ?? '')));

            if (!in_array($filters['group'], $groups, true)) {
                return false;
            }
        }

        if ($filters['state'] !== '') {
            if ($filters['state'] === 'degraded') {
                if (($branch['link_health'] ?? '') !== 'degraded') {
                    return false;
                }
            } else {
                $current = (string) ($state['state'] ?? BranchAvailabilityStateModel::STATE_UNKNOWN);

                if ($current !== $filters['state']) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @return list<array{group:string, n:int}> */
    private function groupOptions(): array
    {
        $counts = [];

        foreach ($this->branches() as $branch) {
            foreach (explode(',', (string) ($branch['groups'] ?? '')) as $group) {
                $group = trim($group);

                if ($group !== '') {
                    $counts[$group] = ($counts[$group] ?? 0) + 1;
                }
            }
        }

        ksort($counts);

        $options = [];

        foreach ($counts as $group => $count) {
            $options[] = ['group' => (string) $group, 'n' => (int) $count];
        }

        return $options;
    }

    private function locationName(int $locationId): string
    {
        if ($locationId <= 0) {
            return '';
        }

        static $cache = [];

        if (array_key_exists($locationId, $cache)) {
            return $cache[$locationId];
        }

        try {
            $row = $this->db->table('pf_locations')->select('name')->where('id', $locationId)->get()->getRowArray();
            $cache[$locationId] = (string) ($row['name'] ?? '');
        } catch (\Throwable $e) {
            $cache[$locationId] = '';
        }

        return $cache[$locationId];
    }

    /** Digits, or a list of them separated by anything that is not a digit. */
    private static function ports(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $ports = [];

        foreach (preg_split('/[^0-9]+/', (string) $value) ?: [] as $part) {
            $port = (int) $part;

            if ($port >= 1 && $port <= 65535 && !in_array($port, $ports, true)) {
                $ports[] = $port;
            }
        }

        return $ports;
    }

    private static function parseTime(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $time = strtotime($value);

        return $time === false ? null : $time;
    }

    /** A date-ish filter value, or empty: an unparseable date must not silently become "now". */
    private static function dateish(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return self::parseTime($value) === null ? '' : $value;
    }

    /**
     * Prunes aged closed outages so the database stays compact and strictly bounded.
     * Open outages (ended_at NULL) are never removed.
     *
     * @param int $days Number of days to retain (default 90 days)
     * @return int Number of deleted event rows
     */
    public function pruneEvents(int $days = self::RETENTION_DAYS): int
    {
        try {
            $days   = max(7, min(365, $days));
            $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

            $builder = $this->db->table('branch_availability_events')
                ->where('ended_at IS NOT NULL')
                ->where('ended_at <', $cutoff);

            $count = $builder->countAllResults(false);

            if ($count > 0) {
                $builder->delete();
                log_message('info', sprintf('Branch availability: pruned %d closed event(s) older than %d days (%s).', $count, $days, $cutoff));
            }

            // Clean up any orphan state rows whose pf_id no longer exists in pf_inventory
            try {
                $validDevices = $this->inventory->select('id')->findAll();
                $validIds = array_filter(array_column($validDevices, 'id'));
                if (!empty($validIds)) {
                    $this->db->table('branch_availability_state')
                        ->whereNotIn('pf_id', $validIds)
                        ->delete();
                }
            } catch (\Throwable $ignored) {}

            return $count;
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability events prune failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Storage footprint diagnostics to verify database stays compact.
     *
     * @return array{state_rows:int, event_rows:int, open_events:int, closed_events:int, retention_days:int, oldest_event:?string, size_estimate_kb:int}
     */
    public function dbStats(): array
    {
        $stateCount = 0;
        $eventCount = 0;
        $openCount  = 0;
        $oldestDate = null;

        try {
            $stateCount = $this->db->table('branch_availability_state')->countAllResults();
            $eventCount = $this->db->table('branch_availability_events')->countAllResults();
            $openCount  = $this->db->table('branch_availability_events')->where('ended_at', null)->countAllResults();

            $oldest = $this->db->table('branch_availability_events')->select('started_at')->orderBy('started_at', 'ASC')->limit(1)->get()->getRowArray();
            $oldestDate = $oldest['started_at'] ?? null;
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability DB stats read failed: ' . $e->getMessage());
        }

        // Each state row ~ 300 bytes, each event row ~ 350 bytes
        $sizeKb = (int) round((($stateCount * 300) + ($eventCount * 350)) / 1024);

        return [
            'state_rows'       => $stateCount,
            'event_rows'       => $eventCount,
            'open_events'      => $openCount,
            'closed_events'    => max(0, $eventCount - $openCount),
            'retention_days'   => self::RETENTION_DAYS,
            'oldest_event'     => $oldestDate,
            'size_estimate_kb' => max(1, $sizeKb),
        ];
    }
}
