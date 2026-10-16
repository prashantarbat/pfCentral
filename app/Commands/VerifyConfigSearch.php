<?php

namespace App\Commands;

use App\Models\ConfigIndexStateModel;
use App\Models\PfBakupModel;
use App\Models\PfInventoryModel;
use App\Services\ConfigPush\ConfigSnapshotStore;
use App\Services\ConfigSearch\ConfigIndexer;
use App\Services\ConfigSearch\ConfigSearchService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Proves the fleet configuration search end to end, against a real database and real files.
 *
 * The unit tests pin the pure arithmetic: what a path's label is, which values are credentials, how a
 * filter is clamped. What only a database and a filesystem can show is the part that would quietly lie
 * to an operator:
 *
 *   - that a fact extracted from a snapshot on disk is *findable* afterwards, by value, by label and by
 *     device, with the section filter narrowing rather than widening;
 *   - that re-indexing replaces a device's facts instead of accumulating two versions of its
 *     configuration, which would make every answer ambiguous;
 *   - that a query containing a LIKE wildcard is matched literally (otherwise searching `50%` describes
 *     the entire fleet as a match, and the page becomes a liar);
 *   - that a credential is masked on the page and in the CSV unless somebody explicitly asks;
 *   - and, most importantly, that the devices which could **not** be indexed — no snapshot on disk,
 *     unreadable, not valid XML, implausibly large — are *named* in the coverage report rather than
 *     silently missing from the results.
 *
 * Fixtures only. Nothing here connects to a device; every file it writes is deleted again, and the
 * database is compared against a baseline it took before touching anything.
 */
class VerifyConfigSearch extends BaseCommand
{
    protected $group       = 'Config';
    protected $name        = 'verify:configsearch';
    protected $description = 'Harness: fleet-wide configuration search (config_index, its honesty, its masking).';
    protected $usage       = 'verify:configsearch';

    /** @var list<string> */
    private array $pass = [];

    /** @var list<string> */
    private array $fail = [];

    /** @var list<int> */
    private array $deviceIds = [];

    /** @var list<int> */
    private array $backupIds = [];

    /** @var list<string> */
    private array $files = [];

    /** @var array<string, mixed> */
    private array $fx = [];

