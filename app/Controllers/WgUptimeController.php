<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\LocationModel;
use App\Models\WgMplsModel;
use App\Models\WgIllModel;
use App\Models\WgBdrModel;
use App\Models\AppGroupsModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\I18n\Time;
use App\Models\CoreConfigModel;

class WgUptimeController extends BaseController
{
    use ResponseTrait;

    protected $CoreConfigModel;
    protected $WgMplsModel;
    protected $WgIllModel;
    protected $WgBdrModel;
    protected $locationModel;
    protected $AppGroupsModel;

    public function __construct()
    {
        $this->CoreConfigModel = new CoreConfigModel();
        $this->WgMplsModel     = new WgMplsModel();
        $this->WgIllModel      = new WgIllModel();
        $this->WgBdrModel      = new WgBdrModel();
        $this->locationModel   = new LocationModel();
        $this->AppGroupsModel  = new AppGroupsModel();
    }
    
    public function receive()
    {
        $request = service('request');
        $data    = $request->getJSON(true);
    
        if (!is_array($data)) {
            return $this->failValidationError('Invalid JSON format.');
        }

        // Save the raw JSON data to a daily log file for easy debugging/verification and history tracking
        try {
            $rawBody = $request->getBody();
            // Ensure daily log directory exists
            $logDir = WRITEPATH . 'logs/telemetry/';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            $logFile = $logDir . 'wgreceive_' . date('Y-m-d') . '.log';
            $logEntry = "==== " . date('Y-m-d H:i:s') . " | IP: " . $request->getIPAddress() . " ====\n" . $rawBody . "\n\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            
            // Also keep the single latest json file for quick single-state checks
            file_put_contents(WRITEPATH . 'debug_wgreceive.json', $rawBody);
        } catch (\Exception $e) {
            log_message('error', 'wgreceive raw log save failed: ' . $e->getMessage());
        }
    
        $insertCount = 0;
        $updateCount = 0;
        $skipped     = [];
    
        // MODELS MAP FOR 3 JSON TYPES
        $models = [
            'mpls' => [
                'model' => $this->WgMplsModel,
                'field' => 'wg_mpls'
            ],
            'ill' => [
                'model' => $this->WgIllModel,
                'field' => 'wg_ill'
            ],
            'bdr' => [
                'model' => $this->WgBdrModel,
                'field' => 'wg_bdr'
            ]
        ];
    
        foreach ($models as $key => $cfg) {
    
            // If this JSON section is not in POST → skip
            if (!isset($data[$key]) || !is_array($data[$key])) {
                continue;
            }
    
            $model      = $cfg['model'];
            $lookupColumn = $cfg['field'];
    
            foreach ($data[$key] as $iface => $status) {
    
                // Find matching location
                $location = $this->locationModel
                    ->where($lookupColumn, $iface)
                    ->first();
    
                if (!$location) {
                    log_message('error', "No location found for iface: {$iface} ({$key})");
                    $skipped[] = $iface;
                    continue;
                }
    
                // Last record → detect flap
                $last = $model->select('status, flap_count')
                    ->where('interface', $iface)
                    ->orderBy('created_at', 'DESC')
                    ->first();
    
                $newFlapCount = 0;
    
                if ($last) {
                    if ((int)$last['status'] !== (int)$status) {
                        // Status changed → FLAP
                        $newFlapCount = ((int)$last['flap_count']) + 1;
                    } else {
                        $newFlapCount = (int)$last['flap_count'];
                    }
                }
    
                // Insert/Update record
                $record = [
                    'location_id' => $location['id'],
                    'interface'   => $iface,
                    'status'      => (int) $status,
                    'flap_count'  => $newFlapCount,
                ];
    
                $existing = $model->where([
                    'location_id' => $location['id'],
                    'interface'   => $iface
                ])->first();
    
                if ($existing) {
                    $model->update($existing['id'], $record);
                    $updateCount++;
                } else {
                    $model->insert($record);
                    $insertCount++;
                }
            }
        }
    
        return $this->respond([
            'message' => "Inserted {$insertCount}, Updated {$updateCount}, Skipped " . count($skipped),
            'skipped' => $skipped
        ], 200);
    }


