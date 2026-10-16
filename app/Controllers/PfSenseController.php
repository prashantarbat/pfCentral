<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use Config\Database;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;
use App\Models\CoreConfigModel;
use App\Models\AppGroupsModel;
use App\Models\PfBakupModel;
use App\Models\LocationModel;
use App\Models\WgMplsModel;
use App\Models\WgIllModel;
use App\Models\WgBdrModel;
use App\Models\NewDeviceModel;
use App\Services\BackupRetentionService;
use App\Services\PfSense\DeviceTelemetryService;
use App\Services\PfSense\PfSenseBackupService;
use App\Services\PfSense\DeviceCredentialService;
use App\Services\PfSense\PfSenseApiClient;
use CodeIgniter\CLI\CLI;

class PfSenseController extends BaseController
{
    protected $pfInventoryModel;
    protected $PfStatusModel;
    protected $CoreConfigModel;
    protected $AppGroupsModel;
    protected $PfBakupModel;
    protected $LocationModel;
    protected $WgMplsModel;
    protected $WgIllModel;
    protected $WgBdrModel;
    protected $NewDeviceModel;
    protected $backupRetention;

    // Focused Domain Services
    protected DeviceTelemetryService $telemetryService;
    protected PfSenseBackupService $backupService;
    protected DeviceCredentialService $credentialService;
    protected PfSenseApiClient $apiClient;

    public function __construct()
    {
        $this->pfInventoryModel = new PfInventoryModel();
        $this->PfStatusModel = new PfStatusModel();
        $this->CoreConfigModel = new CoreConfigModel(); 
        $this->PfBakupModel = new PfBakupModel();  
        $this->AppGroupsModel = new AppGroupsModel();
        $this->LocationModel = new LocationModel();
        $this->WgMplsModel = new WgMplsModel();
        $this->WgIllModel = new WgIllModel();
        $this->WgBdrModel = new WgBdrModel();
        $this->NewDeviceModel = new NewDeviceModel();
        $this->backupRetention = new BackupRetentionService();

        // Instantiate domain services
        $this->telemetryService  = new DeviceTelemetryService($this->pfInventoryModel, $this->PfStatusModel);
        $this->backupService     = new PfSenseBackupService($this->pfInventoryModel, $this->PfBakupModel, $this->PfStatusModel, $this->backupRetention);
        $this->credentialService = new DeviceCredentialService($this->pfInventoryModel, $this->PfStatusModel);
        $this->apiClient         = new PfSenseApiClient();
    }

    public function index()
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';
    
        // Fetch app_name and version from the core_config table
        $appNameConfig = $this->CoreConfigModel->where('name', 'app_name')->first();
        $appName = $appNameConfig['value'] ?? 'My Application';
    
        $appVersionConfig = $this->CoreConfigModel->where('name', 'app_version')->first();
        $appVersion = $appVersionConfig['value'] ?? '1.0.0';
    
        $appUrlConfig = $this->CoreConfigModel->where('name', 'app_url')->first();
        $appUrl = $appUrlConfig['value'] ?? 'http://localhost/pfadminhub/';
    
        $appMpassConfig = $this->CoreConfigModel->where('name', 'mast_pass')->first();
        $mast_pass = $appMpassConfig['value'] ?? 'p@ssw0rd';
    
        $data['title'] = strip_tags($appName) . ' 🔸 Device & Firewall Inventory';
        $data['app_name'] = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url'] = $appUrl;
        $data['mast_pass'] = $mast_pass;
    
        // Fetch all records from pfInventoryModel
        $data['pf'] = $this->pfInventoryModel->findAll();
    
        // Fetch all records from PfStatusModel
        $statusRecords = $this->PfStatusModel->findAll();
    
        // Create a map of pf_id to PfStatusModel records for quick lookup
        $statusMap = [];
        foreach ($statusRecords as $statusRecord) {
            $statusMap[$statusRecord['pf_id']] = $statusRecord;
        }
    
        // Fetch all locations and create a mapping of id → location name
        $locations = $this->LocationModel->findAll();
        $locationMap = [];
        foreach ($locations as $location) {
            $locationMap[$location['id']] = $location['name']; // Assuming 'name' is the column storing location names
        }
        
        $data['locations'] = $this->LocationModel->findAll();
    
        // Merge PfStatusModel data with pfInventoryModel records
        foreach ($data['pf'] as &$row) {
            // Fetch and replace location ID with actual location name
            $row['location'] = $locationMap[$row['location']] ?? 'Unknown Location';
    
            if (isset($statusMap[$row['id']])) {
                // Merge PfStatusModel fields with pfInventoryModel data. pf_status has its
                // own auto-increment 'id' - drop it first or it clobbers the pf_inventory
                // primary key and every action button below targets the wrong device.
                $statusData = $statusMap[$row['id']];
                unset($statusData['id']);
                $row = array_merge($row, $statusData);
            } else {
                // Fill default values for fields if no record is found
                $row['ip'] = '...';
                $row['port'] = '...';
                $row['api'] = '...';
                $row['mpls_ip'] = '...';
                $row['ill_ip'] = '...';
                $row['ssh_port'] = '...';
                $row['wan_ip'] = '...';
                $row['status'] = '...';
                $row['lightsquid_port'] = '...';
                $row['darkstat_port'] = '...';
                $row['ntop_port'] = '...';
                $row['os_name'] = '...';
                $row['os_version'] = '...';
                $row['hostname'] = '...';
                $row['build_identifier'] = '...';
                $row['uptime_duration'] = '...';
                $row['users'] = '...';
                $row['load_averages'] = '...';
                $row['ping_latency'] = '...';
                $row['cpu_usage'] = '...';
                $row['total_memory'] = '...';
                $row['available_memory'] = '...';
                $row['disk_usage'] = '...';
                $row['active_tunnel'] = '...';
                // NULL, not 0: no agent has ever reported this device's header.
                $row['banner'] = null;
            }
        }
    
        // Fetch groups for the modal dropdown
        $data['groups'] = $this->AppGroupsModel->findAll();
         
