<?php

namespace App\Services\ConfigPush;

use App\Models\ConfigAlertModel;
use App\Models\JobTargetModel;
use CodeIgniter\Database\RawSql;

/**
 * The fleet-wide answer to "what did we change, and is it still true?"
 *
 * The job dashboard is organised by *job*: what was queued, what is running, what failed. That is the
 * wrong shape for the question an auditor, a handover or a Monday-morning reviewer actually asks —
 * "which devices are running something we changed, when was each one last confirmed, and what has
 * drifted?" — which is per *device and change*, and spans every job ever applied. So this is its own
 * page over its own query rather than another tab on that dashboard.
 *
 * Three deliberate choices:
 *
 *   - **One row per applied device/change**, not per job. A job that touched 39 branches is 39 rows,
 *     because each branch is separately confirmed or separately drifted, and a per-job view hides the
 *     one branch that reverted.
 *   - **"Last confirmed" is never inferred.** A row carries the verdict *and* when it was last read,
 *     so "confirmed yesterday", "checked but unreachable", and "never checked" stay three different
 *     things. Collapsing them is how a report starts lying.
 *   - **The CSV is the same query as the screen.** Export takes the identical filters and the identical
 *     row shape, so a report handed to somebody else cannot disagree with the page it came from.
 */
class ConfigVerificationReportService
{
    /** Rows per page on screen. The export is not paged; it is capped instead. */
    public const PER_PAGE = 50;

    /**
     * Most rows one CSV export will produce.
     *
     * A whole-fleet export is the point of the feature, so this is a guard against a runaway query and
     * an email-sized attachment, not a policy. When the filtered set exceeds it the page says so
     * before the download, rather than silently handing back a partial file.
     */
    public const MAX_EXPORT = 20000;

    /**
     * How old a confirmation may be before the row is flagged on screen.
     *
     * "Last confirmed" without an expiry is a number nobody acts on: a device confirmed eleven months
     * ago and one confirmed this morning look equally fine. Making staleness explicit is what turns the
     * column into a work queue.
     */
    public const STALE_DAYS = 30;

    public const SCOPES          = ['applied', 'all'];
    public const ALERT_FILTERS   = ['open', 'resolved', 'none'];

    public const VERIFY_STATES = [
        JobTargetModel::VERIFY_MATCHED,
        JobTargetModel::VERIFY_DRIFTED,
        JobTargetModel::VERIFY_UNAVAILABLE,
        JobTargetModel::VERIFY_PENDING,
        JobTargetModel::VERIFY_NOT_APPLICABLE,
    ];

    /** Columns the row query needs, and nothing else. */
    private const ROW_COLUMNS = 'job_targets.id, job_targets.job_id, job_targets.pf_id, job_targets.status,'
        . ' job_targets.is_canary, job_targets.applied_at, job_targets.verified_at,'
        . ' job_targets.verify_status, job_targets.verify_detail, job_targets.verify_attempts,'
        . ' job_targets.backup_id, job_targets.backup_file,'
        . ' config_jobs.batch_id, config_jobs.payload_type, config_jobs.payload_mode,'
        . ' config_jobs.requested_by, config_jobs.reconcile_of_job_id,'
        . ' pf_inventory.name AS device_name, pf_inventory.serial, pf_inventory.man_ip AS device_man_ip,'
        . ' pf_inventory.groups AS device_group,'
        . self::ADDRESS_SQL . ' AS device_ip';

    /**
     * A device's address, resolved the way the push itself resolves it.
     *
     * `man_ip` is the configured management address, but on this fleet it is empty for most branches —
     * the address is learnt by the status poller and lands in `pf_status.ip`. SshTransport already falls
     * back to it, so a report that showed only `man_ip` would print an address for two devices out of
     * thirty-nine and blank for the rest, while the pusher quietly reached all of them.
     *
     * A correlated subquery rather than a join on purpose: a join would multiply rows the day a device
     * has two status rows, and this report's central promise is one row per device per change.
     */
    private const ADDRESS_SQL = "COALESCE(NULLIF(pf_inventory.man_ip, ''),"
        . ' (SELECT s.ip FROM pf_status s WHERE s.pf_id = pf_inventory.id ORDER BY s.id DESC LIMIT 1))';

    // ------------------------------------------------------------------------ filters