    public function live()
    {
        $locationModel = new LocationModel();
        $wgMplsModel   = new WgMplsModel();
        $wgIllModel    = new WgIllModel();
        $wgBdrModel    = new WgBdrModel();   // <<--- ADD THIS
    
        $locations = $locationModel->findAll();
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        // Build a lookup map from location ID to group name
        $db = \Config\Database::connect();
        $pfInventory = $db->table('pf_inventory')->select('location, groups')->get()->getResultArray();
        $locationGroupMap = [];
        foreach ($pfInventory as $pf) {
            $locId = $pf['location'];
            $grpName = trim($pf['groups'] ?? '');
            if ($locId && !empty($grpName)) {
                $locationGroupMap[$locId] = $grpName;
            }
        }
    
        // Core config
        $data['app_name']    = $this->CoreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $data['app_version'] = $this->CoreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $data['app_url']     = $this->CoreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';
        $data['mast_pass']   = $this->CoreConfigModel->where('name', 'mast_pass')->first()['value'] ?? 'p@ssw0rd';
        $data['title']       = strip_tags($data['app_name']) . ' 🔸 Public Live Status Dashboard';

        // LibreNMS base URL — strip /api/v0 or any API path to get the portal root
        $rawLibreNmsUrl = $this->CoreConfigModel->where('name', 'librenms_api_url')->first()['value'] ?? '';
        $librenmsBase = '';
        if (!empty($rawLibreNmsUrl)) {
            $parsed = parse_url($rawLibreNmsUrl);
            $librenmsBase = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
            if (!empty($parsed['port'])) {
                $librenmsBase .= ':' . $parsed['port'];
            }
        }
        $data['librenms_base'] = $librenmsBase;

        // Retrieve dynamic live alerts settings
        $liveAlertsEnable = $this->CoreConfigModel->where('name', 'live_alerts_enable')->first()['value'] ?? '1';
        $liveAlertsTiming = (int)($this->CoreConfigModel->where('name', 'live_alerts_timing')->first()['value'] ?? 60);
        if ($liveAlertsTiming < 1) {
            $liveAlertsTiming = 60;
        }
        $minutesLabel = $liveAlertsTiming === 1 ? 'minute' : $liveAlertsTiming . ' minutes';
    
        $locationsData = [];
        $alerts = [];
    
        foreach ($locations as $location) {
    
            $mpls = $location['wg_mpls'];
            $ill  = $location['wg_ill'];
            $bdr  = $location['wg_bdr'];  // <<--- ADD FIELD IN locations TABLE
    
            $mplsStatus = $illStatus = $bdrStatus = null;
    
    // ======================================================
    //   MPLS SECTION
    // ======================================================
    if ($mpls) {
        $latestMpls = $wgMplsModel->where('interface', $mpls)
                                  ->orderBy('id', 'DESC')
                                  ->first();
    
        $mplsStatus = $latestMpls['status'] ?? null;
        $mplsTime   = isset($latestMpls['updated_at']) ? new Time($latestMpls['updated_at']) : null;
    
        // Skip alerts only for Secondary DR location
        if ($liveAlertsEnable == '1' &&
            $mplsTime && 
            $mplsTime->isBefore(Time::now()->subMinutes($liveAlertsTiming)) &&
            $location['name'] !== "0000-HQ-PERIMETER-FW01") 
        {
            $alerts[] = '⚠️ Alert: No data received from "' . esc($location['name']) . '" (MPLS) Controller in the last ' . $minutesLabel . '.';
        }
    
        $mplsTotal = $latestMpls['flap_count'] ?? 0;
    }
    
    // ======================================================
    //   ILL SECTION
    // ======================================================
    if ($ill) {
        $latestIll = $wgIllModel->where('interface', $ill)
                                ->orderBy('id', 'DESC')
                                ->first();
    
        $illStatus = $latestIll['status'] ?? null;
        $illTime   = isset($latestIll['updated_at']) ? new Time($latestIll['updated_at']) : null;
    
        // Skip alerts only for Secondary DR location
        if ($liveAlertsEnable == '1' &&
            $illTime && 
            $illTime->isBefore(Time::now()->subMinutes($liveAlertsTiming)) &&
            $location['name'] !== "0000-HQ-PERIMETER-FW01") 
        {
            $alerts[] = '⚠️ Alert: No data received from "' . esc($location['name']) . '" (ILL) Controller in the last ' . $minutesLabel . '.';
        }
    
        $illTotal = $latestIll['flap_count'] ?? 0;
    }
    
    // ======================================================
    //   BDR SECTION (NEW)
    // ======================================================
    if ($bdr) {
        $latestBdr = $wgBdrModel->where('interface', $bdr)
                                ->orderBy('id', 'DESC')
                                ->first();
    
        $bdrStatus = $latestBdr['status'] ?? null;
        $bdrTime   = isset($latestBdr['updated_at']) ? new Time($latestBdr['updated_at']) : null;
    
        // Skip alerts only for Secondary DR location
        // if ($liveAlertsEnable == '1' &&
        //     $bdrTime && 
        //     $bdrTime->isBefore(Time::now()->subHours($liveAlertsTiming)) &&
        //     $location['name'] !== "0000-HQ-PERIMETER-FW01") 
        // {
        //     $alerts[] = '⚠️ Alert: No status received from "' . esc($location['name']) . '" (BDR) in the last ' . $hoursLabel . '.';
        // }
    
        $bdrTotal = $latestBdr['flap_count'] ?? 0;
    }
    
    
            // ======================================================
            // FINAL ARRAY FOR VIEW
            // ======================================================
            $locationsData[] = [
                'name' => $location['name'],
                'ip'   => $location['ip'],
    
                'mpls_iface'  => $mpls,
                'mpls_status' => $mplsStatus,
                'mpls_total'  => $mplsTotal ?? 0,
    
                'ill_iface'   => $ill,
                'ill_status'  => $illStatus,
                'ill_total'   => $illTotal ?? 0,
    
                'bdr_iface'    => $bdr,           // NEW
                'bdr_status'  => $bdrStatus,     // NEW
                'bdr_total'   => $bdrTotal ?? 0, // NEW
                'group'       => $locationGroupMap[$location['id']] ?? '',
            ];
        }

        // Sort alphabetically by Group (ascending) - empty groups to the bottom, and then by Location name
        usort($locationsData, function ($a, $b) {
            $aGroup = trim($a['group'] ?? '');
            $bGroup = trim($b['group'] ?? '');
            $aName = trim($a['name'] ?? '');
            $bName = trim($b['name'] ?? '');

            $aHasGroup = !empty($aGroup);
            $bHasGroup = !empty($bGroup);

            if ($aHasGroup && !$bHasGroup) return -1;
            if (!$aHasGroup && $bHasGroup) return 1;

            if ($aHasGroup && $bHasGroup) {
                $cmp = strcasecmp($aGroup, $bGroup);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return strcasecmp($aName, $bName);
        });
    
        $data['data']      = $locationsData;
        $data['alerts']    = $alerts;
        $data['locations'] = $locationModel->findAll();
        $data['groups']    = $this->AppGroupsModel->findAll();
    
        // Public dashboard (reachable without logging in) renders in its own
        // stripped-down layout rather than the authenticated header/sidebar/footer
        // shell, so admin chrome cannot leak onto it by omission.
        $data['content'] = view('live_dashboard', $data);

        echo view('frontend/public_layout', $data);
    }
   
    public function loggedlive()
    {
        $locationModel = new LocationModel();
        $wgMplsModel   = new WgMplsModel();
        $wgIllModel    = new WgIllModel();
        $wgBdrModel    = new WgBdrModel();   // <<--- ADD THIS
    
        $locations = $locationModel->findAll();
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        // Build a lookup map from location ID to group name
        $db = \Config\Database::connect();
        $pfInventory = $db->table('pf_inventory')->select('location, groups')->get()->getResultArray();
        $locationGroupMap = [];
        foreach ($pfInventory as $pf) {
            $locId = $pf['location'];
            $grpName = trim($pf['groups'] ?? '');
            if ($locId && !empty($grpName)) {
                $locationGroupMap[$locId] = $grpName;
            }
        }
    
        // Core config
        $data['app_name']    = $this->CoreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $data['app_version'] = $this->CoreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $data['app_url']     = $this->CoreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';
        $data['mast_pass']   = $this->CoreConfigModel->where('name', 'mast_pass')->first()['value'] ?? 'p@ssw0rd';
        $data['title']       = strip_tags($data['app_name']) . ' 🔸 Live Status Dashboard (Admin)';

        // LibreNMS base URL — strip /api/v0 or any API path to get the portal root
        $rawLibreNmsUrl = $this->CoreConfigModel->where('name', 'librenms_api_url')->first()['value'] ?? '';
        $librenmsBase = '';
        if (!empty($rawLibreNmsUrl)) {
            $parsed = parse_url($rawLibreNmsUrl);
            $librenmsBase = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
            if (!empty($parsed['port'])) {
                $librenmsBase .= ':' . $parsed['port'];
            }
        }
        $data['librenms_base'] = $librenmsBase;

        // Retrieve dynamic live alerts settings
        $liveAlertsEnable = $this->CoreConfigModel->where('name', 'live_alerts_enable')->first()['value'] ?? '1';
        $liveAlertsTiming = (int)($this->CoreConfigModel->where('name', 'live_alerts_timing')->first()['value'] ?? 60);
        if ($liveAlertsTiming < 1) {
            $liveAlertsTiming = 60;
        }
        $minutesLabel = $liveAlertsTiming === 1 ? 'minute' : $liveAlertsTiming . ' minutes';
    
        $locationsData = [];
        $alerts = [];
    
        foreach ($locations as $location) {
    
            $mpls = $location['wg_mpls'];
            $ill  = $location['wg_ill'];
            $bdr  = $location['wg_bdr'];  // <<--- ADD FIELD IN locations TABLE
    
            $mplsStatus = $illStatus = $bdrStatus = null;
    
    // ======================================================
    //   MPLS SECTION
    // ======================================================
    if ($mpls) {
        $latestMpls = $wgMplsModel->where('interface', $mpls)
                                  ->orderBy('id', 'DESC')
                                  ->first();
    
        $mplsStatus = $latestMpls['status'] ?? null;
        $mplsTime   = isset($latestMpls['updated_at']) ? new Time($latestMpls['updated_at']) : null;
    
        // Skip alerts only for Secondary DR location
        if ($liveAlertsEnable == '1' &&
            $mplsTime && 
            $mplsTime->isBefore(Time::now()->subMinutes($liveAlertsTiming)) &&
            $location['name'] !== "0000-HQ-PERIMETER-FW01") 
        {
            $alerts[] = '⚠️ Alert: No data received from "' . esc($location['name']) . '" (MPLS) Controller in the last ' . $minutesLabel . '.';
        }
    
        $mplsTotal = $latestMpls['flap_count'] ?? 0;
    }
    
    // ======================================================
    //   ILL SECTION
    // ======================================================
    if ($ill) {
        $latestIll = $wgIllModel->where('interface', $ill)
                                ->orderBy('id', 'DESC')
                                ->first();
    
        $illStatus = $latestIll['status'] ?? null;
        $illTime   = isset($latestIll['updated_at']) ? new Time($latestIll['updated_at']) : null;
    
        // Skip alerts only for Secondary DR location
        if ($liveAlertsEnable == '1' &&
            $illTime && 
            $illTime->isBefore(Time::now()->subMinutes($liveAlertsTiming)) &&
            $location['name'] !== "0000-HQ-PERIMETER-FW01") 
        {
            $alerts[] = '⚠️ Alert: No data received from "' . esc($location['name']) . '" (ILL) Controller in the last ' . $minutesLabel . '.';
        }
    
        $illTotal = $latestIll['flap_count'] ?? 0;
    }
    
    // ======================================================
    //   BDR SECTION (NEW)
    // ======================================================
    if ($bdr) {
        $latestBdr = $wgBdrModel->where('interface', $bdr)
                                ->orderBy('id', 'DESC')
                                ->first();
    
        $bdrStatus = $latestBdr['status'] ?? null;
        $bdrTime   = isset($latestBdr['updated_at']) ? new Time($latestBdr['updated_at']) : null;
    
        // Skip alerts only for Secondary DR location
        // if ($liveAlertsEnable == '1' &&
        //     $bdrTime && 
        //     $bdrTime->isBefore(Time::now()->subHours($liveAlertsTiming)) &&
        //     $location['name'] !== "0000-HQ-PERIMETER-FW01") 
        // {
        //     $alerts[] = '⚠️ Alert: No status received from "' . esc($location['name']) . '" (BDR) in the last ' . $hoursLabel . '.';
        // }
    
        $bdrTotal = $latestBdr['flap_count'] ?? 0;
    }
    
    
            // ======================================================
            // FINAL ARRAY FOR VIEW
            // ======================================================
            $locationsData[] = [
                'name' => $location['name'],
                'ip'   => $location['ip'],
    
                'mpls_iface'  => $mpls,
                'mpls_status' => $mplsStatus,
                'mpls_total'  => $mplsTotal ?? 0,
    
                'ill_iface'   => $ill,
                'ill_status'  => $illStatus,
                'ill_total'   => $illTotal ?? 0,
    
                'bdr_iface'    => $bdr,           // NEW
                'bdr_status'  => $bdrStatus,     // NEW
                'bdr_total'   => $bdrTotal ?? 0, // NEW
                'group'       => $locationGroupMap[$location['id']] ?? '',
            ];
        }

        // Sort alphabetically by Group (ascending) - empty groups to the bottom, and then by Location name
        usort($locationsData, function ($a, $b) {
            $aGroup = trim($a['group'] ?? '');
            $bGroup = trim($b['group'] ?? '');
            $aName = trim($a['name'] ?? '');
            $bName = trim($b['name'] ?? '');

            $aHasGroup = !empty($aGroup);
            $bHasGroup = !empty($bGroup);

            if ($aHasGroup && !$bHasGroup) return -1;
            if (!$aHasGroup && $bHasGroup) return 1;

            if ($aHasGroup && $bHasGroup) {
                $cmp = strcasecmp($aGroup, $bGroup);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            return strcasecmp($aName, $bName);
        });
    
        $data['data']      = $locationsData;
        $data['alerts']    = $alerts;
        $data['locations'] = $locationModel->findAll();
        $data['groups']    = $this->AppGroupsModel->findAll();
    
        echo view('frontend/header', $data);
        echo view('frontend/sidebar', $data);
        echo view('live_dashboard_login', $data);
        echo view('frontend/footer', $data);
    }


public function smsDownAlert()
{
    $db = \Config\Database::connect();

    // Shared-secret gate: this route must stay session-less so a system scheduler can
    // reach it, so the caller identifies itself with ?token=… from Core Settings.
    // Fail closed — an unset token (or wrong/absent one) rejects every caller with the
    // same generic message, so probing can't tell which check failed.
    $expectedToken = \App\Support\Monitoring::smsCronToken();
    $givenToken    = (string) ($this->request->getGet('token') ?? '');
    if ($expectedToken === ''
        || $givenToken === ''
        || ! hash_equals($expectedToken, $givenToken)
    ) {
        $this->response->setStatusCode(401);
        echo "=== Unauthorized: a valid ?token= is required. ===\n";
        log_message('notice', 'smsDownAlert: rejected caller from IP ' . ($this->request->getIPAddress() ?? 'unknown') . ' (missing/invalid token).');

        return;
    }

    // Integration kill-switch: when SMS alerts are disabled in Core Settings this cron
    // endpoint records nothing and sends nothing.
    if (! \App\Support\Monitoring::smsEnabled()) {
        echo "=== SMS alerts are disabled in Core Settings — nothing sent. ===\n";
        log_message('info', 'smsDownAlert: skipped, sms_alerts_enable is off.');

        return;
    }

    // Use models from constructor
    $locationModel = $this->locationModel;
    $wgMplsModel   = $this->WgMplsModel;
    $wgIllModel    = $this->WgIllModel;
    $WgBdrModel    = $this->WgBdrModel; 

    helper('date');

    $locations = $locationModel->findAll();
    // date_default_timezone_set('Asia/Kolkata'); 
    $timeNow = (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

    // Recipients come from Core Settings (comma-separated mobile numbers); blank list
    // suspends sending even while the integration is enabled.
    $smsConfig = \App\Support\Monitoring::smsSettings();
    $mobile    = $smsConfig['recipients'];

    if (!$smsConfig['configured']) {
        echo "=== SMS gateway is not configured (URL / username / recipients missing) — nothing sent. ===\n";
        log_message('error', 'smsDownAlert: gateway not configured in Core Settings.');

        return;
    }

    // Configurable stale data limit (in minutes)
    $staleLimitMinutes = 10;
    $staleLimit = strtotime("-{$staleLimitMinutes} minutes");

    echo "=== SMS Down Alert started at {$timeNow} ===\n";

    foreach ($locations as $location) {
        $branchName = $location['name'] ?? 'UNKNOWN';
        $branchIP   = $location['ip'] ?? 'N/A';
        $mpls       = $location['wg_mpls'] ?? null;
        $ill        = $location['wg_ill'] ?? null;
        $bdr        = $location['wg_bdr'] ?? null;

        // === Skip branches with bypassed devices ===
        $isBypassed = false;
        try {
            $deviceBypassCheck = $db->table('pf_inventory')
                ->where('location', $location['id'])
                ->where('bypass', 1)
                ->countAllResults();
            if ($deviceBypassCheck > 0) {
                $isBypassed = true;
            }
        } catch (\Exception $e) {
            log_message('error', "smsDownAlert: Error checking bypass status for location {$location['id']}: " . $e->getMessage());
        }

        if ($isBypassed) {
            log_message('info', "smsDownAlert: Skipping {$branchName} — device is set to Bypassed.");
            continue;
        }

        $mplsStatus = $illStatus = $bdrStatus = null;
        $mplsUpdatedAt = $illUpdatedAt = $bdrUpdatedAt = null;

        // === Get latest MPLS status ===
        if (!empty($mpls)) {
            $latestMpls = $wgMplsModel->where('interface', $mpls)
                ->orderBy('updated_at', 'DESC')
                ->first();
            if ($latestMpls) {
                $mplsStatus = $latestMpls['status'] ?? null;
                $mplsUpdatedAt = $latestMpls['updated_at'] ?? null;
            }
        }

        // === Get latest ILL status ===
        if (!empty($ill)) {
            $latestIll = $wgIllModel->where('interface', $ill)
                ->orderBy('updated_at', 'DESC')
                ->first();
            if ($latestIll) {
                $illStatus = $latestIll['status'] ?? null;
                $illUpdatedAt = $latestIll['updated_at'] ?? null;
            }
        }
        
        // === Get latest BDR status ===
        if (!empty($bdr)) {
            $latestBdr = $WgBdrModel->where('interface', $bdr)
                ->orderBy('updated_at', 'DESC')
                ->first();
            if ($latestBdr) {
                $bdrStatus = $latestBdr['status'] ?? null;
                $bdrUpdatedAt = $latestBdr['updated_at'] ?? null;
            }
        }

        // === Skip branches with no WG data ===
        if (is_null($mplsStatus) && is_null($illStatus)) {
            log_message('info', "smsDownAlert: No WG data for {$branchName} ({$branchIP}), skipping.");
            continue;
        }

        // === Skip if both statuses are stale ===
        $mplsStale = $mplsUpdatedAt ? (strtotime($mplsUpdatedAt) < $staleLimit) : true;
        $illStale  = $illUpdatedAt ? (strtotime($illUpdatedAt) < $staleLimit) : true;

        if ($mplsStale && $illStale) {
            log_message('info', "smsDownAlert: Skipping {$branchName} — stale MPLS/ILL data (older than {$staleLimitMinutes} min).");
            continue;
        }

        // === Determine current state ===
        $currentStatus = (
            ($mplsStatus === 0 || $mplsStatus === '0') &&
            ($illStatus === 0 || $illStatus === '0')
        ) ? 'DOWN' : 'UP';

        // === Fetch previous status from DB ===
        $builder = $db->table('alert_log');
        $previous = $builder->where('branch_name', $branchName)->get()->getRow();

        if (!$previous) {
            // First-time entry → insert, no SMS yet
            $builder->insert([
                'branch_name'     => $branchName,
                'last_status'     => $currentStatus,
                'last_alert_sent' => $timeNow,
            ]);
            log_message('info', "smsDownAlert: Initial record added for {$branchName} ({$currentStatus}).");
            continue;
        }

        // === If state changed, send SMS ===
        if ($previous->last_status !== $currentStatus) {
            $timeHuman  = date('d-M-Y H:i:s');
            $statusText = ($currentStatus === 'DOWN') ? 'COMPLETELY DOWN' : 'BACK UP';

            // Send SMS
            $response = $this->sendSMS($mobile, $branchName, $branchIP, $timeHuman, $statusText);

            // Update alert_log
            $builder->where('branch_name', $branchName)->update([
                'last_status'     => $currentStatus,
                'last_alert_sent' => $timeNow,
            ]);

            log_message('info', "smsDownAlert: {$branchName} changed to {$currentStatus}. SMS response: {$response}");
            echo "→ SMS sent for {$branchName} ({$statusText})\n";
        }
    }

    echo "=== SMS Down Alert completed at {$timeNow} ===\n";
}


private function sendSMS($mobile, $branchName, $branchIP, $time, $status)
{
    $smsConfig = \App\Support\Monitoring::smsSettings();

    $smsURL   = $smsConfig['url'];
    $username = $smsConfig['username'];
    $password = $smsConfig['password'];
    $from     = $smsConfig['sender'] ?: $smsConfig['username'];

    $time = (new \DateTime('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    
    $message = "!!!! ALERT !!! The branch network {$branchName} (IP: {$branchIP}) is {$status} at {$time} -AUCB";

    // Each gateway has its own parameter dialect; http_build_query encodes every value
    // exactly once, and a base URL that already carries parameters is preserved.
    switch ($smsConfig['gateway']) {
        case 'soft-tech':
            // Soft-Tech: user + authkey + DLT templateid; smstype T = transactional.
            // The auth key travels in the password setting (labelled "Gateway Auth Key").
            $params = [
                'user'       => $username,
                'authkey'    => $password,
                'senderid'   => $from,
                'smstype'    => 'T',
                'templateid' => $smsConfig['template_id'],
                'mobile'     => $mobile,
                'message'    => $message,
            ];
            break;

        case 'vfirst':
        default:
            // VFirst: classic user/password bulk API.
            $params = [
                'username' => $username,
                'password' => $password,
                'from'     => $from,
                'to'       => $mobile,
                'text'     => $message,
                'category' => 'bulk',
            ];
            break;
    }

    $query = http_build_query($params);

    $url = $smsURL . (strpos($smsURL, '?') === false ? '?' : '&') . $query;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        log_message('error', "sendSMS CURL error for {$branchName}: {$curlErr}");
        return "CURL ERROR: {$curlErr}";
    }

    log_message('info', "sendSMS: {$branchName} ({$branchIP}) - {$status} -> {$mobile} | API Response: {$response}");
    return $response;
}


}