        echo view('frontend/header', $data);
        echo view('frontend/sidebar', $data);
        echo view('listmode/pflist', $data);
        echo view('frontend/footer', $data);
    }

    public function actionModal($id)
    {
        $builder = $this->pfInventoryModel->builder();
        $builder->select('pf_inventory.*, 
            pf_status.ip as status_ip,
            pf_status.port as status_port,
            pf_status.ssh_port,
            pf_status.restapi,
            pf_status.lightsquid_port,
            pf_status.bandwidthd,
            pf_status.darkstat_port
        ');
        $builder->join('pf_status', 'pf_status.pf_id = pf_inventory.id', 'left');
        $builder->where('pf_inventory.id', $id);
        $row = $builder->get()->getRowArray();

        if (!$row) return 'Device not found';
        
        return view('functions/buttonactionmodal_content', ['row' => $row]);
    }

    public function ajaxList()
    {
        $request = service('request');

        $draw   = (int) ($request->getPost('draw') ?? 1);
        $start  = (int) ($request->getPost('start') ?? 0);
        $length = (int) ($request->getPost('length') ?? 50);
        $searchValue = $request->getPost('search')['value'] ?? '';

        // Fetch configurations for LibreNMS & Grafana dynamically
        $coreConfigModel = new \App\Models\CoreConfigModel();
        $dbConfigs = $coreConfigModel->whereIn('name', ['librenms_api_url', 'grafana_url'])->findAll();
        $configMap = array_column($dbConfigs, 'value', 'name');
        $librenmsApiUrl = $configMap['librenms_api_url'] ?? '';
        $grafanaUrl = $configMap['grafana_url'] ?? '';

        $orderArray = $request->getPost('order');
        $orderColumnIndex = 0;
        $orderDir = 'asc';
        
        if (is_array($orderArray) && isset($orderArray[0])) {
            $orderColumnIndex = (int) ($orderArray[0]['column'] ?? 0);
            $orderDir = in_array($orderArray[0]['dir'] ?? 'asc', ['asc', 'desc']) 
                        ? $orderArray[0]['dir'] 
                        : 'asc';
        }
        
        // Map DataTable column index → orderable DB column
        // Columns: 0:#, 1:Status & Tunnel, 2:Device & Branch, 3:Network & Uplinks, 4:System Health, 5:Last Synced, 6:Actions
        $columns = [
            0  => 'pf_inventory.id',
            1  => 'pf_status.status',
            2  => 'pf_inventory.name',
            3  => 'pf_status.ip',
            4  => 'pf_status.cpu_usage',
            5  => 'pf_status.updated_at',
            6  => 'pf_inventory.id',
        ];
        
        $orderByCol = $columns[$orderColumnIndex] ?? 'pf_inventory.name';

        // The banner column arrives via migration. Older deployments whose DB has
        // not been migrated yet must not 500 the whole table because of it (the
        // 2026-09-24 outage: code deployed before the DB migrated, so this select
        // threw "Unknown column pf_status.banner" and /pflist showed a DataTables
        // Ajax error). Degraded mode: no select, no badge, page works.
        $bannerAvailable = Database::connect()->fieldExists('banner', 'pf_status');

        $builder = $this->pfInventoryModel->builder();
        $builder->select('pf_inventory.*, 
            pf_locations.name as location_name,
            pf_locations.wg_mpls,
            pf_locations.wg_ill,
            pf_locations.wg_bdr,
            pf_status.ip as status_ip,
            pf_status.port as status_port,
            pf_status.ssh_port,
            pf_status.mpls_ip,
            pf_status.ill_ip,
            pf_status.wan_ip,
            pf_status.status,
            pf_status.hostname,
            pf_status.os_name,
            pf_status.os_version,
            pf_status.build_identifier,
            pf_status.uptime_duration,
            pf_status.users as active_users,
            pf_status.load_averages,
            pf_status.ping_latency,
            pf_status.cpu_usage,
            pf_status.total_memory,
            pf_status.available_memory,
            pf_status.disk_usage,
            pf_status.active_tunnel,
            pf_status.lightsquid_port,
            pf_status.darkstat_port,
            pf_status.ntop_port,
            pf_status.bandwidthd,
            pf_status.restapi,
            pf_status.updated_at as status_updated_at,
            TIMESTAMPDIFF(SECOND, pf_status.updated_at, NOW()) as status_seconds_ago
        ');

        if ($bannerAvailable) {
            $builder->select('pf_status.banner');
        }
        $builder->select('
            pf_devices.hostname as dev_hostname,
            pf_devices.lan_ip as dev_lan_ip,
            pf_devices.mpls_ip as dev_mpls_ip,
            pf_devices.ill_ip as dev_ill_ip
        ');
        $builder->join('pf_locations', 'pf_locations.id = pf_inventory.location', 'left');
        $builder->join('pf_status', 'pf_status.pf_id = pf_inventory.id', 'left');
        $builder->join('pf_devices', 'TRIM(pf_devices.br_name) = TRIM(pf_inventory.name)', 'left');

        if (!empty($searchValue)) {
            $builder->groupStart()
                    ->like('pf_inventory.name', $searchValue)
                    ->orLike('pf_status.ip', $searchValue)
                    ->orLike('pf_inventory.man_ip', $searchValue)
                    ->orLike('pf_status.hostname', $searchValue)
                    ->orLike('pf_devices.hostname', $searchValue)
                    ->orLike('pf_status.mpls_ip', $searchValue)
                    ->orLike('pf_devices.mpls_ip', $searchValue)
                    ->orLike('pf_status.ill_ip', $searchValue)
                    ->orLike('pf_devices.ill_ip', $searchValue)
                    ->orLike('pf_status.wan_ip', $searchValue)
                    ->orLike('pf_locations.name', $searchValue)
                    ->orLike('pf_locations.wg_mpls', $searchValue)
                    ->orLike('pf_locations.wg_ill', $searchValue)
                    ->orLike('pf_locations.wg_bdr', $searchValue)
                    ->orLike('pf_status.os_name', $searchValue)
                    ->orLike('pf_status.build_identifier', $searchValue)
                    ->orLike('pf_inventory.notes', $searchValue)
                    ->groupEnd();
        }

        $locationFilter = $request->getPost('locationFilter') ?? '';
        if (!empty($locationFilter)) {
            $builder->where('pf_inventory.location', $locationFilter);
        }

        $groupFilter = $request->getPost('groupFilter') ?? '';
        if (!empty($groupFilter)) {
            $builder->where('pf_inventory.group_id', $groupFilter);
        }

        $totalRecordsWithFilter = $builder->countAllResults(false);
        
        if ($orderByCol === 'pf_inventory.name') {
            $builder->orderBy("CASE 
                WHEN pf_inventory.name LIKE '%0000%' THEN 2 
                WHEN pf_inventory.name LIKE '%9999%' THEN 3 
                WHEN CAST(pf_inventory.name AS UNSIGNED) > 0 THEN 0 
                ELSE 1 END", "ASC");
            $builder->orderBy("CAST(pf_inventory.name AS UNSIGNED)", $orderDir);
            $builder->orderBy('pf_inventory.name', $orderDir);
        } else {
            $builder->orderBy($orderByCol, $orderDir);
        }

        if ($length > 0) {
            $builder->limit($length, $start);
        }
        $records = $builder->get()->getResultArray();

        $totalAllRecords = $this->pfInventoryModel->countAll();

        // Fetch WireGuard tunnel statuses dynamically to match loggedlive's real-time states
        $mplsMap = [];
        $illMap = [];
        $bdrMap = [];
        try {
            $db = \Config\Database::connect();
            
            // Get latest status for all MPLS interfaces
            $mplsLatest = $db->query("
                SELECT m1.interface, m1.status 
                FROM wg_mpls m1 
                INNER JOIN (
                    SELECT interface, MAX(id) as max_id 
                    FROM wg_mpls 
                    GROUP BY interface
                ) m2 ON m1.interface = m2.interface AND m1.id = m2.max_id
            ")->getResultArray();
            foreach ($mplsLatest as $m) {
                if (isset($m['interface'])) {
                    $mplsMap[strtolower(trim($m['interface']))] = (int)$m['status'];
                }
            }

            // Get latest status for all ILL interfaces
            $illLatest = $db->query("
                SELECT i1.interface, i1.status 
                FROM wg_ill i1 
                INNER JOIN (
                    SELECT interface, MAX(id) as max_id 
                    FROM wg_ill 
                    GROUP BY interface
                ) i2 ON i1.interface = i2.interface AND i1.id = i2.max_id
            ")->getResultArray();
            foreach ($illLatest as $i) {
                if (isset($i['interface'])) {
                    $illMap[strtolower(trim($i['interface']))] = (int)$i['status'];
                }
            }

            // Get latest status for all BDR interfaces
            $bdrLatest = $db->query("
                SELECT b1.interface, b1.status 
                FROM wg_bdr b1 
                INNER JOIN (
                    SELECT interface, MAX(id) as max_id 
                    FROM wg_bdr 
                    GROUP BY interface
                ) b2 ON b1.interface = b2.interface AND b1.id = b2.max_id
            ")->getResultArray();
            foreach ($bdrLatest as $b) {
                if (isset($b['interface'])) {
                    $bdrMap[strtolower(trim($b['interface']))] = (int)$b['status'];
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error fetching WireGuard statuses for pflist: ' . $e->getMessage());
        }

        $data   = [];
        $no     = $start + 1;
        $baseUrl = base_url();

        foreach ($records as $row) {
            // Use status IP if available, fallback to man_ip from inventory, then dev_lan_ip
            $deviceIp = !empty($row['status_ip']) ? $row['status_ip'] : (!empty($row['man_ip']) ? $row['man_ip'] : (!empty($row['dev_lan_ip']) ? $row['dev_lan_ip'] : '---'));
            
            // Determine true online status using WireGuard tunnel states (matching loggedlive!)
            $wgMpls = !empty($row['wg_mpls']) ? strtolower(trim($row['wg_mpls'])) : null;
            $wgIll  = !empty($row['wg_ill']) ? strtolower(trim($row['wg_ill'])) : null;
            $wgBdr  = !empty($row['wg_bdr']) ? strtolower(trim($row['wg_bdr'])) : null;

            $mplsStatus = ($wgMpls && isset($mplsMap[$wgMpls])) ? $mplsMap[$wgMpls] : null;
            $illStatus  = ($wgIll && isset($illMap[$wgIll])) ? $illMap[$wgIll] : null;
            $bdrStatus  = ($wgBdr && isset($bdrMap[$wgBdr])) ? $bdrMap[$wgBdr] : null;

            // Topology map uses WireGuard tunnel status as the primary indicator for branch reachability.
            $hasWgConfig = ($wgMpls || $wgIll || $wgBdr);
            $wgOnline = false;

            if ($hasWgConfig) {
                // Device is ONLINE if any configured NDC or BDR tunnel is UP (status == 1)
                $wgOnline = (
                    ($wgMpls && $mplsStatus === 1) || 
                    ($wgIll && $illStatus === 1) ||
                    ($wgBdr && $bdrStatus === 1)
                );
            }

            // Check if agent telemetry was reported recently.
            // The agent cron runs once per hour (at :05), so we allow up to 75 minutes (4500s)
            // before declaring a device offline via the agent heartbeat path.
            // WireGuard tunnel status ($wgOnline) is the primary signal; this is the fallback.
            $agentOnline = false;
            $secondsAgo = isset($row['status_seconds_ago']) ? (int)$row['status_seconds_ago'] : null;
            if ($secondsAgo !== null && $secondsAgo >= -60 && $secondsAgo <= 4500) {
                $agentOnline = true;
            } else {
                $statusUpdated = $row['status_updated_at'] ?? $row['updated_at'] ?? '';
                if (!empty($statusUpdated)) {
                    try {
                        $dbTime = \CodeIgniter\I18n\Time::parse($statusUpdated, 'Asia/Kolkata')->getTimestamp();
                        $diff = abs(time() - $dbTime);
                        if ($diff <= 4500) {
                            $agentOnline = true;
                        }
                    } catch (\Exception $e) {}
                }
            }

            // Broader status-string fallback: if status is Synced/Running and telemetry is
            // within 90 minutes (5400s), trust the stored status value.
            $statusVal = strtolower(trim((string)($row['status'] ?? '')));
            if (!$agentOnline && in_array($statusVal, ['online', '1', 'synced', 'running']) && $secondsAgo !== null && $secondsAgo <= 5400) {
                $agentOnline = true;
            }

            $isOnline = ($wgOnline || $agentOnline);

            // 0: Serial Number (#)
            $colIndexHtml = '<span class="badge-index">' . ($no++) . '</span>';

            // 1: Status & Tunnel
            if (!empty($row['bypass']) && $row['bypass'] == 1) {
                $statusClass = 'status-bypassed';
                $statusLabel = 'BYPASSED';
                $statusIcon = 'fa-shield-alt';
            } elseif ($isOnline) {
                $statusClass = 'status-online';
                $statusLabel = 'ONLINE';
                $statusIcon = 'fa-circle';
            } else {
                $statusClass = 'status-offline';
                $statusLabel = 'OFFLINE';
                $statusIcon = 'fa-circle';
            }

            $statusHtml = '<div class="status-col text-center">'
                        . '<span class="status-beacon ' . $statusClass . '">'
                        . '<i class="fas ' . $statusIcon . ' mr-1"></i>' . $statusLabel
                        . '</span>';

            // All Configured WireGuard & BDR Tunnels with UP / DOWN status indicators
            $allTunnels = [];
            if ($wgMpls) {
                $isUp = ($mplsStatus === 1);
                $allTunnels[] = [
                    'label' => strtoupper(trim($row['wg_mpls'])),
                    'type'  => 'MPLS',
                    'is_up' => $isUp,
                    'class' => $isUp ? 'tunnel-mpls' : 'tunnel-none',
                ];
            }
            if ($wgIll) {
                $isUp = ($illStatus === 1);
                $allTunnels[] = [
                    'label' => strtoupper(trim($row['wg_ill'])),
                    'type'  => 'ILL',
                    'is_up' => $isUp,
                    'class' => $isUp ? 'tunnel-ill' : 'tunnel-none',
                ];
            }
            if ($wgBdr) {
                $isUp = ($bdrStatus === 1);
                $allTunnels[] = [
                    'label' => strtoupper(trim($row['wg_bdr'])),
                    'type'  => 'BDR',
                    'is_up' => $isUp,
                    'class' => $isUp ? 'tunnel-bdr' : 'tunnel-none',
                ];
            }

            if (!empty($allTunnels)) {
                $statusHtml .= '<div class="tunnel-tags-wrap mt-1">';
                foreach ($allTunnels as $t) {
                    $stateText = $t['is_up'] ? 'UP' : 'DOWN';
                    $iconClass = $t['is_up'] ? 'fa-shield-alt' : 'fa-times-circle';
                    $statusHtml .= '<span class="tunnel-tag ' . $t['class'] . '" title="' . $t['type'] . ' WireGuard: ' . esc($t['label']) . ' (' . $stateText . ')">'
                                 . '<i class="fas ' . $iconClass . ' mr-1"></i>' . esc($t['label'])
                                 . '</span>';
                }
                $statusHtml .= '</div>';
            } elseif (!empty($row['active_tunnel']) && !in_array($row['active_tunnel'], ['tun_wg1', 'tun_wg0', '---', 'N.A.'])) {
                $statusHtml .= '<div class="tunnel-tags-wrap mt-1">'
                             . '<span class="tunnel-tag tunnel-generic" title="Tunnel: ' . esc($row['active_tunnel']) . '">'
                             . '<i class="fas fa-shield-alt mr-1"></i>' . esc($row['active_tunnel'])
                             . '</span></div>';
            }

            // Header-pack verification
            $bannerValue = array_key_exists('banner', $row) ? $row['banner'] : null;
            if ($bannerValue !== null && $bannerValue !== '' && (string) $bannerValue !== '1') {
                $statusHtml .= '<div class="header-warn-tag mt-1" title="The agent re-applied the pfCentral header pack on its last check-in and the flashing banner is still missing from head.inc.">'
                             . '<i class="fas fa-exclamation-triangle mr-1"></i> NO HEADER</div>';
            }
            $statusHtml .= '</div>';

            // 2: Device & Branch (Name, Location, Hostname, Notes)
            $nameVal = $row['name'] ?? '---';
            $devHostname = !empty($row['hostname']) ? $row['hostname'] : (!empty($row['dev_hostname']) ? $row['dev_hostname'] : '');
            $locName = trim($row['location_name'] ?? '');
            $notes = trim($row['notes'] ?? '');

            $deviceHtml = '<div class="device-cell">'
                        . '<div class="device-title-row">'
                        . '<a href="' . esc($baseUrl) . 'pfsense/pfdetails/' . esc($row['id']) . '" class="branch-name-link" title="Open Device Details: ' . esc($nameVal) . '">'
                        . '<i class="fas fa-server branch-icon"></i>' . esc($nameVal)
                        . '</a>';

            if (!empty($notes)) {
                $deviceHtml .= '<span class="notes-badge ml-auto" title="' . esc($notes) . '"><i class="fas fa-sticky-note"></i></span>';
            }

            $deviceHtml .= '</div>';

            if (!empty($locName) && $locName !== '---' && strcasecmp($locName, trim($nameVal)) !== 0) {
                $deviceHtml .= '<div class="device-meta-loc" title="Location: ' . esc($locName) . '">'
                            . '<i class="fas fa-map-marker-alt text-danger mr-1"></i>' . esc($locName)
                            . '</div>';
            }

            if (!empty($devHostname)) {
                $deviceHtml .= '<div class="device-meta-host" title="Hostname: ' . esc($devHostname) . '">'
                            . '<i class="fas fa-terminal text-muted mr-1"></i>' . esc($devHostname)
                            . '</div>';
            }
            $deviceHtml .= '</div>';

            // 3: Network & Uplinks (LAN IP with Ping/Trace, MPLS, ILL, WAN chips)
            $lanIp = !empty($deviceIp) && $deviceIp !== '---' ? $deviceIp : (!empty($row['man_ip']) ? $row['man_ip'] : '');
            $mplsVal = !empty($row['mpls_ip']) ? $row['mpls_ip'] : (!empty($row['dev_mpls_ip']) ? $row['dev_mpls_ip'] : '');
            $illVal  = !empty($row['ill_ip']) ? $row['ill_ip'] : (!empty($row['dev_ill_ip']) ? $row['dev_ill_ip'] : '');
            $wanVal  = trim($row['wan_ip'] ?? '');

            $isPingable = function($ip) {
                $ip = trim($ip ?? '');
                return !empty($ip) && !in_array(strtoupper($ip), ['---', '...', 'N.A.', 'NA', 'N/A', 'NONE']);
            };

            $networkHtml = '<div class="network-cell">';

            if (!empty($lanIp) && $isPingable($lanIp)) {
                $safeLan = esc($lanIp);
                $networkHtml .= '<div class="lan-row">'
                             . '<span class="lan-ip-badge" title="LAN / Management IP"><i class="fas fa-network-wired text-info mr-1"></i>' . $safeLan . '</span>'
                             . '<div class="lan-tools">'
                             . '<button type="button" class="btn-tool btn-tool-ping" data-toggle="modal" data-target="#pingModal" onclick="startPing(\'' . addslashes($lanIp) . '\')" title="Ping LAN IP"><i class="fas fa-bolt"></i> Ping</button>'
                             . '<button type="button" class="btn-tool btn-tool-trace" data-toggle="modal" data-target="#traceModal" onclick="startTrace(\'' . addslashes($lanIp) . '\')" title="Trace LAN IP"><i class="fas fa-route"></i> Trace</button>'
                             . '</div>'
                             . '</div>';
            } else {
                $networkHtml .= '<div class="text-muted" style="font-size:11px;font-style:italic;">No LAN IP Configured</div>';
            }

            $uplinkChips = [];
            if ($isPingable($mplsVal)) {
                $uplinkChips[] = '<span class="uplink-pill uplink-mpls" title="MPLS: ' . esc($mplsVal) . '">'
                               . '<span class="uplink-label">MPLS</span><span class="uplink-val">' . esc($mplsVal) . '</span>'
                               . '<button type="button" class="uplink-ping-btn" data-toggle="modal" data-target="#pingModal" onclick="startPing(\'' . addslashes($mplsVal) . '\')" title="Ping MPLS"><i class="fas fa-bolt"></i></button>'
                               . '</span>';
            }
            if ($isPingable($illVal)) {
                $uplinkChips[] = '<span class="uplink-pill uplink-ill" title="ILL: ' . esc($illVal) . '">'
                               . '<span class="uplink-label">ILL</span><span class="uplink-val">' . esc($illVal) . '</span>'
                               . '<button type="button" class="uplink-ping-btn" data-toggle="modal" data-target="#pingModal" onclick="startPing(\'' . addslashes($illVal) . '\')" title="Ping ILL"><i class="fas fa-bolt"></i></button>'
                               . '</span>';
            }
            if ($isPingable($wanVal)) {
                $uplinkChips[] = '<span class="uplink-pill uplink-wan" title="WAN: ' . esc($wanVal) . '">'
                               . '<span class="uplink-label">WAN</span><span class="uplink-val">' . esc($wanVal) . '</span>'
                               . '<button type="button" class="uplink-ping-btn" data-toggle="modal" data-target="#pingModal" onclick="startPing(\'' . addslashes($wanVal) . '\')" title="Ping WAN"><i class="fas fa-bolt"></i></button>'
                               . '</span>';
            }

            if (!empty($uplinkChips)) {
                $networkHtml .= '<div class="uplinks-row">' . implode('', $uplinkChips) . '</div>';
            }
            $networkHtml .= '</div>';

            // 4: System Health (OS version, Uptime, CPU, RAM, Disk)
            $osName = !empty($row['os_name']) ? esc($row['os_name']) : (!empty($row['platform']) ? esc($row['platform']) : '---');
            $pfVersion = '';
            if (!empty($row['build_identifier']) && trim($row['build_identifier']) !== '' && trim($row['build_identifier']) !== '...') {
                $pfVersion = trim($row['build_identifier']);
            } elseif (!empty($row['os_version']) && trim($row['os_version']) !== '' && strtolower(trim($row['os_version'])) !== 'pfsense' && trim($row['os_version']) !== '...') {
                $pfVersion = trim($row['os_version']);
            }

            $osBadge = '';
            if (!empty($pfVersion)) {
                $prefix = (stripos($pfVersion, 'v') === 0 || stripos($pfVersion, 'pfSense') === 0) ? '' : 'pfSense ';
                $osBadge = '<span class="os-pill" title="OS Version"><i class="fab fa-freebsd mr-1"></i>' . esc($prefix . $pfVersion) . '</span>';
            } elseif (!empty($osName) && $osName !== '---') {
                $osBadge = '<span class="os-pill">' . esc($osName) . '</span>';
            }

            $uptimeHtml = '';
            if ($isOnline) {
                if (empty($row['uptime_duration']) || $row['uptime_duration'] === '---' || $row['uptime_duration'] === '...') {
                    $uptimeHtml = '<span class="text-success uptime-pill"><i class="fas fa-link mr-1"></i>Connected</span>';
                } else {
                    $rawUptime = trim($row['uptime_duration']);
                    if (preg_match('/up\s+([^,]+(?:,\s*[\d:]+)?)/i', $rawUptime, $upMatch)) {
                        $cleanUptime = trim($upMatch[0]);
                    } else {
                        $uptimeParts = explode('load averages: ', $rawUptime);
                        $cleanUptime = trim($uptimeParts[0]);
                    }
                    $cleanUptime = rtrim($cleanUptime, ', ');
                    $uptimeHtml = '<span class="uptime-pill" title="Uptime: ' . esc($rawUptime) . '"><i class="far fa-clock mr-1 text-muted"></i>' . esc($cleanUptime) . '</span>';
                }
            } else {
                $uptimeHtml = '<span class="text-muted uptime-pill"><i class="far fa-clock mr-1"></i>---</span>';
            }

            $healthHtml = '<div class="health-cell">'
                        . '<div class="health-top-row">' . $osBadge . $uptimeHtml . '</div>';

            $metricChips = [];
            $cpuVal = $row['cpu_usage'] ?? '';
            if (!empty($cpuVal) && is_numeric(str_replace('%', '', $cpuVal))) {
                $cpuNum = (float) str_replace('%', '', $cpuVal);
                $cpuColorClass = $cpuNum > 80 ? 'metric-danger' : ($cpuNum > 50 ? 'metric-warn' : 'metric-ok');
                $metricChips[] = '<span class="metric-chip ' . $cpuColorClass . '" title="CPU: ' . esc($cpuVal) . '">'
                               . '<i class="fas fa-microchip mr-1"></i>' . esc($cpuVal)
                               . '</span>';
            }

            $totalMem = $row['total_memory'] ?? '';
            $availMem = $row['available_memory'] ?? '';
            if (!empty($availMem) && !empty($totalMem)) {
                $metricChips[] = '<span class="metric-chip metric-info" title="RAM: ' . esc($availMem) . ' free / ' . esc($totalMem) . ' total">'
                               . '<i class="fas fa-memory mr-1"></i>' . esc($availMem)
                               . '</span>';
            } elseif (!empty($totalMem)) {
                $metricChips[] = '<span class="metric-chip metric-info" title="Total RAM: ' . esc($totalMem) . '">'
                               . '<i class="fas fa-memory mr-1"></i>' . esc($totalMem)
                               . '</span>';
            }

            if (!empty($row['disk_usage']) && $row['disk_usage'] !== '---') {
                $metricChips[] = '<span class="metric-chip metric-secondary" title="Disk: ' . esc($row['disk_usage']) . '">'
                               . '<i class="fas fa-hdd mr-1"></i>' . esc($row['disk_usage'])
                               . '</span>';
            }

            if (!empty($metricChips)) {
                $healthHtml .= '<div class="metrics-row">' . implode('', $metricChips) . '</div>';
            }
            $healthHtml .= '</div>';

            // 5: Last Synced
            $syncHtml = '<div class="sync-cell text-center">';
            $statusUpdated = $row['status_updated_at'] ?? $row['updated_at'] ?? '';
            if (!empty($statusUpdated)) {
                try {
                    $date = new \DateTime($statusUpdated, new \DateTimeZone('Asia/Kolkata'));
                    $timeStr = $date->format('h:i:s A');
                    $dateStr = $date->format('d/m/Y');
                    $secondsAgo = isset($row['status_seconds_ago']) ? (int)$row['status_seconds_ago'] : null;

                    if ($secondsAgo !== null) {
                        if ($secondsAgo < 90) {
                            $syncHtml .= '<span class="sync-badge sync-fresh">Just now</span>';
                        } elseif ($secondsAgo < 3600) {
                            $mins = max(1, round($secondsAgo / 60));
                            $syncHtml .= '<span class="sync-badge sync-fresh">' . $mins . 'm ago</span>';
                        } elseif ($secondsAgo < 86400) {
                            $hrs = round($secondsAgo / 3600);
                            $syncHtml .= '<span class="sync-badge sync-mid">' . $hrs . 'h ago</span>';
                        } else {
                            $days = round($secondsAgo / 86400);
                            $syncHtml .= '<span class="sync-badge sync-old">' . $days . 'd ago</span>';
                        }
                    }

                    $syncHtml .= '<div class="sync-time">' . $timeStr . '</div>'
                               . '<div class="sync-date">' . $dateStr . '</div>';
                } catch (\Exception $e) {
                    $syncHtml .= '<span class="text-muted">---</span>';
                }
            } else {
                $syncHtml .= '<span class="text-muted">---</span>';
            }
            $syncHtml .= '</div>';

            // 6: Actions
            // Resolve LibreNMS & Grafana Links
            $librenmsLink = '';
            if (!empty($librenmsApiUrl) && !empty($deviceIp) && $deviceIp !== '---'
                && \App\Support\Monitoring::librenmsEnabled()) {
                $librenmsBaseUrl = str_replace('/api/v0', '', $librenmsApiUrl);
                $librenmsBaseUrl = rtrim($librenmsBaseUrl, '/') . '/';
                $librenmsLink = $librenmsBaseUrl . 'device/device=' . urlencode($deviceIp);
            }

            $grafanaLink = '';
            if (!empty($grafanaUrl) && !empty($deviceIp) && $deviceIp !== '---'
                && \App\Support\Monitoring::grafanaEnabled()) {
                $grafanaBaseUrl = rtrim($grafanaUrl, '/') . '/';
                $grafanaLink = $grafanaBaseUrl . "dashboards?query=" . urlencode($deviceIp);
            }

            $actionHtml = '<div class="action-cell d-flex align-items-center justify-content-center">'
                        . '<button type="button" class="btn btn-sm btn-pf-action action-btn"'
                        . ' data-id="' . esc($row['id']) . '"'
                        . ' data-name="' . esc($row['name'] ?? '') . '" title="Device Actions">'
                        . '<img src="' . $baseUrl . 'assets/images/pfsense_zn.png" alt="pf" width="16" class="mr-1"> Actions</button>'
                        . '<button type="button" class="btn btn-sm btn-icon-action btn-icon-wg"'
                        . ' onclick="openPfDeviceCrud(\'' . esc(addslashes($row['location_name'] ?? $row['name'] ?? '')) . '\');"'
                        . ' title="Device Configuration"><i class="fas fa-network-wired"></i></button>'
                        . '<button type="button" class="btn btn-sm btn-icon-action btn-icon-creds viewPassBtn"'
                        . ' data-id="' . esc($row['id']) . '" title="View Credentials"><i class="fas fa-key"></i></button>';

            if (!empty($librenmsLink)) {
                $actionHtml .= '<a href="' . esc($librenmsLink) . '" target="_blank" class="btn btn-sm btn-icon-action btn-icon-nms" title="Open LibreNMS Dashboard">'
                             . '<i class="fas fa-chart-bar"></i></a>';
            }

            if (!empty($grafanaLink)) {
                $actionHtml .= '<a href="' . esc($grafanaLink) . '" target="_blank" class="btn btn-sm btn-icon-action btn-icon-grafana" title="Open Grafana Telemetry">'
                             . '<i class="fas fa-tv"></i></a>';
            }
            $actionHtml .= '</div>';

            $data[] = [
                $colIndexHtml,
                $statusHtml,
                $deviceHtml,
                $networkHtml,
                $healthHtml,
                $syncHtml,
                $actionHtml,
            ];
        }

        return $this->response->setJSON([
            'draw'            => $draw,
            'recordsTotal'    => $totalAllRecords,
            'recordsFiltered' => $totalRecordsWithFilter,
            'data'            => $data,
        ]);
    }

    public function search()
{
    $searchQuery = $this->request->getPost('search_query');

    // Perform the search query on pfInventoryModel
    $data['pf'] = $this->pfInventoryModel->like('name', $searchQuery)
                                         ->orLike('ip', $searchQuery)
                                         ->orLike('location', $searchQuery)
                                         ->findAll();

    // Fetch all records from PfStatusModel
    $statusRecords = $this->PfStatusModel->findAll();

    // Create a map of pf_id to PfStatusModel records for quick lookup
    $statusMap = [];
    foreach ($statusRecords as $statusRecord) {
        $statusMap[$statusRecord['pf_id']] = $statusRecord;
    }

    // Fetch all locations and create a mapping of id → location name
    $locations = $this->LocationModel->findAll();
    $locationMap = [];
    foreach ($locations as $location) {
        $locationMap[$location['id']] = $location['name']; // Assuming 'name' is the column storing location names
    }

    // Merge PfStatusModel data with pfInventoryModel records
    foreach ($data['pf'] as &$row) {
        // Fetch and replace location ID with actual location name
        $row['location'] = $locationMap[$row['location']] ?? 'Unknown Location';

        if (isset($statusMap[$row['id']])) {
            // Merge PfStatusModel fields with pfInventoryModel data. Drop pf_status's own
            // 'id' first so it cannot clobber the pf_inventory primary key.
            $statusData = $statusMap[$row['id']];
            unset($statusData['id']);
            $row = array_merge($row, $statusData);
        } else {
            // Fill default values for fields if no record is found
            $row['ip'] = '...';
            $row['port'] = '...';
            $row['api'] = '...';
            $row['mpls_ip'] = '...';
            $row['ill_ip'] = '...';
            $row['ssh_port'] = '...';
            $row['wan_ip'] = '...';
            $row['status'] = '...';
            $row['lightsquid_port'] = '...';
            $row['darkstat_port'] = '...';
            $row['ntop_port'] = '...';
            $row['os_name'] = '...';
            $row['os_version'] = '...';
            $row['hostname'] = '...';
            $row['build_identifier'] = '...';
            $row['uptime_duration'] = '...';
            $row['users'] = '...';
            $row['load_averages'] = '...';
            $row['ping_latency'] = '...';
            $row['cpu_usage'] = '...';
            $row['total_memory'] = '...';
            $row['available_memory'] = '...';
            $row['disk_usage'] = '...';
            $row['active_tunnel'] = '...';
            $row['banner'] = null;
        }
    }

    // Load only the search results view
    return view('listmode/search_results', $data);
}
    
        // echo view('frontend/header', $data);
        // echo view('frontend/sidebar', $data);
        // echo view('listmode/search_results', $data);
        // echo view('frontend/footer', $data);

    /**
     * Agent phone-home telemetry ingestion receiver (POST /endpoint).
     * Delegated to DeviceTelemetryService.
     */
    public function endpoint()
    {
        $rawInput = file_get_contents("php://input");
        $result = $this->telemetryService->processRawPayload($rawInput ?: '');
        return $this->response->setStatusCode($result['statusCode'])->setJSON($result['body']);
    }

    /**
     * Identity lookup for the pfCentral header pack (add_header.sh on devices).
     * Delegated to DeviceCredentialService.
     */
    public function headerIdentity()
    {
        $deviceKey = $this->request->getHeaderLine('DEVICEKEY');
        $requestIp = $this->request->getHeaderLine('X-Device-IP') ?: $this->request->getIPAddress();
        $requestHostname = $this->request->getHeaderLine('X-Device-Hostname');
        $requestBranchCode = $this->request->getHeaderLine('X-Branch-Code') ?: $this->request->getGet('branchcode');

        $result = $this->credentialService->getHeaderIdentity($deviceKey, $requestIp, $requestHostname, $requestBranchCode);
        return $this->response->setStatusCode($result['statusCode'])->setJSON($result['body']);
    }

    /**
     * Moves an existing inventory row onto a newly minted device key.
     * Delegated to DeviceCredentialService.
     */
    public function rebindDeviceKey()
    {
        $oldKey = trim($this->request->getHeaderLine('DEVICEKEY'));
        $newKey = strtolower(trim($this->request->getHeaderLine('NEWDEVICEKEY')));
        $result = $this->credentialService->rebindDeviceKey($oldKey, $newKey);
        return $this->response->setStatusCode($result['statusCode'])->setJSON($result['body']);
    }

    /**
     * Backup ingestion receiver (POST /backup).
     * Delegated to PfSenseBackupService.
     */
    public function backup()
    {
        $encodedXmlData = (string) $this->request->getBody();
        $deviceKey      = (string) $this->request->getHeaderLine('DEVICEKEY');
        $result = $this->backupService->storeBackup($encodedXmlData, $deviceKey);
        return $this->response->setStatusCode($result['statusCode'])->setJSON($result['body']);
    }

    /**
     * Backward-compatible static forwarders for backup utilities.
     */
    public static function resolveBackupPath(array $backup, ?string $deviceName = null): ?string
    {
        return PfSenseBackupService::resolveBackupPath($backup, $deviceName);
    }

    public static function formatBytes($bytes, $precision = 1): string
    {
        return PfSenseBackupService::formatBytes($bytes, $precision);
    }

    public static function buildUnifiedDiff(array $lines1, array $lines2, int $maxChanges = 400): array
    {
        return PfSenseBackupService::buildUnifiedDiff($lines1, $lines2, $maxChanges);
    }

    /**
     * View backups for a specific device.
     */
    public function viewDeviceBackups($deviceId)
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        $appName    = $this->CoreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->CoreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl     = $this->CoreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';

        $device = $this->pfInventoryModel->find($deviceId);
        $isOnline = false;
        if ($device) {
            $statusRecord = $this->PfStatusModel->where('pf_id', $deviceId)->first();
            if ($statusRecord) {
                unset($statusRecord['id']);
                $device = array_merge($device, $statusRecord);
            }

            // Enrich with Location & WireGuard details to determine online state accurately
            $locationId = $device['location'] ?? null;
            $location = null;
            if ($locationId) {
                $location = $this->LocationModel->find($locationId);
            }
            if (!$location && !empty($device['name'])) {
                $location = $this->LocationModel->where('TRIM(name)', trim($device['name']))->first();
            }

            $wgMpls = !empty($location['wg_mpls']) ? strtolower($location['wg_mpls']) : null;
            $wgIll = !empty($location['wg_ill']) ? strtolower($location['wg_ill']) : null;
            $wgBdr = !empty($location['wg_bdr']) ? strtolower($location['wg_bdr']) : null;

            $mplsStatus = 0;
            $illStatus = 0;
            $bdrStatus = 0;

            if ($wgMpls) {
                $latestMpls = $this->WgMplsModel->where('interface', $wgMpls)->orderBy('updated_at', 'DESC')->first();
                $mplsStatus = $latestMpls ? (int)($latestMpls['status'] ?? 0) : 0;
            }
            if ($wgIll) {
                $latestIll = $this->WgIllModel->where('interface', $wgIll)->orderBy('updated_at', 'DESC')->first();
                $illStatus = $latestIll ? (int)($latestIll['status'] ?? 0) : 0;
            }
            if ($wgBdr) {
                $latestBdr = $this->WgBdrModel->where('interface', $wgBdr)->orderBy('updated_at', 'DESC')->first();
                $bdrStatus = $latestBdr ? (int)($latestBdr['status'] ?? 0) : 0;
            }

            $hasWgConfig = ($wgMpls || $wgIll || $wgBdr);
            if ($hasWgConfig) {
                $isOnline = ($mplsStatus === 1 || $illStatus === 1 || $bdrStatus === 1);
            }

            // Fallback: active tunnel or synced/online status
            if (!$isOnline) {
                $activeTunnel = strtolower(trim((string)($device['active_tunnel'] ?? '')));
                if ($activeTunnel !== '' && $activeTunnel !== 'none') {
                    $isOnline = true;
                } elseif (isset($device['status']) && in_array(strtolower((string)$device['status']), ['1', 'online', 'synced'])) {
                    $isOnline = true;
                }
            }

            if ($location) {
                $device['location_name'] = $location['name'] ?? '';
            }
            $device['mpls_status'] = $mplsStatus;
            $device['ill_status'] = $illStatus;
            $device['bdr_status'] = $bdrStatus;
            $device['is_online'] = $isOnline;
        }

        $deviceName         = $device['name'] ?? ('Device #' . $deviceId);
        $data['title']       = strip_tags($appName) . " 🔸 Backup Archives - {$deviceName}";
        $data['app_name']    = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url']     = $appUrl;
        $data['device']      = $device;
        $data['deviceId']    = $deviceId;
        $data['isOnline']    = $isOnline;

        $backupInfo      = $this->backupService->getDeviceBackupsWithStats($deviceId, $device);
        $data['backups'] = $backupInfo['backups'];
        $data['stats']   = $backupInfo['stats'];
        $data['crypto_settings'] = \App\Services\Security\PfSenseCryptoService::getGlobalSettings();

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('functions/view_backup', $data)
            . view('frontend/footer', $data);
    }

    public function downloadBackup($backupId)
    {
        $download = $this->backupService->prepareDownload((int) $backupId);
        if (!$download) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Backup not found or missing from disk.']);
        }

        $cryptoSettings   = \App\Services\Security\PfSenseCryptoService::getGlobalSettings();
        $isPlainRequested = ($this->request->getGet('plain') === '1') && $cryptoSettings['allow_plain'];

        if ($cryptoSettings['enabled'] && ! $isPlainRequested) {
            $rawContent = @file_get_contents($download['realPath']);
            if ($rawContent !== false) {
                $encrypted = \App\Services\Security\PfSenseCryptoService::encryptConfig($rawContent, $cryptoSettings['password']);
                return $this->response
                    ->setHeader('Content-Type', 'application/xml')
                    ->setHeader('Content-Disposition', 'attachment; filename="' . $download['downloadFileName'] . '"')
                    ->setBody($encrypted);
            }
        }

        return $this->response->download($download['realPath'], null)->setFileName($download['downloadFileName']);
    }

    public function purgeBackups($deviceId)
    {
        $keep   = (int) $this->request->getPost('keep');
        $result = $this->backupService->purgeBackups((int) $deviceId, $keep);

        if ($result['purged'] > 0) {
            session()->setFlashdata('success', $result['message']);
        } else {
            session()->setFlashdata('info', $result['message']);
        }

        return redirect()->to(base_url("pfsense/viewbackup/$deviceId"));
    }

    public function deleteSingleBackup($backupId)
    {
        $result = $this->backupService->deleteSingleBackup((int) $backupId);
        if (!$result['success']) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => false, 'message' => $result['message']]);
            }
            session()->setFlashdata('error', $result['message']);
            return redirect()->back();
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => $result['message']]);
        }

        session()->setFlashdata('success', $result['message']);
        return redirect()->to(base_url("pfsense/viewbackup/{$result['deviceId']}"));
    }

    public function previewBackup($backupId)
    {
        $result = $this->backupService->previewBackup((int) $backupId);
        return $this->response->setStatusCode($result['statusCode'])->setJSON($result['body']);
    }

    public function diffBackups()
    {
        $backup1Id = (int) $this->request->getPost('backup1_id');
        $backup2Id = (int) $this->request->getPost('backup2_id');
        $result    = $this->backupService->diffBackups($backup1Id, $backup2Id);
        return $this->response->setStatusCode($result['statusCode'])->setJSON($result['body']);
    }

    public function downloadDrPackage($deviceId)
    {
        $dr = $this->backupService->buildDrPackage((int) $deviceId);
        if (!$dr) {
            session()->setFlashdata('error', 'No valid XML configuration snapshot found to generate DR package.');
            return redirect()->to(base_url("pfsense/viewbackup/$deviceId"));
        }

        return $this->response->download($dr['zipPath'], null)->setFileName($dr['zipName']);
    }

    /**
     * Compact lightweight device list for the Global Command Palette (Ctrl+K).
     */
    public function quickDevices()
    {
        $devices = $this->pfInventoryModel->findAll();
        $statuses = $this->PfStatusModel->findAll();
        $statusMap = [];
        foreach ($statuses as $s) {
            $statusMap[$s['pf_id']] = $s;
        }

        $list = [];
        foreach ($devices as $d) {
            $id = $d['id'];
            $st = $statusMap[$id] ?? [];
            $list[] = [
                'id'         => (int) $id,
                'name'       => $d['name'] ?? '',
                'ip'         => $d['ip'] ?? $d['man_ip'] ?? '',
                'port'       => $d['port'] ?? $d['man_port'] ?? '443',
                'platform'   => $d['platform'] ?? 'pfSenseCE',
                'os_version' => $st['os_version'] ?? '',
                'status'     => (string) ($st['status'] ?? '0'),
                'banner'     => (string) ($st['banner'] ?? '0'),
                'groups'     => $d['groups'] ?? '',
                'location'   => $d['location'] ?? '',
            ];
        }

        return $this->response->setJSON($list);
    }
            
    public function add()
    {
        $data = $this->request->getPost();
    
        $existingDevice = $this->pfInventoryModel->where('ip', $data['ip'])->first();
    
        if ($existingDevice) {
            return $this->response->setJSON(['success' => false, 'message' => 'Device with this IP address already exists.']);
        }
    
        if ($this->pfInventoryModel->insert($data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Device added successfully']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to add device.']);
        }
    }
     
    public function edit($id)
    {
        $device = $this->pfInventoryModel->find($id);
    
        if ($device) {
            $statusRecord = $this->PfStatusModel->where('pf_id', $id)->first();
            if ($statusRecord) {
                unset($statusRecord['id']);
                $device = array_merge($device, $statusRecord);
            }
            return $this->response->setJSON($device);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Device not found.']);
        }
    }   

    public function update($id)
    {
        $data = $this->request->getPost();

        // Guard against a nonexistent id: Model::update() matches zero rows and still
        // returns true, which used to report success while saving nothing.
        if (empty($id) || !$this->pfInventoryModel->find($id)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Device not found in inventory. Please refresh the page and try again.']);
        }

        // Map ip and port to man_ip and man_port for pf_inventory database column compatibility
        if (isset($data['ip'])) {
            $data['man_ip'] = $data['ip'];
        }
        if (isset($data['port'])) {
            $data['man_port'] = $data['port'];
        }

        // Handle bypass checkbox state for settings update form
        if (isset($data['name']) || isset($data['ip'])) {
            $data['bypass'] = isset($data['bypass']) ? 1 : 0;
        }
    
        $deviceIp = $data['man_ip'] ?? '';
        
        $existingDevice = null;
        if (!empty($deviceIp)) {
            $existingDevice = $this->pfInventoryModel->where('man_ip', $deviceIp)->where('id !=', $id)->first();
        }
    
        if ($existingDevice) {
            return $this->response->setJSON(['success' => false, 'message' => 'Device with this IP address already exists.']);
        }
    
        if ($this->pfInventoryModel->update($id, $data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Device updated successfully']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to update device.']);
        }
    }
      
    public function delete($id)
    {
        if ($this->pfInventoryModel->delete($id)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Device deleted.']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to delete device.']);
        }
    }

    public function apiview($id)
    {
        $device = $this->pfInventoryModel->find($id);
    
        if ($device) {
            return $this->response->setJSON([
                'success' => true,
                'api' => $device['api']
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Device not found.'
            ]);
        }
    }
    
    public function creds($id)
    {
        $device = $this->pfInventoryModel->find($id);
    
        if ($device) {
            return $this->response->setJSON([
                'success' => true,
                'username' => $device['username'],
                'password' => $device['password']
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Device not found.'
            ]);
        }
    }

    public function ping()
    {
        $ip = $this->request->getVar('ip');
    
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $pingResults = [];
            $pingCommand = '';
    
            if (stristr(PHP_OS, 'win')) {
                $pingCommand = "ping -n 4 " . escapeshellarg($ip);
            } else {
                $pingCommand = "/usr/bin/ping -c 4 " . escapeshellarg($ip);
            }
    
            $result = shell_exec($pingCommand);
    
            if (empty($result)) {
                log_message('error', 'Ping command failed: ' . $pingCommand);
                return $this->response->setStatusCode(500)->setJSON(['error' => 'Ping command failed or is disabled']);
            }
    
            $resultLines = explode("\n", trim($result));
    
            foreach ($resultLines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $pingResults[] = $trimmed;
                }
            }
    
            return $this->response->setJSON($pingResults);
        } else {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid IP address']);
        }
    }

    public function trace()
    {
        $ip = $this->request->getVar('ip');
    
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $traceResults = [];
            $traceCommand = '';
    
            if (stristr(PHP_OS, 'win')) {
                // Windows: Use tracert with -d flag to disable DNS lookup, max 15 hops, 1000ms timeout per hop
                $traceCommand = "tracert -d -h 15 -w 1000 " . escapeshellarg($ip);
            } else {
                // Unix-based: Use traceroute with -n flag, max 15 hops, 1s timeout
                $traceCommand = "/usr/sbin/traceroute -n -m 15 -w 1 " . escapeshellarg($ip);
            }
    
            $result = shell_exec($traceCommand);
    
            if (empty($result)) {
                log_message('error', 'Traceroute command failed: ' . $traceCommand);
                return $this->response->setStatusCode(500)->setJSON(['error' => 'Traceroute command failed or is disabled']);
            }
    
            $resultLines = explode("\n", trim($result));
    
            foreach ($resultLines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $traceResults[] = $trimmed;
                }
            }
    
            return $this->response->setJSON($traceResults);
        } else {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid IP address']);
        }
    }

        
    public function pfopen($id)
    {
        // Fetch pf_id from PfStatusModel where $id is pf_id
        $pfStatus = $this->PfStatusModel->where('pf_id', $id)->first();
    
        if (!$pfStatus) {
            return $this->response->setJSON(['error' => 'PF Status not found.']);
        }
    
        // Fetch ip and port
        $ip = $pfStatus['ip'] ?? null;
        $port = $pfStatus['port'] ?? '443'; // Default to 443 if null
    
        if (!$ip) {
            return $this->response->setJSON(['error' => 'IP not found.']);
        }
    
        // Construct URL
        $url = "https://$ip:$port";
    
        // Send JSON response to be handled by JavaScript (prevents HTTP_REFERER issue)
        return $this->response->setJSON(['url' => $url]);
    }


    public function sshClient($id)
    {
        // Fetch pf_id from PfStatusModel where $id is pf_id
        $pfStatus = $this->PfStatusModel->where('pf_id', $id)->first();
        $pfInventoryModel = $this->pfInventoryModel->where('id', $id)->first();
    
        if (!$pfStatus) {
            return redirect()->back()->with('error', 'PF Status not found.');
        }
    
        // Fetch IP and username from PfStatusModel
        $ip = $pfStatus['ip'] ?? null;
        $username = $pfInventoryModel['username'] ?? 'admin'; // Ensure correct array key usage
        $ssh_port = '55255';
    
        if (!$ip) {
            return redirect()->back()->with('error', 'IP not found.');
        }
    
        // Construct SSH URIs
        $extensionId = 'iodihamcpbpeioajjeobimgagajmlibd';
        $sshUri = "ssh://$username@$ip:$ssh_port";
        $sshUrl = "chrome-extension://$extensionId/html/nassh.html#user=$username@host=$ip:port=$ssh_port";
    
        return view('listmode/ssh_client', ['sshUri' => $sshUri, 'sshUrl' => $sshUrl]);
    }

    
    public function pfsquid($id)
    {
        // Fetch pf_id from PfStatusModel
        $pfStatus = $this->PfStatusModel->where('pf_id', $id)->first();
    
        if (!$pfStatus) {
            return redirect()->back()->with('error', 'PF Status not found.');
        }
    
        // Fetch IP from PfStatusModel
        $ip = $pfStatus['ip'] ?? null;
        $port = $pfStatus['lightsquid_port'] ?? null;
        // $port = '3002';
        $username = $pfStatus['username'] ?? 'admin';
        $password = 'AK0L@urb@N';
    
        if (!$ip) {
            return redirect()->back()->with('error', 'IP not found.');
        }
    
        // Construct URL and redirect
        $url = "https://$username:$password@$ip:$port";
        return redirect()->to($url);
    }
    
    public function bandwidthd($id)
    {
        // Fetch pf_id from PfStatusModel
        $pfStatus = $this->PfStatusModel->where('pf_id', $id)->first();
    
        if (!$pfStatus) {
            return redirect()->back()->with('error', 'PF Status not found.');
        }
    
        // Fetch IP and Port
        $ip = $pfStatus['ip'] ?? null;
        $port = $pfStatus['port'] ?? '443'; // Default to 3001
    
        if (!$ip) {
            return redirect()->back()->with('error', 'IP not found.');
        }
    
        // Construct URL and redirect
        $url = "https://$ip:$port/bandwidthd/index.html";
        return redirect()->to($url);
    }
    
    public function pfdarkstat($id)
    {
        // Fetch pf_id from PfStatusModel
        $pfStatus = $this->PfStatusModel->where('pf_id', $id)->first();
    
        if (!$pfStatus) {
            return redirect()->back()->with('error', 'PF Status not found.');
        }
    
        // Fetch IP and Darkstat Port from PfStatusModel
        $ip = $pfStatus['ip'] ?? null;
        $port = $pfStatus['darkstat_port'] ?? null;
    
        if (!$ip) {
            return redirect()->back()->with('error', 'IP not found.');
        }
    
        if (!$port) {
            return redirect()->back()->with('error', 'Darkstat Port not found.');
        }
    
        // Construct URL and redirect
        $url = "http://$ip:$port/";
        return redirect()->to($url);
    }
    

    public function pfdetails($id)
    {
        // Retrieve inventory details from the database first
        $inventory = $this->pfInventoryModel->find($id);
        if (!$inventory) {
            log_message('error', "Firewall with ID $id not found in the inventory.");
            throw new \CodeIgniter\Exceptions\PageNotFoundException("Firewall configuration not found in inventory.");
        }
    
        // Retrieve status details if available
        $firewall = $this->PfStatusModel->where('pf_id', $id)->first() ?? [];
        
        log_message('debug', "Firewall {$id}: inventory row #" . ($inventory['id'] ?? 'n/a') . ', status row #' . ($firewall['id'] ?? 'none') . '.');
    
        // Initialize data array
        $data = [
            'gateways'         => [],
            'services'         => [],
            'systems'          => [],
            'wireguard_peers'  => [],
            'interfaces'       => [],
            'firewall_logs'    => [],
            'system_logs'      => [],
            'error'            => null,
            'telemetry_source' => 'none',
        ];
    
        // Merge inventory fields with $data
        $data = array_merge($data, $inventory);
        $data['status_data'] = $firewall;
        
        // Expose location name instead of ID
        $locRecord = $this->LocationModel->find($data['location'] ?? null);
        $data['location_name'] = $locRecord ? $locRecord['name'] : 'Unknown';
    
        // Use status IP if available, fallback to manual/inventory IP
        $deviceIp = !empty($inventory['man_ip']) ? $inventory['man_ip'] : (!empty($firewall['ip']) ? $firewall['ip'] : (!empty($inventory['ip']) ? $inventory['ip'] : ''));
        $data['ip'] = $deviceIp;
        
        $port = !empty($inventory['man_port']) ? $inventory['man_port'] : (!empty($firewall['port']) ? $firewall['port'] : '443');
        $data['port'] = $port;

        // Fetch backups summary for this device
        $data['backup_count'] = $this->PfBakupModel->where('pf_id', $id)->countAllResults();
        $data['latest_backup'] = $this->PfBakupModel->where('pf_id', $id)->orderBy('created_at', 'DESC')->first();
        
        $logLimit = 50;
        $isValidIp = !empty($deviceIp) && filter_var($deviceIp, FILTER_VALIDATE_IP);
        $isPortReachable = false;

        if ($isValidIp) {
            // Fast socket probe (0.8s) before attempting 8 separate HTTP calls to avoid multi-minute freezes
            $probe = @stream_socket_client("tcp://{$deviceIp}:{$port}", $errno, $errstr, 0.8);
            if ($probe) {
                $isPortReachable = true;
                fclose($probe);
            }
        }
        
        if ($isValidIp && $isPortReachable) {
            // Define API endpoints
            $apiUrls = [
                'gateways'        => "https://{$deviceIp}:$port/api/v2/status/gateways",
                'services'        => "https://{$deviceIp}:$port/api/v2/status/services",
                'systems'         => "https://{$deviceIp}:$port/api/v2/status/system",
                'wireguard_peers' => "https://{$deviceIp}:$port/api/v2/vpn/wireguard/peers",
                'interfaces'      => "https://{$deviceIp}:$port/api/v2/status/interfaces",
                'firewall_logs'   => "https://{$deviceIp}:$port/api/v2/status/logs/firewall?limit=$logLimit&sort_order=SORT_ASC",
                'system_logs'     => "https://{$deviceIp}:$port/api/v2/status/logs/system?limit=$logLimit&sort_order=SORT_ASC"
            ];
            
            try {
                // Attempt to call APIs
                foreach ($apiUrls as $key => $url) {
                    try {
                        $apiResponse = $this->callApi($url, $data['username'], $data['password']);
                        if (isset($apiResponse['error'])) {
                            log_message('debug', "API request failed for URL: $url. Error: " . $apiResponse['error']);
                            $data[$key][$id] = []; 
                            continue;
                        }
        
                        if (!empty($id) && isset($apiResponse['data'])) {
                            $data[$key][$id] = $apiResponse['data'];
                        } else {
                            $data[$key][$id] = []; 
                        }
                    } catch (\Exception $e) {
                        log_message('debug', "Exception in API call for URL: $url. Error: " . $e->getMessage());
                        $data[$key][$id] = []; 
                    }
                }

                if (!empty($data['systems'][$id])) {
                    $data['telemetry_source'] = 'live';
                }
        
                // Process WireGuard peers status
                if (!empty($data['wireguard_peers'][$id])) {
                    foreach ($data['wireguard_peers'][$id] as &$peer) {
                        $peer['status'] = isset($peer['enabled']) && $peer['enabled'] ? 'up' : 'down';
                        $peer['status_label'] = ($peer['status'] == 'up') ? 'Up' : 'Down';
                    }
                }
        
                // Fetch SSH port
                $sshUrl = "https://{$deviceIp}:$port/api/v2/services/ssh";
                $sshResponse = $this->callApi($sshUrl, $data['username'], $data['password']);
                $sshPort = $sshResponse['data']['port'] ?? ($firewall['ssh_port'] ?? '22');
        
                // Fetch MPLS and ILL IPs from interfaces
                $mplsIp = $firewall['mpls_ip'] ?? '';
                $illIp = $firewall['ill_ip'] ?? '';
                if (!empty($data['interfaces'][$id])) {
                    foreach ($data['interfaces'][$id] as $interface) {
                        if (($interface['descr'] ?? '') === 'MPLS') {
                            $mplsIp = $interface['ipaddr'] ?? $mplsIp;
                        }
                        if (($interface['descr'] ?? '') === 'ILL') {
                            $illIp = $interface['ipaddr'] ?? $illIp;
                        }
                    }
                }
        
                // Update or Insert the pf_status telemetry cache record
                $existingRecord = $this->PfStatusModel->where('pf_id', $id)->first();
                $statusData = [
                    'ssh_port' => $sshPort,
                    'mpls_ip'  => $mplsIp,
                    'ill_ip'   => $illIp,
                    'status'   => 'online',
                    'ip'       => $deviceIp,
                    'port'     => $port
                ];
                
                // If we also got system telemetry from 'systems' API, update status columns
                if (!empty($data['systems'][$id])) {
                    $sys = $data['systems'][$id];
                    // Map cpu usage
                    $cpu = $sys['cpu_usage'] ?? '0';
                    $statusData['cpu_usage'] = $cpu . '%';
                    
                    // Map ram usage
                    $statusData['total_memory'] = $sys['total_mem'] ?? '';
                    $statusData['available_memory'] = $sys['avail_mem'] ?? '';
                    
                    // Map disk usage
                    $statusData['disk_usage'] = $sys['disk_usage'] ?? '';
                    
                    // Map hostname & os
                    $statusData['hostname'] = $sys['hostname'] ?? '';
                    $statusData['os_name'] = $sys['os_name'] ?? 'pfSense';
                    $statusData['os_version'] = $sys['os_version'] ?? '';
                    $statusData['uptime_duration'] = $sys['uptime'] ?? '';
                }
        
                if ($existingRecord) {
                    $this->PfStatusModel->update($existingRecord['id'], $statusData);
                } else {
                    $statusData['pf_id'] = $id;
                    $this->PfStatusModel->insert($statusData);
                }
            } catch (\Exception $e) {
                log_message('error', 'Error in pfdetails API sync: ' . $e->getMessage());
            }
        } elseif (!$isValidIp) {
            $data['error'] = "Firewall IP address is invalid or empty. Please set a valid IP address first.";
        } else {
            // Port unreachable directly from web server
            $data['error'] = "Direct REST API at {$deviceIp}:{$port} is currently offline or filtered. Showing cached telemetry.";
        }

        // ==========================================
        // SYNTHESIZE / FALLBACK TO CACHED TELEMETRY
        // ==========================================
        if (empty($data['systems'][$id]) && !empty($firewall)) {
            // Parse CPU
            $rawCpu = $firewall['cpu_usage'] ?? '0';
            $cpuPercent = 0;
            if (strpos($rawCpu, '{') !== false) {
                $decoded = json_decode($rawCpu, true);
                if (is_array($decoded)) {
                    $cpuPercent = intval($decoded['user'] ?? $decoded['cpu'] ?? 0);
                }
            } elseif (preg_match('/(\d+)/', $rawCpu, $m)) {
                $cpuPercent = intval($m[1]);
            }

            // Parse Memory
            $totalMemMb = intval($firewall['total_memory'] ?? 0);
            $availMemMb = intval($firewall['available_memory'] ?? 0);
            $memPercent = 0;
            if ($totalMemMb > 0) {
                $usedMemMb = max(0, $totalMemMb - $availMemMb);
                $memPercent = round(($usedMemMb / $totalMemMb) * 100);
            }
            $totalMemStr = $totalMemMb >= 1024 ? round($totalMemMb / 1024, 1) . ' GB' : ($totalMemMb > 0 ? $totalMemMb . ' MB' : 'N/A');
            $availMemStr = $availMemMb >= 1024 ? round($availMemMb / 1024, 1) . ' GB' : ($availMemMb > 0 ? $availMemMb . ' MB' : 'N/A');

            // Parse Disk
            $rawDisk = $firewall['disk_usage'] ?? '0';
            $diskPercent = 0;
            if (preg_match('/\((\d+)%\)/', $rawDisk, $dm)) {
                $diskPercent = intval($dm[1]);
            } elseif (preg_match('/(\d+)%/', $rawDisk, $dm)) {
                $diskPercent = intval($dm[1]);
            }

            // Parse Uptime & Load
            $uptimeRaw = $firewall['uptime_duration'] ?? 'N/A';
            $cleanUptime = $uptimeRaw;
            if (preg_match('/up\s+([^,]+(?:,\s*[^,]+)?)/i', $uptimeRaw, $um)) {
                $cleanUptime = trim($um[1]);
            }
            $loadAvg = '';
            if (preg_match('/load averages:\s*(.+)$/i', $uptimeRaw, $lm)) {
                $loadAvg = trim($lm[1]);
            } elseif (!empty($firewall['load_averages'])) {
                $loadAvg = $firewall['load_averages'];
            }

            $data['systems'][$id] = [
                'cpu_usage'    => $cpuPercent,
                'mem_usage'    => $memPercent,
                'total_mem'    => $totalMemStr,
                'avail_mem'    => $availMemStr,
                'disk_usage'   => $diskPercent,
                'raw_disk'     => $rawDisk,
                'platform'     => $inventory['platform'] ?? 'pfSenseCE',
                'os_name'      => $firewall['os_name'] ?? 'FreeBSD / pfSense',
                'os_version'   => $firewall['build_identifier'] ?? $firewall['os_version'] ?? '',
                'uptime'       => $cleanUptime,
                'raw_uptime'   => $uptimeRaw,
                'load_avg'     => $loadAvg,
                'hostname'     => $firewall['hostname'] ?? ($inventory['name'] . '.local'),
                'cpu_model'    => !empty($loadAvg) ? ('System Load: ' . $loadAvg) : 'Appliance CPU',
                'ping_latency' => $firewall['ping_latency'] ?? null,
            ];
            $data['telemetry_source'] = 'cached';
        }

        // Active tunnel & IP mappings
        $data['active_tunnel'] = $firewall['active_tunnel'] ?? 'N/A';
        $data['mpls_ip'] = $firewall['mpls_ip'] ?? '';
        $data['ill_ip'] = $firewall['ill_ip'] ?? '';
        $data['wan_ip'] = $firewall['wan_ip'] ?? '';

        // WireGuard peers fallback if empty from API
        if (empty($data['wireguard_peers'][$id])) {
            $fallbackPeers = [];
            if (!empty($data['mpls_ip'])) {
                $isUp = ($data['active_tunnel'] === 'tun_wg0');
                $fallbackPeers[] = [
                    'tun'          => 'tun_wg0',
                    'endpoint'     => $data['mpls_ip'],
                    'port'         => '51820',
                    'enabled'      => true,
                    'status'       => $isUp ? 'up' : 'standby',
                    'status_label' => $isUp ? 'Active Primary' : 'Configured / Standby',
                    'name'         => 'MPLS Primary Tunnel'
                ];
            }
            if (!empty($data['ill_ip'])) {
                $isUp = ($data['active_tunnel'] === 'tun_wg1');
                $fallbackPeers[] = [
                    'tun'          => 'tun_wg1',
                    'endpoint'     => $data['ill_ip'],
                    'port'         => '51821',
                    'enabled'      => true,
                    'status'       => $isUp ? 'up' : 'standby',
                    'status_label' => $isUp ? 'Active Secondary' : 'Configured / Standby',
                    'name'         => 'ILL Secondary Tunnel'
                ];
            }
            if (!empty($fallbackPeers)) {
                $data['wireguard_peers'][$id] = $fallbackPeers;
            }
        }

        // ==========================================
        // BUILD $device COMPATIBLE WITH device_card
        // ==========================================
        $device = $inventory;
        if (!empty($firewall)) {
            $fwCopy = $firewall;
            unset($fwCopy['id']);
            $device = array_merge($device, $fwCopy);
        }

        $defaults = [
            'status'            => '0',
            'lightsquid_port'   => '',
            'darkstat_port'     => '',
            'ntop_port'         => '',
            'bandwidthd'        => '',
            'restapi'           => '',
            'os_name'           => 'pfSense',
            'os_version'        => '',
            'build_identifier'  => '',
            'uptime_duration'   => 'up 0 mins, load averages: 0.00, 0.00, 0.00',
            'cpu_cores'         => '4',
            'available_memory'  => '0',
            'total_memory'      => '0',
            'disk_usage'        => '0/0GB (0%)',
            'active_tunnel'     => 'None',
            'creds_notes'       => '',
            'description'       => ''
        ];

        foreach ($defaults as $key => $val) {
            if (!isset($device[$key]) || $device[$key] === null) {
                $device[$key] = $val;
            }
        }

        $device['location_name'] = $data['location_name'];

        // Fetch pf_devices for this branch
        $devConfig = $this->NewDeviceModel->where('TRIM(br_name)', trim($device['name']))->first();
        if (!$devConfig && !empty($device['location_name'])) {
            $devConfig = $this->NewDeviceModel->where('TRIM(br_name)', trim($device['location_name']))->first();
        }
        if ($devConfig) {
            $device['dev_config'] = $devConfig;
            if ((empty($device['hostname']) || $device['hostname'] === '...') && !empty($devConfig['hostname'])) {
                $device['hostname'] = $devConfig['hostname'];
            }
            if ((empty($device['mpls_ip']) || $device['mpls_ip'] === '...') && !empty($devConfig['mpls_ip'])) {
                $device['mpls_ip'] = $devConfig['mpls_ip'];
            }
            if ((empty($device['ill_ip']) || $device['ill_ip'] === '...') && !empty($devConfig['ill_ip'])) {
                $device['ill_ip'] = $devConfig['ill_ip'];
            }
            if ((empty($device['ip']) || $device['ip'] === '...') && !empty($devConfig['lan_ip'])) {
                $device['ip'] = $devConfig['lan_ip'];
            }
            if (empty($device['description']) && !empty($devConfig['description'])) {
                $device['description'] = $devConfig['description'];
            }
        }
        if (empty($device['description']) && !empty($device['notes'])) {
            $device['description'] = $device['notes'];
        }
        if ((empty($device['os_name']) || $device['os_name'] === '...' || $device['os_name'] === 'pfSense') && !empty($device['platform'])) {
            $device['os_name'] = $device['platform'];
        }

        // Evaluate WireGuard interfaces and status for uplinks
        $loc = $locRecord;
        $wgMpls = $loc ? trim($loc['wg_mpls'] ?? '') : '';
        $wgIll  = $loc ? trim($loc['wg_ill'] ?? '') : '';
        $wgBdr  = $loc ? trim($loc['wg_bdr'] ?? '') : '';

        $isOnline = false;
        $mplsStatus = 0;
        $illStatus = 0;
        $bdrStatus = 0;
        $latestMpls = null;
        $latestIll = null;
        $latestBdr = null;

        if ($loc && ($wgMpls || $wgIll || $wgBdr)) {
            if (!empty($wgMpls)) {
                $latestMpls = $this->WgMplsModel->where('interface', $wgMpls)->orderBy('updated_at', 'DESC')->first();
                $mplsStatus = $latestMpls ? (int)($latestMpls['status'] ?? 0) : 0;
            }
            if (!empty($wgIll)) {
                $latestIll = $this->WgIllModel->where('interface', $wgIll)->orderBy('updated_at', 'DESC')->first();
                $illStatus = $latestIll ? (int)($latestIll['status'] ?? 0) : 0;
            }
            if (!empty($wgBdr)) {
                $latestBdr = $this->WgBdrModel->where('interface', $wgBdr)->orderBy('updated_at', 'DESC')->first();
                $bdrStatus = $latestBdr ? (int)($latestBdr['status'] ?? 0) : 0;
            }

            $isOnline = (
                ($wgMpls && $mplsStatus === 1) || 
                ($wgIll && $illStatus === 1) ||
                ($wgBdr && $bdrStatus === 1)
            );
        } else {
            // Fallback to socket check or port check
            if ($isPortReachable) {
                $isOnline = true;
            } elseif (isset($device['status']) && (strtolower($device['status']) === '1' || strtolower($device['status']) === 'online' || strtolower($device['status']) === 'synced')) {
                if (!empty($device['updated_at']) && (time() - strtotime($device['updated_at']) <= 300)) {
                    $isOnline = true;
                }
            }
        }
        
        $device['mpls_telemetry'] = [
            'status' => $mplsStatus ?? 0,
            'flaps'  => isset($latestMpls['flap_count']) ? (int)$latestMpls['flap_count'] : 0,
            'interface' => $wgMpls ?? ''
        ];
        $device['ill_telemetry'] = [
            'status' => $illStatus ?? 0,
            'flaps'  => isset($latestIll['flap_count']) ? (int)$latestIll['flap_count'] : 0,
            'interface' => $wgIll ?? ''
        ];
        $device['bdr_telemetry'] = [
            'status' => $bdrStatus ?? 0,
            'flaps'  => isset($latestBdr['flap_count']) ? (int)$latestBdr['flap_count'] : 0,
            'interface' => $wgBdr ?? ''
        ];
        $activeUplinks = (($mplsStatus ?? 0) === 1 ? 1 : 0) + (($illStatus ?? 0) === 1 ? 1 : 0) + (($bdrStatus ?? 0) === 1 ? 1 : 0);
        $device['active_uplinks_count'] = $activeUplinks;
        $device['wan_redundancy'] = $activeUplinks >= 2 ? 'HA Active' : ($activeUplinks === 1 ? 'Degraded' : 'Offline');

        if (empty($device['name']) || $device['name'] == 'UNKNOWN') {
            $device['category'] = 'Unknown';
            $device['statusColor'] = '#ef4444';
        } elseif (!empty($device['bypass']) && $device['bypass'] == 1) {
            $device['category'] = 'Bypassed';
            $device['statusColor'] = '#9ca3af';
        } elseif (!$isOnline) {
            $device['category'] = 'Stopped';
            $device['statusColor'] = '#f59e0b';
        } else {
            $device['category'] = 'Running';
            $device['statusColor'] = '#10b981';
        }

        $data['device'] = $device;
    
        // Expose locations and groups for the edit modal
        $data['locations'] = $this->LocationModel->findAll();
        $data['all_groups'] = $this->AppGroupsModel->findAll();
    
        // Add user and app details
        $user = \auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Logged In';
        $appName = $this->CoreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->CoreConfigModel->where('name', 'version')->first()['value'] ?? '1.0.0';
    
        $data['app_name'] = $appName;
        $data['app_version'] = $appVersion;
        
        $appMpassConfig = $this->CoreConfigModel->where('name', 'mast_pass')->first();
        $data['mast_pass'] = $appMpassConfig['value'] ?? 'p@ssw0rd';
        
        $data['title'] = strip_tags($appName) . ' 🔸 Device Configuration Details';
    
        log_message('debug', "Firewall {$id}: view data ready - "
            . count((array) ($data['gateways'] ?? [])) . ' gateways, '
            . count((array) ($data['services'] ?? [])) . ' services, '
            . count((array) ($data['systems'] ?? [])) . ' systems, '
            . count((array) ($data['wireguard_peers'] ?? [])) . ' wireguard peers, error: '
            . ($data['error'] === null ? 'none' : $data['error']) . '.');
    
        // Render views
        echo view('frontend/header', $data);
        echo view('frontend/sidebar', $data);
        echo view('listmode/pfdetails', $data);
        echo view('frontend/footer', $data);
    }
    
    protected function callApi($url, $username, $password)
    {
        return $this->apiClient->call($url, $username, $password);
    }

    /**
     * Real-Time Heartbeat & Latency Probe
     * Tests socket connection to device port and returns latency in milliseconds
     */
    public function pingDevice($id)
    {
        $device = $this->pfInventoryModel->find($id);
        if (!$device) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Device not found.']);
        }

        $status = $this->PfStatusModel->where('pf_id', $id)->first();
        $ip = !empty($device['man_ip']) ? $device['man_ip'] : (!empty($status['ip']) ? $status['ip'] : ($device['ip'] ?? ''));
        $port = !empty($device['man_port']) ? (int)$device['man_port'] : (!empty($device['port']) ? (int)$device['port'] : 443);

        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this->response->setJSON([
                'success' => false,
                'latency' => null,
                'message' => 'Invalid or missing target IP'
            ]);
        }

        $start = microtime(true);
        $fp = @stream_socket_client("tcp://{$ip}:{$port}", $errno, $errstr, 1.2);
        $elapsed = round((microtime(true) - $start) * 1000, 1);

        if ($fp) {
            fclose($fp);
            return $this->response->setJSON([
                'success' => true,
                'latency' => $elapsed,
                'ip'      => $ip,
                'port'    => $port,
                'message' => "Online: {$elapsed} ms round-trip"
            ]);
        }

        // Secondary fallback to port 80 or 22 if custom port failed
        foreach ([80, 22] as $fallbackPort) {
            if ($fallbackPort === $port) continue;
            $start = microtime(true);
            $fp = @stream_socket_client("tcp://{$ip}:{$fallbackPort}", $errno, $errstr, 0.8);
            $elapsed = round((microtime(true) - $start) * 1000, 1);
            if ($fp) {
                fclose($fp);
                return $this->response->setJSON([
                    'success' => true,
                    'latency' => $elapsed,
                    'ip'      => $ip,
                    'port'    => $fallbackPort,
                    'message' => "Online: {$elapsed} ms (via port {$fallbackPort})"
                ]);
            }
        }

        return $this->response->setJSON([
            'success' => false,
            'latency' => null,
            'ip'      => $ip,
            'port'    => $port,
            'message' => "Unreachable (Timeout): $errstr"
        ]);
    }
}

