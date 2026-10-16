<?php

namespace App\Services\ConfigSearch;

use App\Models\ConfigIndexStateModel;
use CodeIgniter\Database\BaseBuilder;

/**
 * Searching the fleet's stored configurations.
 *
 * The questions this exists to answer are the ones that used to mean opening devices one at a time:
 * *which firewalls still point at 10.20.0.1*, *who is still using the old SNMP community*, *which sites
 * have a rule nobody remembers adding*. So the defaults are chosen for those:
 *
 *  - a **contains** match over the value, the label and the XML path, because people search for
 *    `10.20.0` and for `description` as well as for a whole value;
 *  - **exact matches first**, since "which device has exactly this IP" is the common case and it should
 *    not be buried under every address that merely contains the prefix;
 *  - and a **result that says how old it is**. The answer came from a snapshot, not from the device, and
 *    a snapshot has a capture time — a search result without one would be read as live truth.
 *
 * Credentials are searchable (finding every device sharing a leaked key is the point) but masked on
 * screen and in the CSV unless somebody explicitly asks to reveal them, which the controller logs.
 */
class ConfigSearchService
{
    /** One row per fact; 50 keeps a page answerable without hiding how much matched. */
    public const PER_PAGE = 50;

    /** Ceiling on an export, so a broad query cannot build a gigabyte in memory. */
    public const MAX_EXPORT = 20000;

    /** Longer than any value worth typing, and it keeps a hostile query out of the SQL layer. */
    public const MAX_QUERY_LENGTH = 120;

    /** How many coverage problems the page names before it says "and N more". */
    public const MAX_PROBLEMS = 12;

