<?php

namespace App\Services\ConfigSearch;

use App\Models\ConfigIndexModel;
use App\Models\ConfigIndexStateModel;
use App\Models\PfBakupModel;
use App\Models\PfInventoryModel;
use App\Services\ConfigPush\ConfigSnapshotStore;

/**
 * Turns stored `config.xml` snapshots into searchable facts.
 *
 * The search page must answer questions like *which devices still point at 10.20.0.1* without parsing
 * forty configurations on every keystroke, so the substance is extracted once, ahead of time, and the
 * extraction is a pure function of the XML — which is what makes it testable without a database and
 * what makes a re-index idempotent.
 *
 * Two rules shape the output:
 *
 *  1. **A fact has to be readable on its own.** `interfaces/wan/ipaddr` on a results page means nothing
 *     to the person reading it; `WAN — IPv4 address` does. Labels are built from the path with the
 *     surrounding context that makes a list entry identifiable (`Rule #3 — Description`), so a result
 *     can be judged without opening the device.
 *  2. **A device that could not be indexed has to be visibly absent, not silently empty.** Every run
 *     writes a `config_index_state` row for the device saying which snapshot was used, or why none was:
 *     deleted from disk, unreadable, not valid XML, larger than a configuration has any business being.
 *     A search that cannot say what it did *not* look at is a search nobody should trust.
 *
 * Values are stored, including credentials: finding every device that shares a leaked pre-shared key is
 * one of the reasons to build this at all. They are flagged `is_secret` so the page masks them by
 * default rather than printing them into a screenshot.
 */
class ConfigIndexer
{
    /** Ceiling on facts per device: a malformed or hostile upload must not fill the table. */
    public const MAX_FACTS = 40000;

    /** A configuration deeper than this is not a configuration worth walking. */
    public const MAX_DEPTH = 14;

    /** Longest value kept per fact. Package blobs can be enormous and are not what anyone searches for. */
    public const MAX_VALUE = 2000;

    /** Matches ConfigSnapshotStore's own ceiling, so "too large" means the same thing in both places. */
    public const MAX_BYTES = ConfigSnapshotStore::MAX_BYTES;

    /** An index older than this is worth warning about: configurations do change. */
    public const STALE_AFTER_HOURS = 72;

    /** Elements whose text is a credential: masked in the UI, never written to a log line. */
    private const SECRET_LEAVES = [
        'password', 'passwd', 'pass', 'psk', 'presharedkey', 'pre-shared-key', 'ipsecpsk',
        'secret', 'shared_secret', 'sharedsecret', 'community', 'rocommunity', 'rwcommunity',
        'token', 'apikey', 'api-key', 'privatekey', 'private-key', 'privkey', 'prv', 'key',
        'bindpw', 'wpa', 'hash', 'tls',
    ];

    /** Path fragments that mark a credential wherever they appear, not just at the leaf. */
    private const SECRET_PATH_TOKENS = [
        '/password', '/passwd', '/psk', '/preshared', '/pre-shared', '/privatekey', '/privkey',
        '/secret', '/shared_secret', '/sharedsecret', '/token', '/apikey', '/api-key', '/community',
        '/bindpw', 'keys/', '/prv',
    ];

    /** Elements that merely *sound* like secrets. Masking a key size tells nobody anything useful. */
    private const NOT_SECRET_LEAVES = [
        'keysize', 'keylen', 'keylength', 'keytype', 'hashalgo', 'hashalgorithm', 'crypto',
        'encryption-algorithm', 'username', 'user', 'descr',
    ];