    /**
     * Cleans whatever the browser sent into a filter set the query can trust.
     *
     * Pure and total: every key is present in the result whatever was passed in, unknown states are
     * dropped rather than reaching SQL, dates must be real dates, and a backwards date range is swapped
     * because an operator who typed the end before the start meant the range either way round.
     *
     * @param array<string, mixed> $input
     *
     * @return array{scope:string, device:string, group:string, job:string, state:string, alert:string, from:string, to:string}
     */
    public static function normalizeFilters(array $input): array
    {
        $text = static function ($value, int $max = 100): string {
            $value = trim((string) (is_scalar($value) ? $value : ''));

            return mb_substr($value, 0, $max);
        };

        $date = static function ($value): string {
            $value = trim((string) (is_scalar($value) ? $value : ''));

            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
                return '';
            }

            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : '';
        };

        $scope = $text($input['scope'] ?? '', 10);
        $state = $text($input['state'] ?? '', 20);
        $alert = $text($input['alert'] ?? '', 10);

        $from = $date($input['from'] ?? '');
        $to   = $date($input['to'] ?? '');

        if ($from !== '' && $to !== '' && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'scope'  => in_array($scope, self::SCOPES, true) ? $scope : 'applied',
            'device' => $text($input['device'] ?? ''),
            'group'  => $text($input['group'] ?? ''),
            'job'    => $text($input['job'] ?? '', 64),
            'state'  => in_array($state, self::VERIFY_STATES, true) ? $state : '',
            'alert'  => in_array($alert, self::ALERT_FILTERS, true) ? $alert : '',
            'from'   => $from,
            'to'     => $to,
        ];
    }

    /**
     * The query every reader shares: the same filters, the same joins.
     *
     * @param array<string, mixed> $filters a normalised filter set
     * @param string               $select  overrides the row columns for the aggregate reads
     *
     * @return \CodeIgniter\Database\BaseBuilder
     */
    private function query(array $filters, ?string $select = null)
    {
        $builder = db_connect()->table('job_targets')
            ->select($select ?? self::ROW_COLUMNS)
            ->join('config_jobs', 'config_jobs.id = job_targets.job_id', 'left')
            ->join('pf_inventory', 'pf_inventory.id = job_targets.pf_id', 'left');

        // The report is about changes that were actually installed. `all` exists because an auditor
        // eventually asks for every attempt, including the ones that failed — but it is not the
        // default, because a failed push is the job dashboard's business and it would drown this page.
        if ($filters['scope'] === 'applied') {
            $builder->where('job_targets.status', JobTargetModel::STATUS_APPLIED);
        }

        if ($filters['device'] !== '') {
            // The handles an operator has for a branch: its name, its address — configured or learnt —
            // and its serial. The address is searched as the resolved value, because on this fleet the
            // learnt one is usually the only one there is.
            $address = db_connect()->escape(db_connect()->escapeLikeString($filters['device']));

            $builder->groupStart()
                ->like('pf_inventory.name', $filters['device'])
                ->orLike('pf_inventory.serial', $filters['device'])
                ->orWhere(new RawSql(self::ADDRESS_SQL . ' LIKE ' . $address))
                ->groupEnd();
        }

        if ($filters['group'] !== '') {
            $builder->where('pf_inventory.groups', $filters['group']);
        }

        if ($filters['job'] !== '') {
            $builder->groupStart()->like('config_jobs.batch_id', $filters['job'], 'after');

            if (ctype_digit($filters['job'])) {
                $builder->orWhere('config_jobs.id', (int) $filters['job']);
            }

            $builder->groupEnd();
        }

        if ($filters['state'] !== '') {
            $builder->where('job_targets.verify_status', $filters['state']);
        }

        // Alert filters are written as EXISTS rather than a join on purpose: a target can have a
        // resolved drift and a newer open one, and joining would multiply the row while a subquery
        // keeps "this device has something unhandled" a property of the row.
        if ($filters['alert'] === 'open') {
            $builder->where(new RawSql(self::openAlertSql()));
        } elseif ($filters['alert'] === 'resolved') {
            // Has drifted at some point, and nothing about it is open now: the drift was cleared,
            // whether by a later read agreeing or by an operator dismissing it.
            $builder->where(new RawSql(self::anyAlertSql()))
                ->where(new RawSql('NOT ' . self::openAlertSql()));
        } elseif ($filters['alert'] === 'none') {
            $builder->where(new RawSql('NOT ' . self::anyAlertSql()));
        }

        // Applied-date range, not read-date: the question is "what did we change in this window",
        // which is how an auditor asks it. `applied_at` is set when the device confirmed the write.
        if ($filters['from'] !== '') {
            $builder->where('job_targets.applied_at >=', $filters['from'] . ' 00:00:00');
        }

        if ($filters['to'] !== '') {
            $builder->where('job_targets.applied_at <=', $filters['to'] . ' 23:59:59');
        }

        return $builder;
    }

    private static function openAlertSql(): string
    {
        return "EXISTS (SELECT 1 FROM config_alerts a WHERE a.target_id = job_targets.id"
            . " AND a.status = '" . ConfigAlertModel::STATUS_OPEN . "')";
    }

    private static function anyAlertSql(): string
    {
        return 'EXISTS (SELECT 1 FROM config_alerts a WHERE a.target_id = job_targets.id)';
    }

    // ------------------------------------------------------------------------ reads

    /** How many rows the filters select, for paging and for the export cap. */
    public function countRows(array $filters): int
    {
        try {
            return $this->query($filters, 'COUNT(*) AS total')->get()->getRowArray()['total'] ?? 0;
        } catch (\Throwable $e) {
            log_message('error', 'Verification report count failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * One page of the report, already shaped for the table.
     *
     * @return array{rows:list<array>, total:int, page:int, per_page:int, pages:int, filters:array, summary:array, export_capped:bool}
     */
    public function page(array $filters, int $page = 1): array
    {
        $page   = max(1, $page);
        $total  = $this->countRows($filters);
        $pages  = $total === 0 ? 1 : (int) ceil($total / self::PER_PAGE);
        $page   = min($page, $pages);
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = [];

        try {
            $rows = $this->fetch($filters, self::PER_PAGE, $offset);
        } catch (\Throwable $e) {
            log_message('error', 'Verification report query failed: ' . $e->getMessage());
        }

        return [
            'rows'          => $rows,
            'total'         => $total,
            'page'          => $page,
            'per_page'      => self::PER_PAGE,
            'pages'         => $pages,
            'filters'       => $filters,
            'summary'       => $this->summary($filters),
            'export_capped' => $total > self::MAX_EXPORT,
        ];
    }

    /**
     * The rows behind the export, newest first, capped.
     *
     * @return array{rows:list<array>, total:int, truncated:bool}
     */
    public function exportRows(array $filters): array
    {
        $total = $this->countRows($filters);

        try {
            $rows = $this->fetch($filters, self::MAX_EXPORT, 0);
        } catch (\Throwable $e) {
            log_message('error', 'Verification report export failed: ' . $e->getMessage());

            return ['rows' => [], 'total' => $total, 'truncated' => false];
        }

        return ['rows' => $rows, 'total' => $total, 'truncated' => $total > self::MAX_EXPORT];
    }

    /**
     * @return list<array>
     */
    private function fetch(array $filters, int $limit, int $offset): array
    {
        $raw = $this->query($filters)
            ->orderBy('job_targets.applied_at', 'DESC')
            ->orderBy('job_targets.id', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();

        $alerts = $this->alertsFor(array_map(static fn (array $row): int => (int) $row['id'], $raw));

        return array_map(static fn (array $row): array => self::shape($row, $alerts[(int) $row['id']] ?? null), $raw);
    }

    /**
     * Latest alert per target, in one query for the whole page.
     *
     * A target accumulates alerts over time — drift, resolved, drifted again — and the newest one is
     * what the row should say. Read as a map so the table does not need one query per row.
     *
     * @param list<int> $targetIds
     *
     * @return array<int, array>
     */
    private function alertsFor(array $targetIds): array
    {
        $ids = array_values(array_filter($targetIds, static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return [];
        }

        try {
            $rows = db_connect()->table('config_alerts')
                ->select('target_id, status, summary, raised_at, notified_at, resolved_at, resolved_by, resolution')
                ->whereIn('target_id', $ids)
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Verification report alert read failed: ' . $e->getMessage());

            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            // Ascending, so the last one written for a target wins: the newest alert.
            $map[(int) $row['target_id']] = $row;
        }

        return $map;
    }

    /**
     * The counts above the table, over the filtered set rather than the page.
     *
     * A tile that counted the current page would answer "did anything drift on page 3", which is not
     * the question; these are about the slice of the fleet the filters describe.
     *
     * @return array{scope:string, total:int, matched:int, drifted:int, unconfirmed:int, never_checked:int, stale:int, not_applicable:int, devices:int, open_alerts:int, oldest_change:?string, newest_change:?string}
     */
    public function summary(array $filters): array
    {
        $summary = [
            'scope'          => $filters['scope'],
            'total'          => 0,
            'matched'        => 0,
            'drifted'        => 0,
            'unconfirmed'    => 0,
            'never_checked'  => 0,
            'stale'          => 0,
            'not_applicable' => 0,
            'devices'        => 0,
            'open_alerts'    => 0,
            'oldest_change'  => null,
            'newest_change'  => null,
        ];

        try {
            $select = 'COUNT(*) AS total,'
                . " SUM(job_targets.verify_status = '" . JobTargetModel::VERIFY_MATCHED . "') AS matched,"
                . " SUM(job_targets.verify_status = '" . JobTargetModel::VERIFY_DRIFTED . "') AS drifted,"
                . " SUM(job_targets.verify_status IN ('" . JobTargetModel::VERIFY_PENDING . "', '" . JobTargetModel::VERIFY_UNAVAILABLE . "')) AS unconfirmed,"
                . ' SUM(job_targets.verified_at IS NULL) AS never_checked,'
                . " SUM(job_targets.verify_status = '" . JobTargetModel::VERIFY_NOT_APPLICABLE . "') AS not_applicable,"
                . ' COUNT(DISTINCT job_targets.pf_id) AS devices,'
                . " SUM(job_targets.verify_status IN ('" . JobTargetModel::VERIFY_MATCHED . "', '" . JobTargetModel::VERIFY_DRIFTED . "')"
                . " AND job_targets.verified_at IS NOT NULL AND job_targets.verified_at < '" . $this->staleBefore() . "') AS stale,"
                . ' MIN(job_targets.applied_at) AS oldest_change, MAX(job_targets.applied_at) AS newest_change';

            $row = $this->query($filters, $select)->get()->getRowArray();

            foreach (array_keys($summary) as $key) {
                if (array_key_exists($key, $row) && $key !== 'scope') {
                    $summary[$key] = $key === 'oldest_change' || $key === 'newest_change'
                        ? ($row[$key] !== null ? (string) $row[$key] : null)
                        : (int) $row[$key];
                }
            }

            // Counted separately because it is a different table's question: "how many rows here have
            // something unhandled", and a joined count would multiply rows for targets with history.
            $summary['open_alerts'] = (int) ($this->query($filters, 'COUNT(*) AS total')
                ->where(new RawSql(self::openAlertSql()))
                ->get()
                ->getRowArray()['total'] ?? 0);
        } catch (\Throwable $e) {
            log_message('error', 'Verification report summary failed: ' . $e->getMessage());
        }

        return $summary;
    }

    /** The timestamp a confirmation must be newer than to count as current. */
    public function staleBefore(): string
    {
        return date('Y-m-d H:i:s', time() - (self::STALE_DAYS * 86400));
    }

    /**
     * Distinct device groups, for the filter dropdown.
     *
     * Read from the inventory rather than from the rows, so a group with nothing applied is still
     * selectable — "show me this group, which turns out to have no changes" is a legitimate answer.
     *
     * @return list<string>
     */
    public function filterOptions(): array
    {
        try {
            $rows = db_connect()->query('SELECT DISTINCT `groups` AS g FROM pf_inventory WHERE `groups` IS NOT NULL AND `groups` <> \'\' ORDER BY `groups` ASC')
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Verification report group list failed: ' . $e->getMessage());

            return [];
        }

        return array_values(array_filter(array_map(static fn (array $r): string => (string) $r['g'], $rows)));
    }

    // ------------------------------------------------------------------------ shaping

    /**
     * One raw join row into the shape the table, the JSON endpoint and the CSV all read.
     *
     * @return array<string, mixed>
     */
    public static function shape(array $row, ?array $alert = null): array
    {
        $verify = $row['verify_status'] !== null ? (string) $row['verify_status'] : JobTargetModel::VERIFY_PENDING;
        $label  = self::verdict($verify);
        $now    = time();

        return [
            'target_id'      => (int) $row['id'],
            'job_id'         => (int) $row['job_id'],
            'pf_id'          => (int) $row['pf_id'],
            'device'         => (string) ($row['device_name'] ?? ('device #' . $row['pf_id'])),
            'group'          => (string) ($row['device_group'] ?? ''),
            'ip'             => (string) ($row['device_ip'] ?? ''),
            // Whether that address is the one somebody configured, or the one the device reported.
            // Worth saying: a learnt address can change under you, a configured one cannot.
            'ip_source'      => trim((string) ($row['device_man_ip'] ?? '')) !== '' ? 'configured' : (trim((string) ($row['device_ip'] ?? '')) !== '' ? 'reported' : ''),
            'serial'         => (string) ($row['serial'] ?? ''),
            'batch_id'       => (string) ($row['batch_id'] ?? ''),
            'payload_type'   => (string) ($row['payload_type'] ?? 'xml'),
            'payload_mode'   => (string) ($row['payload_mode'] ?? 'replace'),
            'payload_label'  => self::payloadLabel((string) ($row['payload_type'] ?? 'xml'), (string) ($row['payload_mode'] ?? 'replace')),
            'is_canary'      => (int) ($row['is_canary'] ?? 0) === 1,
            'status'         => (string) ($row['status'] ?? ''),
            'applied_at'     => self::stamp($row['applied_at'] ?? null),
            'applied_age'    => self::ageInDays($row['applied_at'] ?? null, $now),
            'verify_status'  => $verify,
            'verdict'        => $label['label'],
            'verdict_class'  => $label['class'],
            'verified_at'    => self::stamp($row['verified_at'] ?? null),
            'checked_age'    => self::ageInDays($row['verified_at'] ?? null, $now),
            'verify_attempts' => (int) ($row['verify_attempts'] ?? 0),
            'detail'         => (string) ($row['verify_detail'] ?? ''),
            'reconcile_of'   => isset($row['reconcile_of_job_id']) && $row['reconcile_of_job_id'] !== null ? (int) $row['reconcile_of_job_id'] : null,
            'stale'          => self::isStale($verify, $row['verified_at'] ?? null, $now),
            'alert'          => $alert === null ? null : [
                'status'      => (string) ($alert['status'] ?? ''),
                'summary'     => (string) ($alert['summary'] ?? ''),
                'raised_at'   => (string) ($alert['raised_at'] ?? ''),
                'notified_at' => self::stamp($alert['notified_at'] ?? null),
                'resolved_by' => (string) ($alert['resolved_by'] ?? ''),
                'resolution'  => (string) ($alert['resolution'] ?? ''),
                'resolved_at' => self::stamp($alert['resolved_at'] ?? null),
            ],
        ];
    }

    /**
     * What the verdict is called on screen, and how loudly.
     *
     * Deliberately separate from the raw state: "pending" is "not confirmed yet" to a reader, and
     * "unavailable" must never be shortened to anything that sounds like a pass.
     *
     * @return array{label:string, class:string}
     */
    public static function verdict(string $verify): array
    {
        return match ($verify) {
            JobTargetModel::VERIFY_MATCHED        => ['label' => 'Confirmed', 'class' => 'ok'],
            JobTargetModel::VERIFY_DRIFTED        => ['label' => 'Drifted', 'class' => 'bad'],
            JobTargetModel::VERIFY_UNAVAILABLE    => ['label' => 'Could not confirm', 'class' => 'warn'],
            JobTargetModel::VERIFY_NOT_APPLICABLE => ['label' => 'Not applicable', 'class' => 'muted'],
            default                               => ['label' => 'Not checked yet', 'class' => 'warn'],
        };
    }

    public static function payloadLabel(string $type, string $mode): string
    {
        if ($type !== 'xml') {
            return $type . ' payload';
        }

        return $mode === 'merge' ? 'config fragment (merge)' : 'full config (replace)';
    }

    /** Whole days since a timestamp, or null when there is no timestamp to measure. */
    public static function ageInDays(?string $when, ?int $now = null): ?int
    {
        if ($when === null || trim($when) === '') {
            return null;
        }

        $ts = strtotime($when);

        if ($ts === false) {
            return null;
        }

        return max(0, (int) floor((($now ?? time()) - $ts) / 86400));
    }

    /**
     * Whether a confirmation is old enough to be worth acting on.
     *
     * Only verdicts count. A device nobody ever read is 'unconfirmed' — already flagged — and calling
     * it stale as well would double-count the same problem; a script has nothing to confirm, so it can
     * never be stale. This flag means one thing: *this was confirmed, and that was a long time ago*.
     */
    public static function isStale(string $verify, ?string $verifiedAt, ?int $now = null): bool
    {
        if (!in_array($verify, [JobTargetModel::VERIFY_MATCHED, JobTargetModel::VERIFY_DRIFTED], true)) {
            return false;
        }

        $age = self::ageInDays($verifiedAt, $now);

        return $age !== null && $age >= self::STALE_DAYS;
    }

    private static function stamp($value): ?string
    {
        if ($value === null || (string) $value === '') {
            return null;
        }

        return (string) $value;
    }

    // ------------------------------------------------------------------------ export

    /**
     * The CSV document for a filtered set, header included.
     *
     * Pure: it takes the same shaped rows the screen renders and can be asserted on directly, which is
     * the only way to test an export without writing a file.
     *
     * @param list<array> $rows
     */
    public static function csv(array $rows): string
    {
        $lines = [self::csvLine(array_keys(self::csvRow([])))];

        foreach ($rows as $row) {
            $lines[] = self::csvLine(array_values(self::csvRow($row)));
        }

        // A BOM, because the first thing anybody does with this file is open it in Excel, which
        // otherwise reads a device named "1001-MAIN BRANCH" as mojibake in a column of numbers.
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    /**
     * One row's cells, keyed by the column heading.
     *
     * The header line and every data line come from this single definition, so a column can never be
     * added to one and forgotten in the other — which is the classic way an exported report stops
     * lining up with its own headings.
     *
     * @return array<string, string>
     */
    public static function csvRow(array $row): array
    {
        $alert = is_array($row['alert'] ?? null) ? $row['alert'] : null;

        return [
            'Device'            => (string) ($row['device'] ?? ''),
            'Group'             => (string) ($row['group'] ?? ''),
            'Address'           => (string) ($row['ip'] ?? ''),
            'Address source'    => (string) ($row['ip_source'] ?? ''),
            'Serial'            => (string) ($row['serial'] ?? ''),
            'Job'               => isset($row['job_id']) ? '#' . $row['job_id'] : '',
            'Batch'             => (string) ($row['batch_id'] ?? ''),
            'Payload'           => (string) ($row['payload_label'] ?? ''),
            'Canary'            => !empty($row['is_canary']) ? 'yes' : 'no',
            'Target status'     => (string) ($row['status'] ?? ''),
            'Applied at'        => (string) ($row['applied_at'] ?? ''),
            'Last check'        => (string) ($row['verified_at'] ?? ''),
            'Verdict'           => (string) ($row['verdict'] ?? ''),
            'Confirmation age (days)' => isset($row['checked_age']) && $row['checked_age'] !== null ? (string) $row['checked_age'] : '',
            'Stale'             => !empty($row['stale']) ? 'yes' : 'no',
            'Verification detail' => (string) ($row['detail'] ?? ''),
            'Alert'             => $alert === null ? '' : (string) $alert['status'],
            'Alert raised at'   => $alert === null ? '' : (string) $alert['raised_at'],
            'Alert emailed at'  => $alert === null ? '' : (string) $alert['notified_at'],
            'Alert resolved by' => $alert === null ? '' : (string) $alert['resolved_by'],
            'Alert resolution'  => $alert === null ? '' : (string) $alert['resolution'],
            'Alert resolved at' => $alert === null ? '' : (string) $alert['resolved_at'],
        ];
    }

    /**
     * One CSV line.
     *
     * Every cell is quoted and internal quotes are doubled, so a device name containing a comma (they
     * exist: "1001-MAIN BRANCH, AKOLA") cannot shift the columns.
     *
     * @param list<string> $cells
     */
    private static function csvLine(array $cells): string
    {
        return implode(',', array_map(static fn ($cell): string => self::csvCell((string) $cell), $cells));
    }

    /**
     * A single CSV cell, safe to open in a spreadsheet.
     *
     * The leading-quote guard matters: a cell beginning `=`, `+`, `-` or `@` is a *formula* to Excel and
     * Sheets, and a device name or a verification message is not trusted input — one of them starts with
     * `=` and the file becomes an execution vector rather than a report. Such a cell is prefixed with a
     * single quote, which spreadsheets take as "this is text, show it literally".
     */
    public static function csvCell(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        if ($value !== '' && preg_match('/^[=+\-@\t]/', $value) === 1) {
            $value = "'" . $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    /** A dated filename, so two exports from the same day are distinguishable. */
    public static function filename(?int $now = null): string
    {
        return 'config-verification-' . date('Y-m-d-His', $now ?? time()) . '.csv';
    }
}