    protected \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * The filters, clamped to something safe to run.
     *
     * @param array<string, mixed> $input
     *
     * @return array{q:string, section:string, device:string, exact:bool, reveal:bool}
     */
    public static function normalizeFilters(array $input): array
    {
        $query = (string) ($input['q'] ?? '');
        $query = preg_replace('/\s+/u', ' ', $query) ?? $query;

        return [
            'q'       => mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH),
            'section' => strtolower(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($input['section'] ?? '')) ?? ''),
            'device'  => mb_substr(trim((string) ($input['device'] ?? '')), 0, 80),
            'exact'   => self::truthy($input['exact'] ?? null),
            'reveal'  => self::truthy($input['reveal'] ?? null),
        ];
    }

    /**
     * The hits for a page, grouped by device.
     *
     * @param array{q:string, section:string, device:string, exact:bool, reveal:bool} $filters
     *
     * @return array<string, mixed>
     */
    public function search(array $filters, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $term    = ConfigIndexer::normalizeValue($filters['q']);
        $norm    = mb_substr($term, 0, 191);

        $empty = [
            'filters'      => $filters,
            'q'            => $filters['q'],
            'matches'      => 0,
            'device_count' => 0,
            'total'        => 0,
            'page'         => $page,
            'pages'        => 1,
            'per_page'     => $perPage,
            'groups'       => [],
            'sections'     => [],
            'coverage'     => $this->coverage(),
            'examples'     => self::examples(),
        ];

        if ($term === '') {
            return $empty;
        }

        $builder = $this->match($filters, $term);
        $total   = $this->countMatches($filters, $term);

        $orderHash = sha1($norm);

        $rows = $builder
            ->select('pf_id, device_name, section, xml_path, label, value_text, value_norm, is_secret, captured_at')
            ->orderBy('CASE WHEN value_hash = ' . $this->db->escape($orderHash) . ' THEN 0 ELSE 1 END', '', false)
            ->orderBy('device_name', 'ASC')
            ->orderBy('section', 'ASC')
            ->orderBy('xml_path', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        if ($rows === []) {
            return array_merge($empty, ['total' => $total, 'pages' => max(1, (int) ceil($total / $perPage))]);
        }

        $groups = $this->group($rows, $filters);
        $pages  = max(1, (int) ceil($total / $perPage));

        return [
            'filters'      => $filters,
            'q'            => $filters['q'],
            'matches'      => $total,
            'device_count' => count($groups),
            'total'        => $total,
            'page'         => $page,
            'pages'        => $pages,
            'per_page'     => $perPage,
            'groups'       => $groups,
            'sections'     => $this->sectionOptions(),
            'coverage'     => $this->coverage(),
            'examples'     => self::examples(),
        ];
    }

    /**
     * The same hits as a flat list, for the CSV.
     *
     * @param array{q:string, section:string, device:string, exact:bool, reveal:bool} $filters
     *
     * @return array{rows:list<array<string, mixed>>, total:int, capped:bool}
     */
    public function exportRows(array $filters): array
    {
        $term = ConfigIndexer::normalizeValue($filters['q']);

        if ($term === '') {
            return ['rows' => [], 'total' => 0, 'capped' => false];
        }

        $total = $this->countMatches($filters, $term);

        $rows = $this->match($filters, $term)
            ->select('pf_id, device_name, section, xml_path, label, value_text, is_secret, captured_at')
            ->orderBy('device_name', 'ASC')
            ->orderBy('section', 'ASC')
            ->orderBy('xml_path', 'ASC')
            ->limit(self::MAX_EXPORT)
            ->get()
            ->getResultArray();

        return ['rows' => $rows, 'total' => $total, 'capped' => $total > self::MAX_EXPORT];
    }

    /**
     * The export as CSV text. One row per fact, with the section named the way the page names it.
     *
     * Accepts either shape — an export row straight from the database (`value_text`) or a grouped hit
     * from the page (`value`, already masked or revealed). Both are legitimate inputs, and a Value column
     * that silently came out empty because the caller passed the other shape is exactly the sort of quiet
     * wrongness an export must not have.
     */
    public static function csv(array $rows, bool $reveal = false): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Device', 'Section', 'Path', 'Field', 'Value', 'Credential', 'Captured at']);

        foreach ($rows as $row) {
            $secret = (int) ($row['is_secret'] ?? 0) === 1;
            $value  = array_key_exists('value_text', $row)
                ? (string) $row['value_text']
                : (string) ($row['value'] ?? '');

            if (!$reveal && ($secret || ($row['masked'] ?? false) === true)) {
                $value = '•••••• (hidden)';
            }

            fputcsv($handle, [
                (string) ($row['device_name'] ?? ''),
                ConfigIndexer::sectionLabel((string) ($row['section'] ?? '')),
                (string) ($row['xml_path'] ?? ''),
                (string) ($row['label'] ?? ''),
                $value,
                $secret ? 'yes' : '',
                (string) ($row['captured_at'] ?? ''),
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    public static function filename(): string
    {
        return 'config-search-' . date('Ymd-His') . '.csv';
    }

    /**
     * The sections the index actually contains, with counts, for the filter dropdown.
     *
     * @return list<array{section:string, label:string, n:int}>
     */
    public function sectionOptions(): array
    {
        try {
            $rows = $this->db->table('config_index')
                ->select('section, COUNT(*) AS n')
                ->groupBy('section')
                ->orderBy('n', 'DESC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Config search section list failed: ' . $e->getMessage());

            return [];
        }

        $options = [];

        foreach ($rows as $row) {
            $section = (string) $row['section'];

            $options[] = [
                'section' => $section,
                'label'   => ConfigIndexer::sectionLabel($section),
                'n'       => (int) $row['n'],
            ];
        }

        return $options;
    }

    /**
     * What the index covers, for the honesty strip at the top of the page.
     *
     * @return array<string, mixed>
     */
    public function coverage(): array
    {
        return (new ConfigIndexer())->coverage();
    }

    /**
     * Questions worth typing, shown when the page is empty.
     *
     * @return list<array{q:string, why:string}>
     */
    public static function examples(): array
    {
        return [
            ['q' => '10.20.0',      'why' => 'find every device that mentions a subnet'],
            ['q' => 'rocommunity',  'why' => 'SNMP settings, by the element that holds them'],
            ['q' => 'Description',  'why' => 'anything with a description (rules, aliases, interfaces)'],
            ['q' => 'allow',         'why' => 'firewall and NAT rules by action'],
        ];
    }

    // ------------------------------------------------------------------ query building

    /**
     * One builder for the filter set, so the page, the count and the export cannot disagree.
     *
     * @param array{q:string, section:string, device:string, exact:bool, reveal:bool} $filters
     */
    private function match(array $filters, string $term): BaseBuilder
    {
        $builder = $this->db->table('config_index');
        $norm    = mb_substr($term, 0, 191);

        if ($filters['exact']) {
            // An equality against the indexed hash: the fast path for "who has *exactly* this".
            $builder->groupStart()
                ->where('value_hash', sha1($norm))
                ->orWhere('value_norm', $norm)
                ->groupEnd();
        } else {
            // Case-insensitive contains over the value, its label and its path: people search for a
            // subnet, for "description", and for "snmp" without caring which of the three they meant.
            //
            // `escapeLikeString()` is what makes the query literal: the builder binds and quotes the
            // value but leaves LIKE wildcards in it alone, so without this a search for `50%` becomes
            // "anything containing 50" and `traffic_at` quietly matches "traffic at" — a search page
            // that answers a different question than the one asked.
            $like = $this->db->escapeLikeString($term);

            $builder->groupStart()
                ->like('value_norm', $like, 'both', null, true)
                ->orLike('label', $like, 'both', null, true)
                ->orLike('xml_path', $like, 'both', null, true)
                ->groupEnd();
        }

        if ($filters['section'] !== '') {
            $builder->where('section', $filters['section']);
        }

        if ($filters['device'] !== '') {
            $builder->groupStart()
                ->like('device_name', $this->db->escapeLikeString($filters['device']), 'both', null, true);

            if (ctype_digit($filters['device'])) {
                $builder->orWhere('pf_id', (int) $filters['device']);
            }

            $builder->groupEnd();
        }

        return $builder;
    }

    /**
     * @param array{q:string, section:string, device:string, exact:bool, reveal:bool} $filters
     */
    private function countMatches(array $filters, string $term): int
    {
        try {
            return (int) $this->match($filters, $term)->countAllResults();
        } catch (\Throwable $e) {
            log_message('error', 'Config search count failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Groups hits by device and attaches the two things a result needs to be interpretable: the device's
     * management address, and which snapshot the answer came from.
     *
     * @param list<array<string, mixed>> $rows
     * @param array{q:string, section:string, device:string, exact:bool, reveal:bool} $filters
     *
     * @return list<array<string, mixed>>
     */
    private function group(array $rows, array $filters): array
    {
        $pfIds = [];

        foreach ($rows as $row) {
            $pfIds[(int) $row['pf_id']] = (int) $row['pf_id'];
        }

        $statuses = [];

        try {
            foreach ($this->db->table('pf_status')->select('pf_id, ip')->whereIn('pf_id', array_values($pfIds))->get()->getResultArray() as $row) {
                $statuses[(int) $row['pf_id']] = (string) ($row['ip'] ?? '');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Config search status read failed: ' . $e->getMessage());
        }

        $files = [];

        try {
            foreach ($this->db->table('config_index_state')->select('pf_id, file_name, status')->whereIn('pf_id', array_values($pfIds))->get()->getResultArray() as $row) {
                $files[(int) $row['pf_id']] = $row;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Config search state read failed: ' . $e->getMessage());
        }

        $groups = [];

        foreach ($rows as $row) {
            $pfId = (int) $row['pf_id'];
            $secret = (int) ($row['is_secret'] ?? 0) === 1;

            if (!isset($groups[$pfId])) {
                $state = $files[$pfId] ?? [];

                $groups[$pfId] = [
                    'pf_id'       => $pfId,
                    'device'      => (string) $row['device_name'],
                    'device_ip'   => $statuses[$pfId] ?? '',
                    'snapshot'    => (string) ($state['file_name'] ?? ''),
                    'captured_at' => (string) ($row['captured_at'] ?? ''),
                    'hits'        => [],
                ];
            }

            $groups[$pfId]['hits'][] = [
                'section'      => (string) $row['section'],
                'section_name' => ConfigIndexer::sectionLabel((string) $row['section']),
                'path'         => (string) $row['xml_path'],
                'label'        => (string) $row['label'],
                'value'        => $secret && !$filters['reveal'] ? '' : (string) $row['value_text'],
                'is_secret'    => $secret,
                'masked'       => $secret && !$filters['reveal'],
            ];
        }

        return array_values($groups);
    }

    private static function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