    /**
     * Leaf element -> the phrase an operator would use. Deliberately a curated list rather than a
     * generated one: these are the handful of fields that make up the questions actually asked.
     */
    private const LEAF_LABELS = [
        'ipaddr' => 'IPv4 address',
        'ipaddrv6' => 'IPv6 address',
        'subnet' => 'Subnet',
        'subnetv6' => 'IPv6 subnet',
        'gateway' => 'Gateway',
        'descr' => 'Description',
        'description' => 'Description',
        'name' => 'Name',
        'hostname' => 'Hostname',
        'domain' => 'Domain',
        'address' => 'Address',
        'port' => 'Port',
        'interface' => 'Interface',
        'if' => 'Interface',
        'proto' => 'Protocol',
        'type' => 'Type',
        'enable' => 'Enabled',
        'disabled' => 'Disabled',
        'mac' => 'MAC address',
        'dns' => 'DNS server',
        'dnsserver' => 'DNS server',
        'timeservers' => 'Time servers',
        'ip' => 'IP address',
        'user' => 'Username',
        'username' => 'Username',
        'password' => 'Password',
        'pass' => 'Password',
        'community' => 'SNMP community',
        'rocommunity' => 'SNMP read-only community',
        'rwcommunity' => 'SNMP read/write community',
        'syslocation' => 'SNMP location',
        'syscontact' => 'SNMP contact',
        'psk' => 'Pre-shared key',
        'presharedkey' => 'Pre-shared key',
        'pre-shared-key' => 'Pre-shared key',
        'ipsecpsk' => 'IPsec pre-shared key',
        'key' => 'Key',
        'privatekey' => 'Private key',
        'prv' => 'Private key',
        'cert' => 'Certificate',
        'crt' => 'Certificate',
        'tracker' => 'Rule tracker',
        'tag' => 'Tag',
        'uid' => 'User ID',
        'vpnid' => 'VPN id',
        'ikeid' => 'Tunnel id',
        'from' => 'Range from',
        'to' => 'Range to',
        'code' => 'Code',
        'path' => 'Path',
        'url' => 'URL',
        'command' => 'Command',
        'shellcmd' => 'Shell command',
    ];

    /** Repeated siblings that deserve a name of their own when quoted in a label. */
    private const LIST_NOUNS = [
        'rule' => 'Rule',
        'alias' => 'Alias',
        'hosts' => 'Host override',
        'staticmap' => 'Static mapping',
        'phase1' => 'IPsec phase 1',
        'phase2' => 'IPsec phase 2',
        'item' => 'Entry',
        'user' => 'User',
        'group' => 'Group',
        'cert' => 'Certificate',
        'crl' => 'CRL',
        'server' => 'Server',
        'client' => 'Client',
        'peer' => 'Peer',
        'allow' => 'Allow entry',
        'static' => 'Static entry',
    ];

    /** Roots whose immediate child is an interface/zone name worth putting in front of every label. */
    private const NAMED_SCOPES = ['interfaces', 'dhcpd', 'dhcpdv6', 'pppoe'];

    /** Roots that are the same story under different element names. */
    private const SECTION_FOLD = [
        'dhcpdv6'    => 'dhcpd',
        'dnsmasq'    => 'unbound',
        'ca'         => 'cert',
        'cron'       => 'system',
        'syslog'     => 'system',
        'ntp'        => 'system',
        'widgets'    => 'system',
        'load_balancer' => 'loadbalancer',
    ];

    /** Operator-facing name per section. Anything absent is humanised from the element name. */
    private const SECTION_LABELS = [
        'interfaces'     => 'Interfaces',
        'filter'         => 'Firewall rules',
        'nat'            => 'NAT',
        'aliases'        => 'Aliases',
        'system'         => 'System',
        'snmpd'          => 'SNMP',
        'unbound'        => 'DNS resolver',
        'dhcpd'          => 'DHCP',
        'ipsec'          => 'IPsec',
        'openvpn'        => 'OpenVPN',
        'cert'           => 'Certificates',
        'installedpackages' => 'Installed packages',
        'loadbalancer'   => 'Load balancer',
        'virtualip'      => 'Virtual IPs',
        'gifs'           => 'GIF interfaces',
        'vlans'          => 'VLANs',
        'bridges'        => 'Bridges',
        'wireless'       => 'Wireless',
        'captiveportal'  => 'Captive portal',
        'shaper'         => 'Traffic shaping',
        'wol'            => 'Wake on LAN',
        'staticroutes'   => 'Static routes',
    ];

    protected ConfigIndexModel $index;
    protected ConfigIndexStateModel $state;
    protected ConfigSnapshotStore $snapshots;
    protected PfInventoryModel $inventory;
    protected PfBakupModel $backups;

    public function __construct(
        ?ConfigIndexModel $index = null,
        ?ConfigIndexStateModel $state = null,
        ?ConfigSnapshotStore $snapshots = null,
        ?PfInventoryModel $inventory = null,
        ?PfBakupModel $backups = null
    ) {
        $this->index     = $index ?? new ConfigIndexModel();
        $this->state     = $state ?? new ConfigIndexStateModel();
        $this->snapshots = $snapshots ?? new ConfigSnapshotStore();
        $this->inventory = $inventory ?? new PfInventoryModel();
        $this->backups   = $backups ?? new PfBakupModel();
    }