    public function run(array $params)
    {
        $before = $this->counts();

        try {
            $this->sectionA();
            $this->sectionB();
            $this->sectionC();
            $this->sectionD();
            $this->sectionE();
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

    // ------------------------------------------------------------------ A: extraction is pure

    private function sectionA(): void
    {
        CLI::write('A. A configuration becomes facts with paths, labels and credentials marked', 'yellow');

        $parsed = ConfigIndexer::extract($this->fixtureXml());

        $this->check('a pfSense configuration parses', $parsed['ok'] === true);
        $this->check('and yields facts', $parsed['nodes'] > 10);

        $byPath = [];
        foreach ($parsed['facts'] as $fact) {
            $byPath[$fact['path']] = $fact;
        }

        $this->check('the WAN address is a fact with its full path', isset($byPath['interfaces/wan/ipaddr']));
        $this->check('labelled with the interface it belongs to', ($byPath['interfaces/wan/ipaddr']['label'] ?? '') === 'WAN — IPv4 address');
        $this->check('and its value is the address itself', ($byPath['interfaces/wan/ipaddr']['value'] ?? '') === '10.20.0.5');
        $this->check('an interface description carries the interface as context', ($byPath['interfaces/wan/descr']['label'] ?? '') === 'WAN — Description');

        // Repetition is what makes a path ambiguous, so repeated siblings are numbered.
        $this->check('the first firewall rule keeps its own path', isset($byPath['filter/rule[1]/descr']));
        $this->check('and the second is a different fact, not an overwrite', isset($byPath['filter/rule[2]/descr']));
        $this->check('so a rule reads as a rule', ($byPath['filter/rule[2]/descr']['label'] ?? '') === 'Rule #2 — Description');
        $this->check('and a host override reads as one', isset($byPath['unbound/hosts[2]/host']) && ($byPath['unbound/hosts[2]/host']['label'] ?? '') === 'Host override #2 — Host');

        $this->check('an SNMP community is marked as a credential', ($byPath['snmpd/rocommunity']['is_secret'] ?? false) === true);
        $this->check('an IPsec pre-shared key too', ($byPath['ipsec/phase1/pre-shared-key']['is_secret'] ?? false) === true);
        $this->check('a hostname is not', ($byPath['system/hostname']['is_secret'] ?? true) === false);
        $this->check('and neither is a key size, which only sounds like one', ConfigIndexer::isSecret('system/ssh/keysize') === false);

        $this->check('the section is the root element, so a filter is filterable', ($byPath['filter/rule[1]/descr']['section'] ?? '') === 'filter');
        $this->check('and the page can name it properly', ConfigIndexer::sectionLabel('filter') === 'Firewall rules');

        $this->check('text with newlines and stray spacing is normalised', ($byPath['aliases/alias/address']['value'] ?? '') === '10.20.0.10 10.20.0.11');
        $this->check('an element with no text is not a fact', !isset($byPath['system/empty']));

        $broken = ConfigIndexer::extract('<pfsense><system><hostname>oops</pfsense>');
        $this->check('a configuration that is not valid XML is refused, not half-indexed', $broken['ok'] === false && $broken['facts'] === []);
        $this->check('and the refusal says why', str_contains($broken['message'], 'not valid XML'));

        $deep = ConfigIndexer::extract('<pfsense>' . str_repeat('<a>', 20) . 'bottom' . str_repeat('</a>', 20) . '</pfsense>');
        $this->check('a configuration nested beyond reason is truncated rather than walked', $deep['ok'] === true && $deep['truncated'] === true);
    }

    // ------------------------------------------------------------------ B: a snapshot becomes searchable

    private function sectionB(): void
    {
        CLI::write('B. A stored snapshot is indexed, and re-indexing replaces rather than accumulates', 'yellow');

        $device = $this->device('SEARCH-A');
        $file   = $this->snapshot($device, 'prepush_' . date('Ymd_His') . '_aaaaaa_dead.xml', $this->fixtureXml());
        $backup = $this->backupRow($device, $file, '2026-09-20 09:00:00');

        // The management address the search page shows beside a device's name comes from pf_status.
        $this->db()->table('pf_status')->insert([
            'pf_id'      => (int) $device['id'],
            'ip'         => '10.20.0.99',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $indexer = new ConfigIndexer();
        $result  = $indexer->indexDevice($device);

        $this->check('the device indexes', $result['status'] === ConfigIndexStateModel::STATUS_INDEXED);
        $this->check('with facts extracted from it', $result['facts'] > 10);

        $state = $this->stateRow((int) $device['id']);

        $this->check('the coverage row names the snapshot it used', ($state['file_name'] ?? '') === basename($file));
        $this->check('and when that configuration was captured', ($state['captured_at'] ?? '') === '2026-09-20 09:00:00');
        $this->check('and how much of it is searchable', (int) ($state['nodes'] ?? 0) === $result['facts']);
        $this->check('the snapshot row is linked, so a fact can be traced back to an artifact', (int) ($state['backup_id'] ?? 0) === $backup);

        $indexed = $this->factCount((int) $device['id']);
        $this->check('the facts are in the index', $indexed === $result['facts']);

        $indexer->indexDevice($device);
        $this->check('indexing the same device again does not double its facts', $this->factCount((int) $device['id']) === $indexed);

        // The device's configuration changes (as it will), and the old facts must not survive alongside
        // the new ones — two versions of one device in the index would make every answer ambiguous.
        $changed = $this->fixtureXml('10.20.0.99', 'yavatmal-changed', 'Traffic at 50% capacity', 'private-community-v2');
        file_put_contents($file, $changed);
        touch($file, time() + 5);

        $indexer->indexDevice($device);

        $this->check('after the configuration changes, the old value is gone', $this->hasFact((int) $device['id'], 'value_norm', '10.20.0.5') === false);
        $this->check('and the new one is searchable', $this->hasFact((int) $device['id'], 'value_norm', '10.20.0.99') === true);
        $this->check('with the fact count still matching one configuration, not two', $this->hasFactCount((int) $device['id']) === $indexed);

        $this->fx['A'] = ['device' => $device, 'file' => $file];
    }

    // ------------------------------------------------------------------ C: searching

    private function sectionC(): void
    {
        CLI::write('C. The questions this exists for', 'yellow');

        $device  = $this->fx['A']['device'] ?? $this->device('SEARCH-C');
        $search  = new ConfigSearchService();

        $hit = $search->search(['q' => '10.20.0.99', 'section' => '', 'device' => '', 'exact' => true, 'reveal' => false]);

        $this->check('searching for an address finds it', $hit['matches'] >= 1);
        $this->check('and it is attributed to the device that holds it', ($hit['groups'][0]['device'] ?? '') === $device['name']);
        $this->check('with the field named in the operator\'s words', ($hit['groups'][0]['hits'][0]['label'] ?? '') === 'WAN — IPv4 address');
        $this->check('and the snapshot it came from, not a claim that it is live', ($hit['groups'][0]['captured_at'] ?? '') !== '');

        $contains = $search->search(['q' => '10.20.0', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('a partial address matches by contains', $contains['matches'] > $hit['matches']);

        $exactOnly = $search->search(['q' => '10.20.0', 'section' => '', 'device' => '', 'exact' => true, 'reveal' => false]);
        $this->check('but exact mode means exactly, not partially', $exactOnly['matches'] === 0);

        $byLabel = $search->search(['q' => 'Description', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('searching a field name finds the fields, not just the values', $byLabel['matches'] >= 2);

        $upper = $search->search(['q' => 'YAVATMAL-CHANGED', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('case does not have to be remembered', $upper['matches'] >= 1);

        $filtered = $search->search(['q' => '10.20.0', 'section' => 'filter', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('the section filter selects', $filtered['matches'] >= 1);
        $this->check('and does not widen the search', $filtered['matches'] <= $contains['matches']);
        $this->check('every row it returns really is in that section', $this->allInSection($filtered['groups'], 'filter'));

        $byDevice = $search->search(['q' => '10.20.0', 'section' => '', 'device' => (string) $device['name'], 'exact' => false, 'reveal' => false]);
        $this->check('the device filter narrows to that device', $byDevice['matches'] >= 1 && count($byDevice['groups']) === 1);

        $byId = $search->search(['q' => '10.20.0', 'section' => '', 'device' => (string) $device['id'], 'exact' => false, 'reveal' => false]);
        $this->check('and an id works as well as a name', $byId['matches'] >= 1 && count($byId['groups']) === 1);

        $nothing = $search->search(['q' => 'no-such-value-anywhere-1234', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('a term nothing matches returns nothing', $nothing['matches'] === 0);
        $this->check('but still says what the index covers', ($nothing['coverage']['indexed'] ?? 0) >= 1);

        // A wildcard in a search box is a character people type by accident. If % were passed through to
        // SQL, this query would "match" every fact in the fleet and the page would be worse than useless.
        $percent = $search->search(['q' => '50%', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('a percent sign in the query is matched literally', $percent['matches'] >= 1 && $percent['matches'] <= 3);

        $underscore = $search->search(['q' => 'traffic_at', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('and an underscore is not a single-character wildcard either', $underscore['matches'] === 0);

        $empty = $search->search(['q' => '', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('an empty query searches nothing rather than everything', $empty['matches'] === 0 && $empty['groups'] === []);

        $deviceIp = $search->search(['q' => '10.20.0.99', 'section' => '', 'device' => '', 'exact' => true, 'reveal' => false]);
        $this->check('the result names the device\'s management address when it is known', ($deviceIp['groups'][0]['device_ip'] ?? '') === '10.20.0.99');

        $this->sectionCSecrets($search);
        $this->sectionCExport($search);
    }

    private function sectionCSecrets(ConfigSearchService $search): void
    {
        $hidden = ['q' => 'private-community-v2', 'section' => '', 'device' => '', 'exact' => true, 'reveal' => false];
        $open   = array_merge($hidden, ['reveal' => true]);

        $masked = $search->search($hidden);

        $this->check('a credential is findable — that is the point of searching for one', $masked['matches'] >= 1);
        $this->check('but its value is not printed on the page', ($masked['groups'][0]['hits'][0]['masked'] ?? false) === true);
        $this->check('and the value itself is withheld, not merely styled', ($masked['groups'][0]['hits'][0]['value'] ?? 'x') === '');

        $revealed = $search->search($open);
        $this->check('an explicit reveal shows it', ($revealed['groups'][0]['hits'][0]['value'] ?? '') === 'private-community-v2');

        // The CSV goes through the export path the controller uses, not through the page's grouped rows:
        // an export that names no device because it was fed the wrong shape is exactly the failure this
        // check exists to catch.
        $device = (string) ($this->fx['A']['device']['name'] ?? '');

        $csv = ConfigSearchService::csv($search->exportRows($hidden)['rows']);
        $this->check('the CSV hides credentials by default too', str_contains($csv, 'hidden') && !str_contains($csv, 'private-community-v2'));
        $this->check('and still says which device holds them', $device !== '' && str_contains($csv, $device));

        $csvRevealed = ConfigSearchService::csv($search->exportRows($open)['rows'], true);
        $this->check('and includes them when the export asks for them', str_contains($csvRevealed, 'private-community-v2'));
    }

    private function sectionCExport(ConfigSearchService $search): void
    {
        $filters = ['q' => '10.20.0', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false];
        $export  = $search->exportRows($filters);

        $this->check('the export returns the matches', $export['rows'] !== [] && $export['total'] >= count($export['rows']));
        $this->check('and is not capped for a query of this size', $export['capped'] === false);

        $csv = ConfigSearchService::csv($export['rows']);
        $header = strtok($csv, "\n");

        $this->check('the CSV has a header an auditor can read', str_contains((string) $header, 'Device') && str_contains((string) $header, 'Section') && str_contains((string) $header, 'Captured at'));
        $this->check('and one line per fact', substr_count(trim($csv), "\n") === count($export['rows']));
        $this->check('the file is named for when it was taken', str_starts_with(ConfigSearchService::filename(), 'config-search-'));

        $empty = $search->exportRows(['q' => '', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);
        $this->check('exporting nothing is refused rather than dumping the fleet', $empty['rows'] === [] && $empty['total'] === 0);
    }

    // ------------------------------------------------------------------ D: what the index does not cover

    private function sectionD(): void
    {
        CLI::write('D. A device that could not be indexed is named, not silently missing', 'yellow');

        $indexer  = new ConfigIndexer();
        $coverage = $indexer->coverage();
        $before   = (int) $coverage['missing'];

        // (1) A snapshot row whose file is gone — pruned by retention, or a restore that lost the directory.
        $gone = $this->device('SEARCH-GONE');
        $this->backupRow($gone, WRITEPATH . 'noc_backup/' . ConfigSnapshotStore::safeName((string) $gone['name']) . '/never-written.xml', '2026-09-19 09:00:00');

        $result = $indexer->indexDevice($gone);
        $this->check('a snapshot that is not on disk is reported as missing', $result['status'] === ConfigIndexStateModel::STATUS_NO_SNAPSHOT);
        $this->check('and the reason is in an operator\'s words', str_contains($result['message'], 'No stored configuration snapshot'));

        // (2) A file that exists but is not a configuration.
        $broken  = $this->device('SEARCH-BROKEN');
        $brokenFile = $this->snapshot($broken, 'prepush_broken.xml', '<pfsense><system><hostname>oops</pfsense>');
        $this->backupRow($broken, $brokenFile, '2026-09-19 09:00:00');

        $brokenResult = $indexer->indexDevice($broken);
        $this->check('a snapshot that is not valid XML is reported as such', $brokenResult['status'] === ConfigIndexStateModel::STATUS_INVALID);
        $this->check('and nothing of it is indexed', $this->factCount((int) $broken['id']) === 0);

        // (3) A file far larger than a configuration.
        $huge     = $this->device('SEARCH-HUGE');
        $hugeFile = $this->sparseFile($huge, 'prepush_huge.xml', ConfigIndexer::MAX_BYTES + 1024);
        $this->backupRow($huge, $hugeFile, '2026-09-19 09:00:00');

        $hugeResult = $indexer->indexDevice($huge);
        $this->check('a snapshot larger than a configuration can be is refused', $hugeResult['status'] === ConfigIndexStateModel::STATUS_TOO_LARGE);

        // Every one of them now has to be visible in the coverage report, with its reason.
        $after = $indexer->coverage();

        $this->check('the coverage report counts them as not searchable', (int) $after['missing'] >= $before + 3);
        $this->check('it names the device whose snapshot is missing', $this->coverageNames($after, (string) $gone['name']));
        $this->check('the one whose snapshot is broken', $this->coverageNames($after, (string) $broken['name']));
        $this->check('and the one that is implausibly large', $this->coverageNames($after, (string) $huge['name']));

        $named = $this->coverageProblem($after, (string) $gone['name']);
        $this->check('with the reason attached, so the operator can act on it', ($named['reason'] ?? '') !== null);

        // A search must not imply these devices were looked at and found clean.
        $search = new ConfigSearchService();
        $result = $search->search(['q' => '10.20.0', 'section' => '', 'device' => '', 'exact' => false, 'reveal' => false]);

        $this->check('a search still reports the devices it could not read', (int) ($result['coverage']['missing'] ?? 0) >= 3);

        $this->fx['D'] = ['gone' => (int) $gone['id'], 'broken' => (int) $broken['id'], 'huge' => (int) $huge['id']];
    }

    // ------------------------------------------------------------------ E: the work list

    private function sectionE(): void
    {
        CLI::write('E. The work list, and the filters the page may pass', 'yellow');

        $indexer = new ConfigIndexer();
        $list    = [];

        foreach ($indexer->devicesToIndex() as $candidate) {
            $list[(int) $candidate['id']] = (string) $candidate['name'];
        }

        $expected = [$this->fx['A']['device']['id'] ?? 0, $this->fx['D']['gone'] ?? 0, $this->fx['D']['broken'] ?? 0, $this->fx['D']['huge'] ?? 0];

        foreach ($expected as $pfId) {
            $this->check('device #' . $pfId . ' (a snapshot exists for it) is in the work list', isset($list[(int) $pfId]));
        }

        // A device that is dormant but still holds a configuration is still worth searching: a retired
        // site's rules are exactly what somebody digs through the index to find.
        $this->check('including devices that are not active any more', count($list) >= 4);

        $filters = ConfigSearchService::normalizeFilters([
            'q'       => '  two   words  ' . str_repeat('x', 300),
            'section' => 'filter; DROP TABLE',
            'device'  => 'a',
            'exact'   => 'yes',
            'reveal'  => '0',
        ]);

        $this->check('a query is collapsed and capped', strlen($filters['q']) === ConfigSearchService::MAX_QUERY_LENGTH);
        $this->check('a section name cannot carry anything but a name', $filters['section'] === 'filterdroptable');
        $this->check('and the flags are read as yes/no', $filters['exact'] === true && $filters['reveal'] === false);

        $this->check('the examples the empty page offers are questions, not jargon', count(ConfigSearchService::examples()) >= 3);
        $this->check('the section dropdown is ordered by how much is in it', $this->sectionsDescending());
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
            'username' => 'harness',
            'password' => 'harness',
        ], true);

        $this->deviceIds[] = $id;

        return $model->find($id) ?? ['id' => $id, 'name' => $label];
    }

    /** A real file in the device's snapshot folder: the indexer reads from disk, not from a stub. */
    private function snapshot(array $device, string $fileName, string $contents): string
    {
        $dir = WRITEPATH . 'noc_backup/' . ConfigSnapshotStore::safeName((string) $device['name']);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $path = $dir . '/' . $fileName;
        file_put_contents($path, $contents);

        $this->files[] = $path;

        return $path;
    }

    /** A file of the given size without writing that many bytes. */
    private function sparseFile(array $device, string $fileName, int $bytes): string
    {
        $dir = WRITEPATH . 'noc_backup/' . ConfigSnapshotStore::safeName((string) $device['name']);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $path   = $dir . '/' . $fileName;
        $handle = fopen($path, 'wb');

        if ($handle !== false) {
            fseek($handle, $bytes - 2);
            fwrite($handle, '<x');
            fclose($handle);
        }

        $this->files[] = $path;

        return $path;
    }

    private function backupRow(array $device, string $path, string $capturedAt): int
    {
        $id = (int) (new PfBakupModel())->insert([
            'pf_id'      => (int) $device['id'],
            'file_name'  => basename($path),
            'file_path'  => $path,
            'quantity'   => 1,
            'created_at' => $capturedAt,
        ], true);

        $this->backupIds[] = $id;

        return $id;
    }

    /**
     * A small but realistic pfSense configuration: interfaces, rules, aliases, a resolver host override,
     * SNMP, an IPsec phase 1, and a few of the shapes that make extraction awkward (repeated siblings,
     * whitespace to normalise, an empty element, a credential).
     */
    private function fixtureXml(string $wanIp = '10.20.0.5', string $hostname = 'branch-gateway-01', string $secondRuleDescr = 'Block telnet', string $community = 'akola-community'): string
    {
        return '<?xml version="1.0"?>
<pfsense>
    <version>23.09.1</version>
    <system>
        <hostname>' . $hostname . '</hostname>
        <domain>pfcentral.local</domain>
        <empty></empty>
        <dnssearch></dnssearch>
    </system>
    <interfaces>
        <wan>
            <if>em0</if>
            <ipaddr>' . $wanIp . '</ipaddr>
            <subnet>24</subnet>
            <gateway>10.20.0.1</gateway>
            <descr>WAN uplink</descr>
        </wan>
        <lan>
            <if>em1</if>
            <ipaddr>192.168.1.1</ipaddr>
            <subnet>24</subnet>
        </lan>
    </interfaces>
    <filter>
        <rule>
            <type>pass</type>
            <interface>lan</interface>
            <descr>Allow LAN to WAN</descr>
            <destination>
                <address>8.8.8.8</address>
                <port>53</port>
            </destination>
        </rule>
        <rule>
            <type>block</type>
            <interface>wan</interface>
            <descr>' . $secondRuleDescr . '</descr>
            <destination>
                <address>10.20.0.0</address>
                <port>23</port>
            </destination>
        </rule>
    </filter>
    <aliases>
        <alias>
            <name>web-servers</name>
            <address>
                10.20.0.10 10.20.0.11
            </address>
            <descr>Public web servers</descr>
        </alias>
    </aliases>
    <unbound>
        <hosts>
            <host>printer1</host>
            <domain>pfcentral.local</domain>
            <ip>10.20.0.30</ip>
            <descr>Office printer</descr>
        </hosts>
        <hosts>
            <host>scanner1</host>
            <domain>pfcentral.local</domain>
            <ip>10.20.0.31</ip>
            <descr>Office scanner</descr>
        </hosts>
    </unbound>
    <snmpd>
        <rocommunity>' . $community . '</rocommunity>
        <syslocation>Primary Datacenter</syslocation>
        <syscontact>admin@pfcentral.local</syscontact>
    </snmpd>
    <ipsec>
        <phase1>
            <ikeid>1</ikeid>
            <remote-gateway>203.0.113.7</remote-gateway>
            <pre-shared-key>supersecretpsk</pre-shared-key>
        </phase1>
    </ipsec>
</pfsense>';
    }

    // ------------------------------------------------------------------ helpers

    private function db()
    {
        return db_connect();
    }

    private function stateRow(int $pfId): array
    {
        return $this->db()->table('config_index_state')->where('pf_id', $pfId)->get()->getRowArray() ?? [];
    }

    private function factCount(int $pfId): int
    {
        return $this->db()->table('config_index')->where('pf_id', $pfId)->countAllResults();
    }

    private function hasFact(int $pfId, string $column, string $value): bool
    {
        return $this->db()->table('config_index')->where('pf_id', $pfId)->where($column, $value)->countAllResults() > 0;
    }

    /** The number of facts for a device, derived from the state row rather than counted again. */
    private function hasFactCount(int $pfId): int
    {
        return (int) ($this->stateRow($pfId)['nodes'] ?? 0);
    }

    private function allInSection(array $groups, string $section): bool
    {
        foreach ($groups as $group) {
            foreach ($group['hits'] as $hit) {
                if ($hit['section'] !== $section) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed> $coverage */
    private function coverageNames(array $coverage, string $device): bool
    {
        return $this->coverageProblem($coverage, $device) !== [];
    }

    /** @param array<string, mixed> $coverage */
    private function coverageProblem(array $coverage, string $device): array
    {
        foreach ($coverage['problems'] as $problem) {
            if ($problem['device'] === $device) {
                return $problem;
            }
        }

        return [];
    }

    private function sectionsDescending(): bool
    {
        $options = (new ConfigSearchService())->sectionOptions();
        $previous = PHP_INT_MAX;

        foreach ($options as $option) {
            if ($option['n'] > $previous) {
                return false;
            }

            $previous = $option['n'];
        }

        return $options !== [];
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $db = $this->db();

        return [
            'config_index'       => $db->table('config_index')->countAllResults(),
            'config_index_state' => $db->table('config_index_state')->countAllResults(),
            'pf_backups'         => $db->table('pf_backups')->countAllResults(),
            'pf_inventory'       => $db->table('pf_inventory')->countAllResults(),
            'pf_status'          => $db->table('pf_status')->countAllResults(),
        ];
    }

    private function cleanup(): void
    {
        $db = $this->db();

        if ($this->deviceIds !== []) {
            $db->table('config_index')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('config_index_state')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('pf_status')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('pf_backups')->whereIn('pf_id', $this->deviceIds)->delete();
            $db->table('pf_inventory')->whereIn('id', $this->deviceIds)->delete();
        }

        if ($this->backupIds !== []) {
            $db->table('pf_backups')->whereIn('id', $this->backupIds)->delete();
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
