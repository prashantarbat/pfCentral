<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;
use App\Models\CoreConfigModel;
use App\Models\AppGroupsModel;
use App\Models\PfBakupModel;
use App\Models\LocationModel;
use App\Models\WgMplsModel;
use App\Models\WgIllModel;
use App\Models\WgBdrModel;

class Map extends BaseController
{
    protected $pfInventoryModel;
    protected $pfStatusModel;
    protected $coreConfigModel;
    protected $pfBakupModel;
    protected $appGroupsModel;
    protected $locationModel;
    protected $wgMplsModel;
    protected $wgIllModel;
    protected $wgBdrModel;

    public function __construct()
    {
        $this->pfInventoryModel = new PfInventoryModel();
        $this->pfStatusModel    = new PfStatusModel();
        $this->coreConfigModel  = new CoreConfigModel(); 
        $this->pfBakupModel     = new PfBakupModel();  
        $this->appGroupsModel   = new AppGroupsModel();
        $this->locationModel    = new LocationModel();

        $this->wgMplsModel      = new WgMplsModel();
        $this->wgIllModel       = new WgIllModel();
        $this->wgBdrModel       = new WgBdrModel();
    }

    public function index()
    {
        /** ---------------------------------------------------------
         *  Flash Messages
         * -------------------------------------------------------- */
        $data = [
            'message' => session()->getFlashdata('message'),
            'error'   => session()->getFlashdata('error'),
        ];

        if (session()->has('display_message')) {
            session()->remove('display_message');
        }

        /** ---------------------------------------------------------
         *  Load App Config
         * -------------------------------------------------------- */
        $config = $this->coreConfigModel->whereIn('name', [
            'app_name','app_version','app_url','mast_pass','google_maps_api_key','google_maps_map_id'
        ])->findAll();

        $data['app_name']  = $this->getConfigValue($config, 'app_name', 'My App');
        $data['app_version'] = $this->getConfigValue($config, 'app_version', '1.0.0');
        $data['app_url'] = $this->getConfigValue($config, 'app_url', '');
        $data['mast_pass'] = $this->getConfigValue($config, 'mast_pass', '');
        $data['google_maps_api_key'] = $this->getConfigValue($config, 'google_maps_api_key', '');
        // Optional Cloud Map ID: enables AdvancedMarkerElement (and cloud-based map styling)
        $data['google_maps_map_id'] = $this->getConfigValue($config, 'google_maps_map_id', '');
        $data['title'] = $data['app_name'] . ' 🔸 Network Map View';

        /** ---------------------------------------------------------
         * Authenticated User
         * -------------------------------------------------------- */
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        /** ---------------------------------------------------------
         * Load Inventory + Status
         * -------------------------------------------------------- */
        $devices = $this->pfInventoryModel->findAll();
        $statusRecords = $this->pfStatusModel->findAll();
        $statusMap = array_column($statusRecords, null, 'pf_id');

        /** ---------------------------------------------------------
         * Merge Status + Health
         * -------------------------------------------------------- */
        foreach ($devices as &$d) {
            $pfId = $d['id'];
            $d['ip']   = $statusMap[$pfId]['ip'] ?? 'N/A';
            $d['port'] = $statusMap[$pfId]['port'] ?? 'N/A';

            $location = $this->locationModel->find($d['location']);
            $d['health'] = $location ? $this->getWgHealth($location) : 'Unknown';
        }

        /** ---------------------------------------------------------
         * Load Locations + Health
         * -------------------------------------------------------- */
        $locationIds = array_unique(array_filter(array_column($devices,'location')));
        $locations = !empty($locationIds)
            ? $this->locationModel->whereIn('id', $locationIds)->findAll()
            : [];

        foreach ($locations as &$loc) {
            $loc['health'] = $this->getWgHealth($loc);
        }

        $data['locations'] = $locations;

        /** ---------------------------------------------------------
         * Render Map View
         * -------------------------------------------------------- */
        return view('map', $data);
    }

    /** -------------------------------------------------------------
     * Helper: Get Config Value
     * ------------------------------------------------------------ */
    private function getConfigValue($items, $key, $default = null)
    {
        foreach ($items as $i) {
            if ($i['name'] === $key) return $i['value'];
        }
        return $default;
    }