    // ------------------------------------------------------------------ indexing

    /**
     * Indexes one device from its newest stored snapshot, replacing whatever facts it had before.
     *
     * @param array<string, mixed> $device a pf_inventory-shaped row (id + name at minimum)
     *
     * @return array{pf_id:int, device:string, status:string, facts:int, nodes:int, truncated:bool, bytes:?int, captured_at:?string, message:string}
     */
    public function indexDevice(array $device): array
    {
        $pfId = (int) ($device['id'] ?? 0);
        $name = trim((string) ($device['name'] ?? ''));

        if ($pfId <= 0) {
            return $this->summary(0, $name, ConfigIndexStateModel::STATUS_NO_SNAPSHOT, 0, 0, false, null, null, 'A device without an id cannot be indexed.');
        }

        if ($name === '') {
            $name = 'device #' . $pfId;
        }

        $snapshot = $this->snapshots->latest($device);

        if (empty($snapshot['ok'])) {
            $status = $this->failureStatus($snapshot);
            $reason = (string) ($snapshot['message'] ?? 'No snapshot could be read.');
            $size   = $this->sizeOf((string) ($snapshot['path'] ?? ''));

            $this->writeState($pfId, $name, $status, $reason, $snapshot, $size, 0);

            return $this->summary($pfId, $name, $status, 0, 0, false, $size, null, $reason);
        }

        $contents = (string) $snapshot['contents'];
        $size     = strlen($contents);

        if ($size > self::MAX_BYTES) {
            $reason = 'The stored snapshot is larger than a configuration should be, so it was not indexed.';

            $this->writeState($pfId, $name, ConfigIndexStateModel::STATUS_TOO_LARGE, $reason, $snapshot, $size, 0);

            return $this->summary($pfId, $name, ConfigIndexStateModel::STATUS_TOO_LARGE, 0, 0, false, $size, null, $reason);
        }

        $parsed = self::extract($contents);

        if (empty($parsed['ok'])) {
            $this->writeState($pfId, $name, ConfigIndexStateModel::STATUS_INVALID, $parsed['message'], $snapshot, $size, 0);

            return $this->summary($pfId, $name, ConfigIndexStateModel::STATUS_INVALID, 0, 0, false, $size, null, $parsed['message']);
        }

        // The artifact's own row is the authoritative capture time: a file's mtime moves when the file
        // is touched, while the row was written when the configuration was actually taken off the device.
        $artifact   = $this->artifactFor($pfId, (string) ($snapshot['file'] ?? ''));
        $capturedAt = $artifact['created_at'] ?? ($snapshot['captured_at'] !== null ? date('Y-m-d H:i:s', (int) $snapshot['captured_at']) : null);
        $backupId   = $artifact['id'] ?? null;
        $now        = date('Y-m-d H:i:s');
        $rows       = [];

        foreach ($parsed['facts'] as $fact) {
            $norm = self::normalizeValue((string) $fact['value']);
            $rows[] = [
                'pf_id'       => $pfId,
                'device_name' => $name,
                'section'     => $fact['section'],
                'xml_path'    => $fact['path'],
                'label'       => $fact['label'],
                'value_text'  => $fact['value'],
                'value_norm'  => mb_substr($norm, 0, 191),
                'value_hash'  => sha1(mb_substr($norm, 0, 191)),
                'is_secret'   => $fact['is_secret'] ? 1 : 0,
                'captured_at' => $capturedAt,
                'backup_id'   => $backupId,
                'indexed_at'  => $now,
            ];
        }

        // Replace inside a transaction: a half-replaced device would be a search result that is partly
        // the old configuration and partly the new one, which is worse than either.
        $db = db_connect();
        $db->transStart();

        try {
            $db->table('config_index')->where('pf_id', $pfId)->delete();

            foreach (array_chunk($rows, 250) as $chunk) {
                $this->index->insertBatch($chunk);
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Config index write failed for device ' . $pfId . ': ' . $e->getMessage());

            $reason = 'The extracted facts could not be stored: ' . $e->getMessage();
            $this->writeState($pfId, $name, ConfigIndexStateModel::STATUS_UNREADABLE, $reason, $snapshot, $size, 0);

            return $this->summary($pfId, $name, ConfigIndexStateModel::STATUS_UNREADABLE, 0, 0, false, $size, $capturedAt, $reason);
        }

        if ($db->transStatus() === false) {
            $reason = 'The extracted facts could not be stored.';

            $this->writeState($pfId, $name, ConfigIndexStateModel::STATUS_UNREADABLE, $reason, $snapshot, $size, 0);

            return $this->summary($pfId, $name, ConfigIndexStateModel::STATUS_UNREADABLE, 0, 0, false, $size, $capturedAt, $reason);
        }

        $this->writeState($pfId, $name, ConfigIndexStateModel::STATUS_INDEXED, null, $snapshot, $size, count($rows), $capturedAt, $backupId);

        $message = count($rows) . ' fact(s) indexed from ' . (string) ($snapshot['file'] ?? 'the stored snapshot') . '.';

        if (!empty($parsed['truncated'])) {
            $message .= ' The configuration was larger than the fact ceiling, so the tail was not indexed.';
        }

        return $this->summary($pfId, $name, ConfigIndexStateModel::STATUS_INDEXED, count($rows), count($rows), (bool) $parsed['truncated'], $size, $capturedAt, $message);
    }

    /**
     * Indexes every device that either is active or has a stored snapshot.
     *
     * The union matters: an inactive device still holds a snapshot somebody may search for ("which of the
     * retired sites still had that rule?"), and an active device with no snapshot must still appear in the
     * coverage report as *not indexed* rather than vanish.
     *
     * @return array{devices:int, indexed:int, facts:int, failed:list<string>}
     */
    public function indexAll(?callable $emit = null, ?int $limit = null): array
    {
        $devices = $this->devicesToIndex();
        $indexed = 0;
        $facts   = 0;
        $failed  = [];

        foreach ($devices as $device) {
            if ($limit !== null && $indexed >= $limit) {
                break;
            }

            $result = $this->indexDevice($device);

            if ($result['status'] === ConfigIndexStateModel::STATUS_INDEXED) {
                $indexed++;
                $facts += $result['facts'];
            } else {
                $failed[] = $result['device'] . ': ' . $result['message'];
            }

            if ($emit !== null) {
                $emit($result);
            }
        }

        return ['devices' => count($devices), 'indexed' => $indexed, 'facts' => $facts, 'failed' => $failed];
    }

    /**
     * Every device the index should cover: active inventory rows, plus anything a snapshot exists for.
     *
     * @return list<array<string, mixed>>
     */
    public function devicesToIndex(): array
    {
        $devices = [];
        $names   = [];

        try {
            foreach ($this->inventory->findAll() as $row) {
                $id   = (int) ($row['id'] ?? 0);
                $name = trim((string) ($row['name'] ?? ''));

                if ($id <= 0) {
                    continue;
                }

                $names[$id] = $name !== '' ? $name : 'device #' . $id;

                if ((string) ($row['active'] ?? '') === '1') {
                    $devices[$id] = ['id' => $id, 'name' => $names[$id]];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Inventory read failed while building the index work list: ' . $e->getMessage());
        }

        try {
            foreach ($this->backups->select('pf_id')->distinct()->findAll() as $row) {
                $id = (int) ($row['pf_id'] ?? 0);

                if ($id > 0 && !isset($devices[$id])) {
                    $devices[$id] = ['id' => $id, 'name' => $names[$id] ?? ('device #' . $id)];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'pf_backups read failed while building the index work list: ' . $e->getMessage());
        }

        ksort($devices);

        return array_values($devices);
    }

    /**
     * What the index covers right now, in the shape the page's coverage strip wants.
     *
     * @return array{total:int, indexed:int, missing:int, facts:int, stale:bool, last_indexed:?string, oldest_indexed:?string, problems:list<array{pf_id:int, device:string, status:string, label:string, reason:?string}>}
     */
    public function coverage(): array
    {
        $db = db_connect();

        try {
            $byStatus = [];
            $facts    = 0;
            $last     = null;
            $oldest   = null;

            foreach ($db->table('config_index_state')->select('status, COUNT(*) AS n, SUM(nodes) AS nodes, MAX(indexed_at) AS last_at, MIN(indexed_at) AS first_at')->groupBy('status')->get()->getResultArray() as $row) {
                $status = (string) $row['status'];
                $byStatus[$status] = (int) $row['n'];
                $facts += (int) ($row['nodes'] ?? 0);

                foreach ([$row['last_at'] ?? null, $row['first_at'] ?? null] as $candidate) {
                    if ($candidate === null || $candidate === '') {
                        continue;
                    }

                    $last   = ($last === null || $candidate > $last) ? (string) $candidate : $last;
                    $oldest = ($oldest === null || $candidate < $oldest) ? (string) $candidate : $oldest;
                }
            }

            $problems = [];

            foreach ($db->table('config_index_state')->where('status !=', ConfigIndexStateModel::STATUS_INDEXED)->orderBy('device_name', 'ASC')->limit(25)->get()->getResultArray() as $row) {
                $problems[] = [
                    'pf_id'  => (int) $row['pf_id'],
                    'device' => (string) $row['device_name'],
                    'status' => (string) $row['status'],
                    'label'  => ConfigIndexStateModel::statusLabel((string) $row['status']),
                    'reason' => $row['reason'] !== null ? (string) $row['reason'] : null,
                ];
            }

            $total   = array_sum($byStatus);
            $indexed = $byStatus[ConfigIndexStateModel::STATUS_INDEXED] ?? 0;

            return [
                'total'          => $total,
                'indexed'        => $indexed,
                'missing'        => $total - $indexed,
                'facts'          => $facts,
                'by_status'      => $byStatus,
                'stale'          => $this->isStale($last),
                'last_indexed'   => $last,
                'oldest_indexed' => $oldest,
                'problems'       => $problems,
            ];
        } catch (\Throwable $e) {
            log_message('error', 'Config index coverage read failed: ' . $e->getMessage());

            return [
                'total'          => 0,
                'indexed'        => 0,
                'missing'        => 0,
                'facts'          => 0,
                'by_status'      => [],
                'stale'          => false,
                'last_indexed'   => null,
                'oldest_indexed' => null,
                'problems'       => [],
            ];
        }
    }

    // ------------------------------------------------------------------ pure extraction

    /**
     * Extracts every leaf value from a configuration, with a path and a readable label for each.
     *
     * Pure: no database, no disk. That is what lets the unit tests pin the shape of a fact — including
     * the awkward parts, like repeated siblings needing an index to be tellable apart — without a
     * fixture database.
     *
     * @return array{ok:bool, facts:list<array{section:string, path:string, label:string, value:string, is_secret:bool}>, nodes:int, truncated:bool, message:string}
     */
    public static function extract(string $xml): array
    {
        if (trim($xml) === '') {
            return ['ok' => false, 'facts' => [], 'nodes' => 0, 'truncated' => false, 'message' => 'The stored snapshot is empty.'];
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();

        // No entity expansion (NOENT is deliberately absent) and no network: a configuration is
        // device-supplied input, and it gets treated as such.
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $document->documentElement === null) {
            return ['ok' => false, 'facts' => [], 'nodes' => 0, 'truncated' => false, 'message' => 'The stored snapshot is not valid XML.'];
        }

        $facts     = [];
        $truncated = false;

        // The root element (`pfsense`) is deliberately *not* part of a path: every configuration has
        // one, it would prefix every label, and it would become the section of every fact — turning the
        // one filter that organises the whole page into a single useless value.
        foreach ($document->documentElement->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                self::walk($child, self::cleanName($child->nodeName), 0, $facts, $truncated);
            }
        }

        return [
            'ok'        => true,
            'facts'     => $facts,
            'nodes'     => count($facts),
            'truncated' => $truncated,
            'message'   => count($facts) . ' fact(s) extracted.',
        ];
    }

    /** The operator-facing label for an XML path, e.g. `interfaces/wan/ipaddr` -> "WAN — IPv4 address". */
    public static function label(string $path): string
    {
        $parts = array_values(array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return $path;
        }

        $leaf = self::cleanName((string) end($parts));
        $name = self::LEAF_LABELS[$leaf] ?? self::humanize($leaf);
        $lead = self::context($parts);

        return $lead === '' ? $name : $lead . ' — ' . $name;
    }

    /** Whether a path's value is a credential that should be masked on screen. */
    public static function isSecret(string $path): bool
    {
        $parts = array_values(array_filter(explode('/', $path), static fn (string $part): bool => $part !== ''));
        $leaf  = strtolower(self::cleanName((string) end($parts)));

        if ($leaf !== '' && in_array($leaf, self::NOT_SECRET_LEAVES, true)) {
            return false;
        }

        if ($leaf !== '' && in_array($leaf, self::SECRET_LEAVES, true)) {
            return true;
        }

        $needle = '/' . strtolower(implode('/', $parts));

        foreach (self::SECRET_PATH_TOKENS as $token) {
            if (str_contains($needle, $token)) {
                return true;
            }
        }

        return false;
    }

    /** The machine section for a path, folded so the filter list stays short enough to read. */
    public static function sectionOf(string $path): string
    {
        $parts = explode('/', $path);
        $root  = self::cleanName((string) ($parts[0] ?? ''));

        return self::SECTION_FOLD[$root] ?? $root;
    }

    /** The operator-facing section name. */
    public static function sectionLabel(string $section): string
    {
        return self::SECTION_LABELS[$section] ?? self::humanize($section);
    }

    /** Lower-cased, whitespace-collapsed, control-character-free — what a search actually compares. */
    public static function normalizeValue(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /** `name[3]` -> `name`; everything else unchanged. */
    public static function cleanName(string $name): string
    {
        return preg_replace('/\[[0-9]+\]$/', '', $name) ?? $name;
    }

    private static function walk(\DOMElement $element, string $path, int $depth, array &$facts, bool &$truncated): void
    {
        if (count($facts) >= self::MAX_FACTS) {
            $truncated = true;

            return;
        }

        $children = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        if ($children === []) {
            $value = self::normalizeValue($element->textContent);

            if ($value === '') {
                return;
            }

            if (mb_strlen($value) > self::MAX_VALUE) {
                $value = mb_substr($value, 0, self::MAX_VALUE) . '…';
            }

            $facts[] = [
                'section'   => self::sectionOf($path),
                'path'      => $path,
                'label'     => self::label($path),
                'value'     => $value,
                'is_secret' => self::isSecret($path),
            ];

            return;
        }

        if ($depth >= self::MAX_DEPTH) {
            $truncated = true;

            return;
        }

        // Repeated siblings get an index, so `filter/rule[3]/descr` can be told from `rule[7]/descr` —
        // without it, every firewall rule in a configuration would share one path and one label.
        $counts = [];
        $seen   = [];

        foreach ($children as $child) {
            $counts[$child->nodeName] = ($counts[$child->nodeName] ?? 0) + 1;
        }

        foreach ($children as $child) {
            $name = $child->nodeName;
            $seen[$name] = ($seen[$name] ?? 0) + 1;
            $segment = $counts[$name] > 1 ? $name . '[' . $seen[$name] . ']' : $name;

            self::walk($child, $path . '/' . $segment, $depth + 1, $facts, $truncated);

            if (count($facts) >= self::MAX_FACTS) {
                $truncated = true;

                return;
            }
        }
    }

    /**
     * The words in front of a label: the interface/zone a value belongs to, or the numbered list entry.
     *
     * @param list<string> $parts
     */
    private static function context(array $parts): string
    {
        // A numbered entry comes first: "Static mapping #1" identifies the thing far better than "LAN",
        // and the interface is still right there in the path underneath the label.
        for ($i = count($parts) - 1; $i >= 0; $i--) {
            if (preg_match('/^([A-Za-z0-9_\-]+)\[([0-9]+)\]$/', $parts[$i], $matches) === 1) {
                $noun = self::LIST_NOUNS[$matches[1]] ?? self::humanize($matches[1]);

                return $noun . ' #' . $matches[2];
            }
        }

        // Nothing numbered, but the value sits under an interface or zone: "WAN — IPv4 address" says
        // which interface at a glance, which is the question people are actually asking.
        $scope  = self::cleanName((string) ($parts[0] ?? ''));
        $second = self::cleanName((string) ($parts[1] ?? ''));

        if ($second !== '' && in_array($scope, self::NAMED_SCOPES, true)) {
            return strtoupper($second);
        }

        return '';
    }

    private static function humanize(string $name): string
    {
        $words = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', str_replace(['_', '-'], ' ', $name)) ?? $name;
        $words = trim($words);

        return $words === '' ? $name : ucfirst($words);
    }

    // ------------------------------------------------------------------ plumbing

    /**
     * Why a snapshot could not be indexed, in the vocabulary the coverage report uses.
     *
     * `ConfigSnapshotStore::latest()` has already refused the file and said why, so the message is the
     * most precise evidence available: a snapshot that exists but is not a configuration is a *different*
     * operational problem from one that cannot be read at all, and the operator fixes them differently.
     */
    private function failureStatus(array $snapshot): string
    {
        $path = (string) ($snapshot['path'] ?? '');

        if ($path === '' || !is_file($path)) {
            return ConfigIndexStateModel::STATUS_NO_SNAPSHOT;
        }

        $size = $this->sizeOf($path);

        if ($size !== null && $size > self::MAX_BYTES) {
            return ConfigIndexStateModel::STATUS_TOO_LARGE;
        }

        $message = strtolower((string) ($snapshot['message'] ?? ''));

        if (str_contains($message, 'not valid xml') || str_contains($message, 'is empty')) {
            return ConfigIndexStateModel::STATUS_INVALID;
        }

        return ConfigIndexStateModel::STATUS_UNREADABLE;
    }

    private function sizeOf(string $path): ?int
    {
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $size = @filesize($path);

        return $size === false ? null : (int) $size;
    }

    /**
     * The `pf_backups` row for the file being indexed, if one is on record.
     *
     * @return array{id:?int, created_at:?string}
     */
    private function artifactFor(int $pfId, string $fileName): array
    {
        if ($pfId <= 0 || $fileName === '') {
            return ['id' => null, 'created_at' => null];
        }

        try {
            $row = $this->backups->where('pf_id', $pfId)->where('file_name', $fileName)->orderBy('id', 'DESC')->first();
        } catch (\Throwable $e) {
            return ['id' => null, 'created_at' => null];
        }

        if ($row === null) {
            return ['id' => null, 'created_at' => null];
        }

        return [
            'id'         => (int) $row['id'],
            'created_at' => $row['created_at'] !== null && $row['created_at'] !== '' ? (string) $row['created_at'] : null,
        ];
    }

    private function writeState(
        int $pfId,
        string $name,
        string $status,
        ?string $reason,
        array $snapshot,
        ?int $size,
        int $nodes,
        ?string $capturedAt = null,
        ?int $backupId = null
    ): void {
        $row = [
            'pf_id'       => $pfId,
            'device_name' => $name,
            'status'      => $status,
            'reason'      => $reason === null ? null : mb_substr($reason, 0, 255),
            'backup_id'   => $backupId,
            'file_name'   => $snapshot['file'] ?? null,
            'file_path'   => $snapshot['path'] ?? null,
            'captured_at' => $capturedAt,
            'file_size'   => $size,
            'nodes'       => $nodes,
            'indexed_at'  => date('Y-m-d H:i:s'),
        ];

        try {
            if ($this->state->find($pfId) === null) {
                $this->state->insert($row);

                return;
            }

            $this->state->update($pfId, $row);
        } catch (\Throwable $e) {
            log_message('error', 'Config index state write failed for device ' . $pfId . ': ' . $e->getMessage());
        }
    }

    private function isStale(?string $lastIndexed): bool
    {
        if ($lastIndexed === null || $lastIndexed === '') {
            return false;
        }

        return (time() - (int) strtotime($lastIndexed)) > (self::STALE_AFTER_HOURS * 3600);
    }

    /**
     * @return array{pf_id:int, device:string, status:string, facts:int, nodes:int, truncated:bool, bytes:?int, captured_at:?string, message:string}
     */
    private function summary(int $pfId, string $name, string $status, int $facts, int $nodes, bool $truncated, ?int $bytes, ?string $capturedAt, string $message): array
    {
        return [
            'pf_id'       => $pfId,
            'device'      => $name === '' ? 'device #' . $pfId : $name,
            'status'      => $status,
            'facts'       => $facts,
            'nodes'       => $nodes,
            'truncated'   => $truncated,
            'bytes'       => $bytes,
            'captured_at' => $capturedAt,
            'message'     => $message,
        ];
    }
}
