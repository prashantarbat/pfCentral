<?php

namespace App\Controllers;

use App\Controllers\BaseController;
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
use CodeIgniter\CLI\CLI;

class DevicesController extends BaseController
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
    
        // Fetch all records from PfStatusModel using raw builder to guarantee custom fields
        $db = \Config\Database::connect();
        $statusRecords = $db->table('pf_status')
                            ->select('*, TIMESTAMPDIFF(SECOND, updated_at, NOW()) as status_seconds_ago')
                            ->get()
                            ->getResultArray();
    
        // Create a map of pf_id to PfStatusModel records
        $statusMap = [];
        foreach ($statusRecords as $statusRecord) {
            $statusMap[$statusRecord['pf_id']] = $statusRecord;
        }

        // Pre-fetch latest WireGuard statuses into maps to avoid N+1 queries
        $latestMplsMap = [];
        $latestIllMap = [];
        $latestBdrMap = [];

        try {
            $mplsRecords = $this->WgMplsModel->select('interface, status')->orderBy('updated_at', 'DESC')->findAll();
            foreach ($mplsRecords as $r) {
                $iface = strtolower((string)$r['interface']);
                if (!isset($latestMplsMap[$iface])) {
                    $latestMplsMap[$iface] = (int)($r['status'] ?? 0);
                }
            }

            $illRecords = $this->WgIllModel->select('interface, status')->orderBy('updated_at', 'DESC')->findAll();
            foreach ($illRecords as $r) {
                $iface = strtolower((string)$r['interface']);
                if (!isset($latestIllMap[$iface])) {
                    $latestIllMap[$iface] = (int)($r['status'] ?? 0);
                }
            }

            $bdrRecords = $this->WgBdrModel->select('interface, status')->orderBy('updated_at', 'DESC')->findAll();
            foreach ($bdrRecords as $r) {
                $iface = strtolower((string)$r['interface']);
                if (!isset($latestBdrMap[$iface])) {
                    $latestBdrMap[$iface] = (int)($r['status'] ?? 0);
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error pre-fetching WireGuard statuses: ' . $e->getMessage());
        }
    
        // Fetch pf_devices for fallback
        $deviceRecords = $db->table('pf_devices')->get()->getResultArray();
        $deviceConfigMap = [];
        foreach ($deviceRecords as $d) {
            $deviceConfigMap[trim($d['br_name'])] = $d;
        }

        // Merge status data into each pf record
        foreach ($data['pf'] as &$row) {
            $brName = trim($row['name'] ?? '');
            $devConfig = $deviceConfigMap[$brName] ?? null;

            if (isset($statusMap[$row['id']])) {
                $statusData = $statusMap[$row['id']];
                // Safely remove 'id' to avoid overwriting pf_inventory primary key
                unset($statusData['id']);
                $row = array_merge($row, $statusData);
            } else {
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
            }

            // Fallback from pf_devices if fields are empty
            if ($devConfig) {
                if ((empty($row['hostname']) || $row['hostname'] === '...') && !empty($devConfig['hostname'])) {
                    $row['hostname'] = $devConfig['hostname'];
                }
                if ((empty($row['mpls_ip']) || $row['mpls_ip'] === '...') && !empty($devConfig['mpls_ip'])) {
                    $row['mpls_ip'] = $devConfig['mpls_ip'];
                }
                if ((empty($row['ill_ip']) || $row['ill_ip'] === '...') && !empty($devConfig['ill_ip'])) {
                    $row['ill_ip'] = $devConfig['ill_ip'];
                }
                if ((empty($row['ip']) || $row['ip'] === '...') && !empty($devConfig['lan_ip'])) {
                    $row['ip'] = $devConfig['lan_ip'];
                }
            } else {
                // If name is empty or unlinked, try matching by IP in pf_devices
                if (empty(trim($row['name'] ?? '')) && !empty($row['ip']) && $row['ip'] !== '...') {
                    foreach ($deviceRecords as $d) {
                        if ($d['lan_ip'] === $row['ip']) {
                            $row['name'] = $d['br_name'];
                            $row['hostname'] = $d['hostname'];
                            break;
                        }
                    }
                }
            }
            if ((empty($row['os_name']) || $row['os_name'] === '...') && !empty($row['platform'])) {
                $row['os_name'] = $row['platform'];
            }
        }
        unset($row); // break reference

        // Fetch all locations
        $data['locations'] = $this->LocationModel->findAll();
    
        // Create location ID => data map
        $locationMap = [];
        foreach ($data['locations'] as $loc) {
            $locationMap[$loc['id']] = $loc;
        }
    
        // Add location details and evaluate standardized status
        foreach ($data['pf'] as &$row) {
            // Auto-heal location if missing from inventory
            if (empty($row['location']) || !isset($locationMap[$row['location']])) {
                $checkName = trim($row['name'] ?? '');
                $checkIp   = trim($row['ip'] ?? '');
                foreach ($data['locations'] as $locCandidate) {
                    if (!empty($checkName) && strcasecmp(trim($locCandidate['name']), $checkName) === 0) {
                        $row['location'] = $locCandidate['id'];
                        break;
                    }
                    if (!empty($checkIp) && !empty($locCandidate['ip']) && $locCandidate['ip'] === $checkIp) {
                        $row['location'] = $locCandidate['id'];
                        break;
                    }
                }
            }

            $loc = $locationMap[$row['location']] ?? null;
            $row['location_name'] = $loc['name'] ?? ($row['name'] ?: 'Unknown');

            // Retrieve associated WireGuard interfaces
            $wgMpls = !empty($loc['wg_mpls']) ? strtolower(trim($loc['wg_mpls'])) : '';
            $wgIll  = !empty($loc['wg_ill']) ? strtolower(trim($loc['wg_ill'])) : '';
            $wgBdr  = !empty($loc['wg_bdr']) ? strtolower(trim($loc['wg_bdr'])) : '';

            $row['wg_mpls'] = !empty($loc['wg_mpls']) ? strtoupper(trim($loc['wg_mpls'])) : '';
            $row['wg_ill']  = !empty($loc['wg_ill']) ? strtoupper(trim($loc['wg_ill'])) : '';
            $row['wg_bdr']  = !empty($loc['wg_bdr']) ? strtoupper(trim($loc['wg_bdr'])) : '';

            $hasWgConfig = ($wgMpls || $wgIll || $wgBdr);
            $wgOnline = false;

            $mplsStatus = 0;
            $illStatus  = 0;
            $bdrStatus  = 0;

            if ($hasWgConfig) {
                // MySQL is case-insensitive, so we must be too when looking up PHP arrays
                $mplsStatus = ($wgMpls !== '' && isset($latestMplsMap[$wgMpls])) ? $latestMplsMap[$wgMpls] : 0;
                $illStatus  = ($wgIll !== '' && isset($latestIllMap[$wgIll])) ? $latestIllMap[$wgIll] : 0;
                $bdrStatus  = ($wgBdr !== '' && isset($latestBdrMap[$wgBdr])) ? $latestBdrMap[$wgBdr] : 0;

                $wgOnline = (
                    ($wgMpls !== '' && $mplsStatus === 1) || 
                    ($wgIll !== '' && $illStatus === 1) ||
                    ($wgBdr !== '' && $bdrStatus === 1)
                );
            }

            $row['mpls_status'] = $mplsStatus;
            $row['ill_status']  = $illStatus;
            $row['bdr_status']  = $bdrStatus;

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
            $row['status'] = $isOnline ? 'online' : 'offline';
        }
        unset($row); // break reference
    
        // Fetch groups for the modal dropdown
        $data['groups'] = $this->AppGroupsModel->findAll();
    
        // Load views
        echo view('frontend/header', $data);
        echo view('frontend/sidebar', $data);
        echo view('devices/devices', $data);
        echo view('frontend/footer', $data);
    }


    public function unknownupdate()
    {
        // Retrieve data from POST request
        $deviceId = $this->request->getPost('id'); // Get device ID from hidden field

        // Guard against a missing/foreign id: Model::update() runs "WHERE id IS NULL"
        // (or updates nothing) and still returns true, which used to report success
        // while saving nothing.
        if (empty($deviceId) || !ctype_digit((string) $deviceId) || !$this->pfInventoryModel->find($deviceId)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Device not found in inventory. Please refresh the page and try again.',
            ]);
        }

        $deviceName = $this->request->getPost('name');
        $deviceSerial = $this->request->getPost('serial');
        $deviceGroup = $this->request->getPost('group_id');
        $deviceLocation = $this->request->getPost('location_id');
        $deviceUsername = $this->request->getPost('username');
        $devicePassword = $this->request->getPost('password');
        $deviceApi = $this->request->getPost('api');
        $deviceNotes = $this->request->getPost('notes');
        
        // Validate required fields
        $validation = \Config\Services::validation();
        $validation->setRules([
            'name'        => 'required|min_length[3]',
            'username'    => 'required|min_length[3]',
            'password'    => 'required|min_length[6]',
            'location_id' => 'required',
        ]);
    
        if (!$validation->withRequest($this->request)->run()) {
            // Return errors as JSON to display in SweetAlert2
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validation->getErrors(),
            ]);
        }
    
        // Prepare data for update
        $data = [
            'active'      => '1',
            'name'        => $deviceName,
            'serial'      => $deviceSerial,
            'groups'    => $deviceGroup,
            'location'    => $deviceLocation,
            'username'    => $deviceUsername,
            'password'    => $devicePassword,
            'api'         => $deviceApi,
            'notes'       => $deviceNotes,
        ];
    
        // Update the device record
        $updateSuccess = $this->pfInventoryModel->update($deviceId, $data);
    
        // Return response back to frontend
        if ($updateSuccess) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Device updated successfully!',
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to update device.',
            ]);
        }
    }   
    
    public function delete($id)
    {
        // Check if the inventory record exists
        $inventory = $this->pfInventoryModel->find($id);
    
        if (!$inventory) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Device not found.',
            ])->setStatusCode(404);
        }
    
        // 1. Delete related records from pf_status
        $this->PfStatusModel->where('pf_id', $id)->delete();
    
        // 2. Delete backup files from disk
        $backups = $this->PfBakupModel->where('pf_id', $id)->findAll();
        foreach ($backups as $backup) {
            $filePath = $backup['file_path'];
            if ($filePath && file_exists($filePath)) {
                @unlink($filePath); // Suppress errors, if any
            }
        }
    
        // 3. Delete related records from pf_backups
        $this->PfBakupModel->where('pf_id', $id)->delete();
    
        // 4. Delete from pf_inventory
        $this->pfInventoryModel->delete($id);
    
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Device, status, and backup files deleted successfully.',
        ]);
    }

    public function deviceCard($id)
    {
        $device = $this->pfInventoryModel->find($id);
        if (!$device) {
            return 'Device not found';
        }

        // Fetch status
        $statusRecord = $this->PfStatusModel->where('pf_id', $id)->first();
        if ($statusRecord) {
            // pf_status has its own auto-increment 'id'; drop it so the merge cannot
            // clobber the pf_inventory primary key. index() does the same before its
            // merge - without this, every id on the rendered card (edit, delete,
            // update-device buttons) silently points at the wrong device.
            unset($statusRecord['id']);
            $device = array_merge($device, $statusRecord);
        }

        // Defensive status/telemetry fallbacks to prevent ErrorException for missing keys
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
            if (!isset($device[$key])) {
                $device[$key] = $val;
            }
        }

        // Fetch location details safely
        $locationId = $device['location'] ?? null;
        $loc = $locationId ? $this->LocationModel->find($locationId) : null;
        $device['location_name'] = ($loc && !empty($loc['name'])) ? $loc['name'] : ($device['name'] ?? 'Unknown');

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

        // Standardized status evaluation based on WireGuard interfaces at the location
        $wgMpls = $loc ? trim($loc['wg_mpls'] ?? '') : '';
        $wgIll  = $loc ? trim($loc['wg_ill'] ?? '') : '';
        $wgBdr  = $loc ? trim($loc['wg_bdr'] ?? '') : '';

        $isOnline = false;

        if ($loc && ($wgMpls || $wgIll || $wgBdr)) {
            $mplsStatus = 0;
            $illStatus = 0;
            $bdrStatus = 0;

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
            // Fallback to socket check
            $ip = $device['man_ip'] ?? $device['ip'] ?? '';
            $port = $device['man_port'] ?? $device['port'] ?? '';
            
            if (!empty($ip) && !empty($port) && is_numeric($port) && 
                (filter_var($ip, FILTER_VALIDATE_IP) || filter_var($ip, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME))) {
                $connection = @stream_socket_client("tcp://$ip:$port", $errno, $errstr, 2, STREAM_CLIENT_CONNECT);
                if ($connection) {
                    fclose($connection);
                    $isOnline = true;
                }
            }
            
            if (!$isOnline && isset($device['status']) && (strtolower($device['status']) === '1' || strtolower($device['status']) === 'online')) {
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

        $device['statusColor'] = $isOnline ? 'green' : 'red';

        // Categorize
        if ($device['name'] == 'UNKNOWN' || empty($device['name'])) {
            $device['category'] = 'Unknown';
        } elseif (!empty($device['bypass']) && $device['bypass'] == 1) {
            $device['category'] = 'Bypassed';
            $device['statusColor'] = 'blue';
        } elseif (!$isOnline) {
            $device['category'] = 'Stopped';
            $device['statusColor'] = 'red';
        } else {
            $device['category'] = 'Running';
            $device['statusColor'] = 'green';
        }

        return view('devices/device_card', ['device' => $device]);
    }

    public function getGroups()
    {
        $groups = $this->AppGroupsModel->findAll();
        return $this->response->setJSON($groups);
    }

    public function storeGroup()
    {
        $name = trim($this->request->getPost('name') ?? '');
        $description = trim($this->request->getPost('description') ?? '');

        if (empty($name)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Group name is required.']);
        }

        // Check if group name already exists
        $existing = $this->AppGroupsModel->where('name', $name)->first();
        if ($existing) {
            return $this->response->setJSON(['success' => false, 'message' => 'Group with this name already exists.']);
        }

        $id = $this->AppGroupsModel->insert([
            'name' => $name,
            'description' => $description
        ]);

        if ($id) {
            // Fetch and assign group to the selected devices
            $deviceIds = $this->request->getPost('device_ids') ?? [];
            if (!is_array($deviceIds)) {
                $deviceIds = [];
            }

            // Clear this group name from all devices first to prevent stale records
            $this->pfInventoryModel->where('groups', $name)->set(['groups' => ''])->update();

            // Assign the group to selected devices
            if (!empty($deviceIds)) {
                $this->pfInventoryModel->whereIn('id', $deviceIds)->set(['groups' => $name])->update();
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Group created successfully.']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to create group.']);
    }

    public function updateGroup($id)
    {
        $group = $this->AppGroupsModel->find($id);
        if (!$group) {
            return $this->response->setJSON(['success' => false, 'message' => 'Group not found.']);
        }

        $name = trim($this->request->getPost('name') ?? '');
        $description = trim($this->request->getPost('description') ?? '');

        if (empty($name)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Group name is required.']);
        }

        $existing = $this->AppGroupsModel->where('name', $name)->where('id !=', $id)->first();
        if ($existing) {
            return $this->response->setJSON(['success' => false, 'message' => 'Group with this name already exists.']);
        }

        $oldName = $group['name'];
        $updated = $this->AppGroupsModel->update($id, [
            'name' => $name,
            'description' => $description
        ]);

        if ($updated) {
            // Cascade update the groups column in pf_inventory!
            if ($oldName !== $name) {
                $this->pfInventoryModel->where('groups', $oldName)->set(['groups' => $name])->update();
            }

            // Fetch and assign group to the selected devices
            $deviceIds = $this->request->getPost('device_ids') ?? [];
            if (!is_array($deviceIds)) {
                $deviceIds = [];
            }

            // Clear the group name from all devices that were previously assigned to this group
            $this->pfInventoryModel->where('groups', $name)->set(['groups' => ''])->update();
            if ($oldName !== $name) {
                $this->pfInventoryModel->where('groups', $oldName)->set(['groups' => ''])->update();
            }

            // Assign the group to the newly selected devices
            if (!empty($deviceIds)) {
                $this->pfInventoryModel->whereIn('id', $deviceIds)->set(['groups' => $name])->update();
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Group updated successfully.']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to update group.']);
    }

    public function deleteGroup($id)
    {
        $group = $this->AppGroupsModel->find($id);
        if (!$group) {
            return $this->response->setJSON(['success' => false, 'message' => 'Group not found.']);
        }

        $groupName = $group['name'];
        if ($this->AppGroupsModel->delete($id)) {
            // Cascade clear the groups column in pf_inventory!
            $this->pfInventoryModel->where('groups', $groupName)->set(['groups' => ''])->update();
            return $this->response->setJSON(['success' => true, 'message' => 'Group deleted successfully.']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to delete group.']);
    }
}