    /** -------------------------------------------------------------
     * Determine WireGuard Health
     * ------------------------------------------------------------ */
    private function getWgHealth($location)
    {
        $mplsUp = null;
        $illUp  = null;

        if (!empty($location['wg_mpls'])) {
            $mpls = $this->wgMplsModel->where('interface', $location['wg_mpls'])
                                      ->orderBy('id','DESC')->first();
            $mplsUp = $mpls['status'] ?? null;
        }

        if (!empty($location['wg_ill'])) {
            $ill = $this->wgIllModel->where('interface', $location['wg_ill'])
                                    ->orderBy('id','DESC')->first();
            $illUp = $ill['status'] ?? null;
        }

        return $this->computeHealth($mplsUp, $illUp);
    }

    /** -------------------------------------------------------------
     * Health from the latest status of each WireGuard interface.
     * Same rule as always: at least one healthy tunnel = Online.
     * ------------------------------------------------------------ */
    private function computeHealth($mplsStatus, $illStatus): string
    {
        return ($mplsStatus == 1 || $illStatus == 1) ? 'Online' : 'Offline';
    }

    /** -------------------------------------------------------------
     * Latest status per WireGuard interface, in two batched queries
     * (max id per interface, then those rows) instead of one query
     * per location.
     * ------------------------------------------------------------ */
    private function latestInterfaceStatuses(array $ifaces, string $table): array
    {
        $ifaces = array_values(array_unique(array_filter($ifaces)));
        if ($ifaces === []) {
            return [];
        }

        $db = \Config\Database::connect();

        $max = $db->table($table)
            ->select('interface, MAX(id) AS last_id')
            ->whereIn('interface', $ifaces)
            ->groupBy('interface')
            ->get()
            ->getResultArray();

        if ($max === []) {
            return [];
        }

        $rows = $db->table($table)
            ->whereIn('id', array_column($max, 'last_id'))
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['interface']] = $r['status'];
        }
        return $map;
    }

    /** -------------------------------------------------------------
     * Lightweight JSON feed for the map's live polling. Returns every
     * location with its current health (the payload is a few KB even
     * for a large fleet), cached briefly so several open map tabs share
     * one computation. Public like the /map page itself, which already
     * renders all of this inline.
     * ------------------------------------------------------------ */
    public function status()
    {
        $cacheKey = 'map-status-payload-v2';
        $cached = \Config\Services::cache()->get($cacheKey);
        if (is_array($cached) && isset($cached['generated_at'], $cached['locations'])) {
            return $this->response->setJSON($cached);
        }

        $locations = $this->locationModel->findAll();

        $mplsMap = $this->latestInterfaceStatuses(array_column($locations, 'wg_mpls'), 'wg_mpls');
        $illMap  = $this->latestInterfaceStatuses(array_column($locations, 'wg_ill'), 'wg_ill');
        $bdrMap  = $this->latestInterfaceStatuses(array_column($locations, 'wg_bdr'), 'wg_bdr');

        $payload = [
            'generated_at' => time(),
            'locations'    => [],
        ];

        foreach ($locations as $loc) {
            $payload['locations'][] = [
                'id'        => $loc['id'],
                'name'      => $loc['name'],
                'ip'        => $loc['ip'],
                'wg_mpls'   => $loc['wg_mpls'],
                'wg_ill'    => $loc['wg_ill'],
                'wg_bdr'      => $loc['wg_bdr'],
                'address'     => $loc['address'],
                'email'       => $loc['email'],
                'phone'       => $loc['phone'],
                'latitude'    => $loc['latitude'],
                'longitude'   => $loc['longitude'],
                'mpls_status' => $loc['wg_mpls'] ? ($mplsMap[$loc['wg_mpls']] ?? null) : null,
                'ill_status'  => $loc['wg_ill']  ? ($illMap[$loc['wg_ill']] ?? null)  : null,
                'bdr_status'  => $loc['wg_bdr']  ? ($bdrMap[$loc['wg_bdr']] ?? null)  : null,
                'health'      => $this->computeHealth(
                    $loc['wg_mpls'] ? ($mplsMap[$loc['wg_mpls']] ?? null) : null,
                    $loc['wg_ill']  ? ($illMap[$loc['wg_ill']] ?? null)  : null
                ),
            ];
        }

        \Config\Services::cache()->save($cacheKey, $payload, 15);

        return $this->response->setJSON($payload);
    }

    /** -------------------------------------------------------------
     * Generate & Download PHP Weathermap Configuration
     * ------------------------------------------------------------ */
    public function weathermapConfig()
    {
        // Prevent PHP warnings/errors from contaminating the plain-text configuration file
        error_reporting(0);
        ini_set('display_errors', '0');

        // 1. Resolve configuration parameters
        $dbConfigs = $this->coreConfigModel->whereIn('name', [
            'librenms_api_url',
            'librenms_api_token'
        ])->findAll();

        $configMap = array_column($dbConfigs, 'value', 'name');

        $librenmsApiUrl = $configMap['librenms_api_url'] 
            ?? env('LIBRENMS_API_URL') 
            ?? 'http://localhost/api/v0';

        $librenmsApiToken = $configMap['librenms_api_token'] 
            ?? env('LIBRENMS_API_TOKEN') 
            ?? 'YOUR_LIBRENMS_API_TOKEN';

        $rrdBase = $this->request->getGet('rrd-path') ?: '/opt/librenms/rrd/';
        $hubMplsName = $this->request->getGet('hub-mpls') ?: 'VyOS-MPLS';
        $hubInetName = $this->request->getGet('hub-inet') ?: 'VyOS-INET';
        $mode = $this->request->getGet('mode') ?: 'wireguard';

        $rrdBase = rtrim($rrdBase, '/') . '/';
        $mode = strtolower(trim($mode, '"\''));
        if (!in_array($mode, ['wireguard', 'physical', 'wg', 'nic'])) {
            $mode = 'wireguard';
        }
        if ($mode === 'wg') {
            $mode = 'wireguard';
        }
        if ($mode === 'nic') {
            $mode = 'physical';
        }

        // 2. Query active branches
        $db = \Config\Database::connect();
        $builder = $db->table('pf_locations');
        $builder->select('pf_locations.*, pf_inventory.active, pf_inventory.bypass');
        $builder->join('pf_inventory', 'TRIM(pf_inventory.name) = TRIM(pf_locations.name)', 'left');
        $locations = $builder->get()->getResultArray();

        $activeBranches = [];
        foreach ($locations as $loc) {
            $isActive = isset($loc['active']) ? (int)$loc['active'] === 1 : true;
            $isBypassed = isset($loc['bypass']) ? (int)$loc['bypass'] === 1 : false;

            if ($isActive && !$isBypassed) {
                $activeBranches[] = $loc;
            }
        }

        $branchCount = count($activeBranches);

        // 3. Query all devices in LibreNMS
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$librenmsApiUrl}/devices");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: {$librenmsApiToken}"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $deviceLookup = [];
        if ($httpCode === 200) {
            $lnmsDevicesData = json_decode($response, true);
            $lnmsDevices = $lnmsDevicesData['devices'] ?? [];
            foreach ($lnmsDevices as $d) {
                $hostname = strtolower(trim($d['hostname'] ?? ''));
                $ip = strtolower(trim($d['ip'] ?? ''));
                if (!empty($hostname)) {
                    $deviceLookup[$hostname] = $d;
                }
                if (!empty($ip)) {
                    $deviceLookup[$ip] = $d;
                }
            }
        }

        // 4. Discover interfaces and query ports
        $branchMappings = [];
        foreach ($activeBranches as $branch) {
            $name = trim($branch['name']);
            $ip = strtolower(trim($branch['ip'] ?? ''));

            $device = null;
            if (!empty($ip) && isset($deviceLookup[$ip])) {
                $device = $deviceLookup[$ip];
            } elseif (isset($deviceLookup[strtolower($name)])) {
                $device = $deviceLookup[strtolower($name)];
            }

            $deviceId = $device ? $device['device_id'] : null;
            $deviceHostname = $device ? $device['hostname'] : null;
            $mplsPort = null;
            $illPort = null;

            if ($deviceId) {
                $portsCh = curl_init();
                curl_setopt($portsCh, CURLOPT_URL, "{$librenmsApiUrl}/devices/{$deviceId}/ports?columns=port_id,ifName,ifDescr,ifAlias,ifType");
                curl_setopt($portsCh, CURLOPT_HTTPHEADER, ["X-Auth-Token: {$librenmsApiToken}"]);
                curl_setopt($portsCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($portsCh, CURLOPT_TIMEOUT, 10);
                $portsRes = curl_exec($portsCh);
                $portsCode = curl_getinfo($portsCh, CURLINFO_HTTP_CODE);
                curl_close($portsCh);

                if ($portsCode === 200) {
                    $portsData = json_decode($portsRes, true);
                    $ports = $portsData['ports'] ?? [];

                    $wgMplsIface = trim(strtolower($branch['wg_mpls'] ?? ''));
                    $wgIllIface  = trim(strtolower($branch['wg_ill'] ?? ''));

                    // 1. Search in WireGuard Mode
                    if ($mode === 'wireguard') {
                        foreach ($ports as $port) {
                            $ifName  = trim(strtolower($port['ifName'] ?? ''));
                            $ifDescr = trim(strtolower($port['ifDescr'] ?? ''));
                            $ifAlias = trim(strtolower($port['ifAlias'] ?? ''));

                            // Match MPLS Wireguard Interface (e.g. wg134 or WG134)
                            if (!empty($wgMplsIface)) {
                                if ($ifName === $wgMplsIface || 
                                    $ifDescr === $wgMplsIface || 
                                    str_contains($ifAlias, $wgMplsIface) ||
                                    (str_starts_with($ifName, 'tun_wg') && str_contains($ifAlias, str_replace('_', '', $wgMplsIface)))
                                ) {
                                    $mplsPort = $port;
                                }
                            }

                            // Match ILL Wireguard Interface (e.g. wg534 or WG534)
                            if (!empty($wgIllIface)) {
                                if ($ifName === $wgIllIface || 
                                    $ifDescr === $wgIllIface || 
                                    str_contains($ifAlias, $wgIllIface) ||
                                    (str_starts_with($ifName, 'tun_wg') && str_contains($ifAlias, str_replace('_', '', $wgIllIface)))
                                ) {
                                    $illPort = $port;
                                }
                            }
                        }
                    }

                    // 2. Search in Physical Mode / Fallback for MPLS Port
                    if (!$mplsPort) {
                        foreach ($ports as $port) {
                            $ifName  = trim(strtolower($port['ifName'] ?? ''));
                            $ifAlias = trim(strtolower($port['ifAlias'] ?? ''));

                            // First NIC typically igc0, igb0, em0, re0 or alias containing 'mpls'
                            if ($ifName === 'igc0' || $ifName === 'igb0' || $ifName === 'em0' || $ifName === 're0' || 
                                str_contains($ifAlias, 'mpls')
                            ) {
                                $mplsPort = $port;
                                break;
                            }
                        }
                    }

                    // 3. Search in Physical Mode / Fallback for ILL/Internet Port
                    if (!$illPort) {
                        foreach ($ports as $port) {
                            $ifName  = trim(strtolower($port['ifName'] ?? ''));
                            $ifAlias = trim(strtolower($port['ifAlias'] ?? ''));

                            // Second NIC typically igc1, igb1, em1, re1 or alias containing ILL keywords
                            if ($ifName === 'igc1' || $ifName === 'igb1' || $ifName === 'em1' || $ifName === 're1' || 
                                str_contains($ifAlias, 'ill') || str_contains($ifAlias, 'airtel') || 
                                str_contains($ifAlias, 'bsnl') || str_contains($ifAlias, 'internet')
                            ) {
                                $illPort = $port;
                                break;
                            }
                        }
                    }
                }
            }

            $branchMappings[] = [
                'db' => $branch,
                'device_id' => $deviceId,
                'device_hostname' => $deviceHostname,
                'mpls_port' => $mplsPort,
                'ill_port'  => $illPort,
            ];
        }

        // 5. Generate Weathermap configuration
        $out = [];
        $out[] = "# ==========================================================================";
        $out[] = "# pfCentral - Live Operations Topology Map";
        $out[] = "# Automatically generated on " . date('Y-m-d H:i:s');
        $out[] = "# ==========================================================================";
        $out[] = "";
        $out[] = "BACKGROUND images/dark_noc_bg.png";
        $out[] = "HTMLSTYLE floating";
        $out[] = "TITLE pfCentral Platform - Network Topology Map";
        $out[] = "TIMEPOS 10 30 Live: %Y-%m-%d %H:%M:%S";
        $out[] = "FONTDEFINE 100 docs/fonts/LiberationSans-Regular.ttf 8";
        $out[] = "FONTDEFINE 200 docs/fonts/LiberationSans-Bold.ttf 10";
        $out[] = "";
        $out[] = "KEYPOS DEFAULT 30 920 Bandwidth Legend";
        $out[] = "KEYTEXTCOLOR 241 245 249";
        $out[] = "KEYBGCOLOR 15 23 42";
        $out[] = "BGCOLOR 15 23 42";
        $out[] = "TITLECOLOR 241 245 249";
        $out[] = "";
        $out[] = "SCALE DEFAULT 0    10   148 163 184";
        $out[] = "SCALE DEFAULT 10   50    34 197  94";
        $out[] = "SCALE DEFAULT 50   85   234 179   8";
        $out[] = "SCALE DEFAULT 85   100  239  68  68";
        $out[] = "";
        $out[] = "NODE " . $hubMplsName;
        $out[] = "    LABEL VyOS MPLS DC Core";
        $out[] = "    INFOURL /device/device=" . urlencode($hubMplsName) . "/";
        $out[] = "    ICON images/router.png";
        $out[] = "    POSITION 880 540";
        $out[] = "";
        $out[] = "NODE " . $hubInetName;
        $out[] = "    LABEL VyOS Internet DC Core";
        $out[] = "    INFOURL /device/device=" . urlencode($hubInetName) . "/";
        $out[] = "    ICON images/router.png";
        $out[] = "    POSITION 1040 540";
        $out[] = "";

        $cx = 960;
        $cy = 540;
        $rx = 760;
        $ry = 440;

        $nodeIndex = 0;
        foreach ($branchMappings as $mapping) {
            $branch = $mapping['db'];
            $branchName = trim($branch['name']);
            $nodeId = 'BR-' . trim(preg_replace('/[^a-zA-Z0-9_-]/', '-', $branchName), '-');

            $angle = (2 * M_PI * $nodeIndex) / max(1, $branchCount);
            $x = round($cx + $rx * cos($angle));
            $y = round($cy + $ry * sin($angle));
            $nodeIndex++;

            $deviceId = $mapping['device_id'];
            $devHost = $mapping['device_hostname'] ?? $branch['ip'];

            $out[] = "# Branch: {$branchName}";
            $out[] = "NODE {$nodeId}";
            $out[] = "    LABEL {$branchName}";
            if ($deviceId) {
                $out[] = "    INFOURL /device/device={$deviceId}/";
            }
            $out[] = "    ICON images/firewall.png";
            $out[] = "    POSITION {$x} {$y}";
            $out[] = "";

            // Link 1: MPLS
            $mplsPort = $mapping['mpls_port'];
            $out[] = "LINK {$nodeId}-MPLS";
            $out[] = "    NODES {$hubMplsName} {$nodeId}";
            $out[] = "    BANDWIDTH 100M";
            if ($mplsPort && $deviceId) {
                $portId = $mplsPort['port_id'];
                $out[] = "    TARGET {$rrdBase}{$devHost}/port-id{$portId}.rrd:traffic_in:traffic_out";
                $out[] = "    INFOURL /device/device={$deviceId}/tab=port/port={$portId}/";
                $out[] = "    OVERLIBGRAPH /graph.php?height=100&width=250&id={$portId}&type=port_bits&legend=no";
            } else {
                $out[] = "    # WG Interface not discovered yet or device not in LibreNMS";
            }
            $out[] = "    OUTLINECOLOR 51 65 85";
            $out[] = "    BWLABEL bits";
            $out[] = "    BWSTYLE classic";
            $out[] = "";

            // Link 2: Internet WG
            $illPort = $mapping['ill_port'];
            $out[] = "LINK {$nodeId}-WG";
            $out[] = "    NODES {$hubInetName} {$nodeId}";
            $out[] = "    BANDWIDTH 50M";
            if ($illPort && $deviceId) {
                $portId = $illPort['port_id'];
                $out[] = "    TARGET {$rrdBase}{$devHost}/port-id{$portId}.rrd:traffic_in:traffic_out";
                $out[] = "    INFOURL /device/device={$deviceId}/tab=port/port={$portId}/";
                $out[] = "    OVERLIBGRAPH /graph.php?height=100&width=250&id={$portId}&type=port_bits&legend=no";
            } else {
                $out[] = "    # WG Interface not discovered yet or device not in LibreNMS";
            }
            $out[] = "    OUTLINECOLOR 51 65 85";
            $out[] = "    BWLABEL bits";
            $out[] = "    BWSTYLE classic";
            $out[] = "";
        }

        $confContent = implode("\n", $out);

        return $this->response
            ->setHeader('Content-Type', 'text/plain')
            ->setHeader('Content-Disposition', 'attachment; filename="wan_map.conf"')
            ->setBody($confContent);
    }
}
