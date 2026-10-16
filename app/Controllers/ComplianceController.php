<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CoreConfigModel;
use App\Models\LocationModel;
use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;
use App\Models\WgBdrModel;
use App\Models\WgIllModel;
use App\Models\WgMplsModel;
use App\Services\Security\CveAdvisoryService;

/**
 * Fleet Security & RBI Cyber Security Framework Compliance Controller
 *
 * Provides comprehensive compliance auditing against Reserve Bank of India (RBI)
 * Cyber Security Framework for Banks:
 * - Warning/Authorized Usage Banner Verification (RBI CSF Perimeter Controls)
 * - Daily Configuration Backup & DR Freshness (RBI CSF Business Continuity)
 * - OS/Firmware Currency & Patch Auditing (RBI CSF Vulnerability Management)
 * - WAN Uplink Redundancy & Link Flap Stability (RBI CSF High Availability)
 */
class ComplianceController extends BaseController
{
    protected CoreConfigModel $coreConfigModel;
    protected PfInventoryModel $pfInventoryModel;
    protected PfStatusModel $pfStatusModel;
    protected LocationModel $locationModel;
    protected WgMplsModel $wgMplsModel;
    protected WgIllModel $wgIllModel;
    protected WgBdrModel $wgBdrModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->coreConfigModel  = new CoreConfigModel();
        $this->pfInventoryModel = new PfInventoryModel();
        $this->pfStatusModel    = new PfStatusModel();
        $this->locationModel    = new LocationModel();
        $this->wgMplsModel      = new WgMplsModel();
        $this->wgIllModel       = new WgIllModel();
        $this->wgBdrModel       = new WgBdrModel();
    }

    /**
     * Display the RBI Compliance Dashboard
     */
    public function index()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return redirect()->to('/loggedlive')->with('error', 'Administrator access required for Compliance & Auditing.');
        }

        $data['first_name'] = $user->first_name ?? 'Operator';

        $appName    = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'pfCentral';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl     = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? base_url();

        $data['title']       = strip_tags($appName) . ' 🔸 RBI Cyber Security Compliance Audit';
        $data['app_name']    = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url']     = $appUrl;

        // Calculate Fleet Compliance Metrics
        $auditData = $this->generateFleetAuditData();
        $data = array_merge($data, $auditData);

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('compliance/index', $data)
            . view('frontend/footer', $data);
    }

    /**
     * Export RBI Compliance Audit Report as CSV
     */
    public function exportCsv()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setBody('Access Denied');
        }

        $auditData = $this->generateFleetAuditData();
        $branches  = $auditData['devices'];

        $filename = 'RBI_CyberSecurity_Compliance_Audit_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV Header Block (RBI Inspection Metadata)
        fputcsv($output, ['========================================================================================================']);
        fputcsv($output, ['PFCENTRAL - FLEET SECURITY & REGULATORY COMPLIANCE AUDIT REPORT']);
        fputcsv($output, ['RBI Cyber Security Framework for Banks (Annex I & II Baseline Cyber Security Controls)']);
        fputcsv($output, ['Generated On: ' . date('Y-m-d H:i:s T') . ' | Auditor: ' . ($user->username ?? $user->email ?? 'pfCentral Admin')]);
        fputcsv($output, ['Fleet Overall Compliance Score: ' . $auditData['overallScore'] . '% | Grade: ' . $auditData['overallGrade']]);
        fputcsv($output, ['========================================================================================================']);
        fputcsv($output, []);

        // Column Titles
        fputcsv($output, [
            'Sr. No.',
            'Branch / Device Name',
            'Management IP',
            'Location',
            'Warning Banner (RBI Mandate)',
            'Daily Backup Status',
            'Latest Backup Date',
            'PF Version',
            'Firmware Status',
            'MPLS Uplink',
            'ILL Uplink',
            'BDR Uplink',
            'WAN Redundancy Level',
            'Branch Compliance Score (%)',
            'Audit Grade',
            'Compliance Status'
        ]);

        $sr = 1;
        foreach ($branches as $b) {
            fputcsv($output, [
                $sr++,
                $b['name'],
                $b['ip'],
                $b['location_name'],
                $b['bannerCompliant'] ? 'COMPLIANT (Active)' : 'NON-COMPLIANT (Missing)',
                $b['backupStatusLabel'],
                $b['latestBackupDate'] ?? 'Never',
                $b['pf_version'] ?? ($b['os_version'] ?: 'Unknown'),
                $b['osCompliant'] ? 'CURRENT' : 'REVIEW',
                $b['mpls_status'] ? 'UP' : 'DOWN',
                $b['ill_status'] ? 'UP' : 'DOWN',
                $b['bdr_status'] ? 'UP' : 'DOWN',
                $b['redundancyLabel'],
                $b['complianceScore'] . '%',
                $b['grade'],
                $b['isFullyCompliant'] ? 'PASSED' : 'ACTION REQUIRED'
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Exportable / Printable PDF Compliance Dossier for an individual branch firewall
     */
    public function deviceReport($id)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return redirect()->to('/loggedlive')->with('error', 'Administrator access required.');
        }

        $auditData = $this->generateFleetAuditData();
        $targetDevice = null;
        foreach ($auditData['devices'] as $d) {
            if ((int)$d['id'] === (int)$id) {
                $targetDevice = $d;
                break;
            }
        }

        if (!$targetDevice) {
            return $this->response->setStatusCode(404)->setBody('Branch device not found in inventory.');
        }

        // Fetch raw inventory and status records for full telemetry
        $inventory = $this->pfInventoryModel->find($id);
        $status    = $this->pfStatusModel->where('pf_id', $id)->first() ?? [];
        $loc       = !empty($inventory['location']) ? $this->locationModel->find($inventory['location']) : [];

        $device = array_merge($inventory ?? [], $status, [
            'location_name' => $loc['name'] ?? $targetDevice['location_name'],
            'wg_mpls'       => $loc['wg_mpls'] ?? '',
            'wg_ill'        => $loc['wg_ill'] ?? '',
            'wg_bdr'        => $loc['wg_bdr'] ?? '',
        ]);

        // Branch numeric code
        $branchCode = '';
        if (preg_match('/^(\d+)/', $device['name'] ?? '', $m)) {
            $branchCode = $m[1];
        }

        $auditRef = 'PFC-AUDIT-' . date('Y') . '-' . ($branchCode ?: sprintf('%04d', $id)) . '-' . $id;

        // CVE Advisory Analysis
        $buildId = $status['build_identifier'] ?? '';
        $osName  = $status['os_name'] ?? '';
        $cveData = CveAdvisoryService::getAdvisoriesForDevice($buildId, $osName);

        // Uptime parsing
        $uptimeDuration = $status['uptime_duration'] ?? '';
        preg_match('/up\s+((\d+)\s+days,?)?\s*((\d+):(\d+)|(\d+)\s+mins)?/', $uptimeDuration, $matches);
        $uptimeDays = isset($matches[2]) ? (int)$matches[2] : 0;
        $uptimeHours = $matches[4] ?? '0';
        $uptimeMinutes = $matches[5] ?? ($matches[6] ?? '0');
        $uptimeFormatted = ($uptimeHours === '0' && $uptimeMinutes === '0') 
            ? "{$uptimeDays} days" 
            : ($uptimeDays > 0 ? "{$uptimeDays} days, {$uptimeHours}:{$uptimeMinutes}" : "{$uptimeHours}:{$uptimeMinutes}");

        $data = [
            'device'          => $device,
            'compliance'      => $targetDevice,
            'cveData'         => $cveData,
            'branchCode'      => $branchCode,
            'auditRef'        => $auditRef,
            'uptimeFormatted' => $uptimeFormatted,
            'user'            => $user
        ];

        return view('compliance/device_report', $data);
    }

    /**
     * Get CVE Advisory Matrix for a device in JSON format
     */
    public function cveMatrix($id)
    {
        $status = $this->pfStatusModel->where('pf_id', $id)->first();
        $buildId = $status['build_identifier'] ?? '';
        $osName  = $status['os_name'] ?? '';
        $data = CveAdvisoryService::getAdvisoriesForDevice($buildId, $osName);
        return $this->response->setJSON($data);
    }

    /**
     * Trigger Live CVE & Security Advisory Synchronization from NIST NVD
     */
    public function syncCve()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error'   => 'Administrator access required to sync security advisories.'
            ]);
        }

        $err = null;
        $result = CveAdvisoryService::syncFromNvd($err);
        return $this->response->setJSON($result);
    }

    /**
     * Save user-defined mitigation or compensating control for a CVE
     */
    public function saveMitigation()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON([
                'success' => false,
                'error'   => 'Administrator access required to configure security mitigations.'
            ]);
        }

        $cveId       = trim((string)$this->request->getPost('cve'));
        $isMitigated = (bool)$this->request->getPost('is_mitigated');
        $note        = trim((string)$this->request->getPost('mitigation_note'));

        if (empty($cveId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'error'   => 'CVE Identifier is required.'
            ]);
        }

        $author = $user->username ?? $user->email ?? 'pfCentral Auditor';
        $res = CveAdvisoryService::saveMitigation($cveId, $isMitigated, $note, $author);

        return $this->response->setJSON($res);
    }

    /**
     * Compute full fleet audit details
     */
    private function generateFleetAuditData(): array
    {
        $db = \Config\Database::connect();

        // 1. Fetch devices with their status and location
        $devices = $this->pfInventoryModel
            ->select('pf_inventory.*, pf_status.banner, pf_status.os_version, pf_status.os_name, pf_status.build_identifier, pf_status.uptime_duration, pf_status.updated_at as last_checked, pf_status.ip as status_ip, pf_locations.name as location_name, pf_locations.wg_mpls, pf_locations.wg_ill, pf_locations.wg_bdr')
            ->join('pf_status', 'pf_status.pf_id = pf_inventory.id', 'left')
            ->join('pf_locations', 'pf_locations.id = pf_inventory.location', 'left')
            ->orderBy('pf_inventory.name', 'ASC')
            ->findAll();

        // 2. Fetch latest WireGuard link states for MPLS, ILL, BDR
        $mplsMap = [];
        $illMap  = [];
        $bdrMap  = [];

        try {
            $mplsLatest = $db->query("
                SELECT m1.interface, m1.status, m1.flap_count 
                FROM wg_mpls m1 
                INNER JOIN (SELECT interface, MAX(id) as max_id FROM wg_mpls GROUP BY interface) m2 
                ON m1.interface = m2.interface AND m1.id = m2.max_id
            ")->getResultArray();
            foreach ($mplsLatest as $m) {
                if (!empty($m['interface'])) {
                    $mplsMap[strtolower($m['interface'])] = $m;
                }
            }

            $illLatest = $db->query("
                SELECT i1.interface, i1.status, i1.flap_count 
                FROM wg_ill i1 
                INNER JOIN (SELECT interface, MAX(id) as max_id FROM wg_ill GROUP BY interface) i2 
                ON i1.interface = i2.interface AND i1.id = i2.max_id
            ")->getResultArray();
            foreach ($illLatest as $i) {
                if (!empty($i['interface'])) {
                    $illMap[strtolower($i['interface'])] = $i;
                }
            }

            $bdrLatest = $db->query("
                SELECT b1.interface, b1.status, b1.flap_count 
                FROM wg_bdr b1 
                INNER JOIN (SELECT interface, MAX(id) as max_id FROM wg_bdr GROUP BY interface) b2 
                ON b1.interface = b2.interface AND b1.id = b2.max_id
            ")->getResultArray();
            foreach ($bdrLatest as $b) {
                if (!empty($b['interface'])) {
                    $bdrMap[strtolower($b['interface'])] = $b;
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Error fetching WireGuard links in Compliance: ' . $e->getMessage());
        }

        // 3. Scan backups to find freshest backup per branch ID
        $backupDates = $this->getLatestBackupDateMap($devices);

        $totalDevices        = count($devices);
        $bannerCompliantCount = 0;
        $backupFreshCount    = 0;
        $osCurrencyCount     = 0;
        $multiWanCount       = 0;
        $totalScores         = 0;

        $processed = [];
        $now = time();

        foreach ($devices as $d) {
            $id = (int)$d['id'];
            $ip = !empty($d['status_ip']) ? $d['status_ip'] : (!empty($d['man_ip']) ? $d['man_ip'] : ($d['ip'] ?? '---'));

            // Check 1: Warning Banner (RBI Mandate)
            $hasBanner = isset($d['banner']) && $d['banner'] !== null && (string)$d['banner'] === '1';
            if ($hasBanner) {
                $bannerCompliantCount++;
            }

            // Check 2: Backup Freshness (< 24 hours = 100%, < 7 days = 50%, older = 20%, none = 0%)
            $latestBackupTimestamp = $backupDates[$id] ?? null;
            $backupScore = 0;
            $backupStatusLabel = 'No Backup';
            $backupStatusClass = 'badge-danger';

            if ($latestBackupTimestamp) {
                $ageHours = ($now - $latestBackupTimestamp) / 3600;
                if ($ageHours <= 24) {
                    $backupScore = 100;
                    $backupStatusLabel = '< 24h Compliant';
                    $backupStatusClass = 'badge-success';
                    $backupFreshCount++;
                } elseif ($ageHours <= 168) {
                    $backupScore = 50;
                    $backupStatusLabel = 'Recent (< 7d)';
                    $backupStatusClass = 'badge-info';
                } else {
                    $backupScore = 20;
                    $backupStatusLabel = 'Archived (> 7d)';
                    $backupStatusClass = 'badge-warning';
                }
            }

            // Check 3: PF Version Currency (pfSense CE 2.7.0+ or modern Plus 23.x/24.x)
            $rawBuild = trim((string)($d['build_identifier'] ?? ''));
            $rawOs    = trim((string)($d['os_version'] ?? ''));

            $vInfo = CveAdvisoryService::extractVersionInfo($rawBuild, $rawOs);
            $pfVersion = $vInfo['semVer'] ?? '';

            $osCompliant = false;
            if (!empty($pfVersion)) {
                // Support any modern pfSense CE >= 2.7.0 (2.7.x, 2.8.x, 2.9.x, 3.x) or pfSense Plus (23.x, 24.x)
                if (version_compare($pfVersion, '2.7.0', '>=') || version_compare($pfVersion, '23.0', '>=')) {
                    $osCompliant = true;
                    $osCurrencyCount++;
                }
            }
            $osScore = $osCompliant ? 100 : (!empty($pfVersion) ? 60 : 20);

            // Check 4: WAN Uplink Redundancy & Link States
            $wgMpls = !empty($d['wg_mpls']) ? strtolower($d['wg_mpls']) : null;
            $wgIll  = !empty($d['wg_ill']) ? strtolower($d['wg_ill']) : null;
            $wgBdr  = !empty($d['wg_bdr']) ? strtolower($d['wg_bdr']) : null;

            $mplsData = ($wgMpls && isset($mplsMap[$wgMpls])) ? $mplsMap[$wgMpls] : null;
            $illData  = ($wgIll && isset($illMap[$wgIll])) ? $illMap[$wgIll] : null;
            $bdrData  = ($wgBdr && isset($bdrMap[$wgBdr])) ? $bdrMap[$wgBdr] : null;

            $mplsUp = $mplsData ? ((int)$mplsData['status'] === 1) : false;
            $illUp  = $illData ? ((int)$illData['status'] === 1) : false;
            $bdrUp  = $bdrData ? ((int)$bdrData['status'] === 1) : false;

            $activeUplinks = ($mplsUp ? 1 : 0) + ($illUp ? 1 : 0) + ($bdrUp ? 1 : 0);
            if ($activeUplinks >= 2) {
                $wanScore = 100;
                $redundancyLabel = 'High Availability (' . $activeUplinks . ' Links Active)';
                $redundancyClass = 'badge-success';
                $multiWanCount++;
            } elseif ($activeUplinks === 1) {
                $wanScore = 55;
                $redundancyLabel = 'Degraded (1 Uplink Only)';
                $redundancyClass = 'badge-warning';
            } else {
                $wanScore = 0;
                $redundancyLabel = 'Critical (No Uplinks Active)';
                $redundancyClass = 'badge-danger';
            }

            // Weighted Composite RBI Score: Banner 30%, Backup 30%, WAN 25%, OS 15%
            $bannerScore = $hasBanner ? 100 : 0;
            $composite = round(($bannerScore * 0.30) + ($backupScore * 0.30) + ($wanScore * 0.25) + ($osScore * 0.15));
            $totalScores += $composite;

            // Grade
            if ($composite >= 90) {
                $grade = 'A';
                $gradeColor = '#10b981';
            } elseif ($composite >= 75) {
                $grade = 'B';
                $gradeColor = '#0ea5e9';
            } elseif ($composite >= 50) {
                $grade = 'C';
                $gradeColor = '#f59e0b';
            } else {
                $grade = 'F';
                $gradeColor = '#ef4444';
            }

            $isFullyCompliant = ($hasBanner && $backupScore === 100 && $activeUplinks >= 1);

            $processed[] = [
                'id'                 => $id,
                'name'               => $d['name'] ?: 'UNKNOWN',
                'ip'                 => $ip,
                'location_name'      => $d['location_name'] ?? 'Unknown Site',
                'bannerCompliant'    => $hasBanner,
                'backupScore'        => $backupScore,
                'backupStatusLabel'  => $backupStatusLabel,
                'backupStatusClass'  => $backupStatusClass,
                'latestBackupDate'   => $latestBackupTimestamp ? date('d-M-Y H:i', $latestBackupTimestamp) : null,
                'os_version'         => $pfVersion ?: ($rawBuild ?: ($rawOs ?: '---')),
                'pf_version'         => $pfVersion ?: ($rawBuild ?: ($rawOs ?: '---')),
                'build_identifier'   => $rawBuild,
                'osCompliant'        => $osCompliant,
                'mpls_status'        => $mplsUp,
                'mpls_flaps'         => $mplsData['flap_count'] ?? 0,
                'ill_status'         => $illUp,
                'ill_flaps'          => $illData['flap_count'] ?? 0,
                'bdr_status'         => $bdrUp,
                'bdr_flaps'          => $bdrData['flap_count'] ?? 0,
                'activeUplinks'      => $activeUplinks,
                'redundancyLabel'    => $redundancyLabel,
                'redundancyClass'    => $redundancyClass,
                'complianceScore'    => $composite,
                'grade'              => $grade,
                'gradeColor'         => $gradeColor,
                'isFullyCompliant'   => $isFullyCompliant
            ];
        }

        $overallScore = $totalDevices > 0 ? round($totalScores / $totalDevices) : 0;

        if ($overallScore >= 90) {
            $overallGrade = 'A';
            $overallGradeColor = '#10b981';
        } elseif ($overallScore >= 75) {
            $overallGrade = 'B';
            $overallGradeColor = '#0ea5e9';
        } elseif ($overallScore >= 50) {
            $overallGrade = 'C';
            $overallGradeColor = '#f59e0b';
        } else {
            $overallGrade = 'F';
            $overallGradeColor = '#ef4444';
        }

        return [
            'devices'              => $processed,
            'totalDevices'         => $totalDevices,
            'overallScore'         => $overallScore,
            'overallGrade'         => $overallGrade,
            'overallGradeColor'    => $overallGradeColor,
            'bannerCompliantCount' => $bannerCompliantCount,
            'bannerPercent'        => $totalDevices > 0 ? round(($bannerCompliantCount / $totalDevices) * 100) : 0,
            'backupFreshCount'     => $backupFreshCount,
            'backupPercent'        => $totalDevices > 0 ? round(($backupFreshCount / $totalDevices) * 100) : 0,
            'osCurrencyCount'      => $osCurrencyCount,
            'osPercent'            => $totalDevices > 0 ? round(($osCurrencyCount / $totalDevices) * 100) : 0,
            'multiWanCount'        => $multiWanCount,
            'multiWanPercent'      => $totalDevices > 0 ? round(($multiWanCount / $totalDevices) * 100) : 0
        ];
    }

    /**
     * Map branch/firewall id to the most recent backup timestamp found in database and storage.
     *
     * @param array $devices List of device records from pf_inventory
     * @return array<int, int> Map of pf_id => unix_timestamp
     */
    private function getLatestBackupDateMap(array $devices = []): array
    {
        $map = [];

        // 1. Primary source: query the pf_backups table for the latest backup timestamp per device
        try {
            $db = \Config\Database::connect();
            $backupRows = $db->table('pf_backups')
                ->select('pf_id, MAX(created_at) as latest_created_at')
                ->groupBy('pf_id')
                ->get()
                ->getResultArray();

            foreach ($backupRows as $row) {
                $devId = (int)$row['pf_id'];
                if ($devId > 0 && !empty($row['latest_created_at'])) {
                    $ts = strtotime($row['latest_created_at']);
                    if ($ts && $ts > 0) {
                        $map[$devId] = $ts;
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'ComplianceController: error querying pf_backups: ' . $e->getMessage());
        }

        // 2. Secondary source / filesystem scan: check device backup folders for any new or unindexed XMLs
        $backupRoots = [
            WRITEPATH . 'pfcentral_backup/',
            WRITEPATH . 'noc_backup/'
        ];

        // Build lookup maps for device name and branch code
        $nameToId = [];
        $codeToId = [];
        foreach ($devices as $dev) {
            $dId = (int)($dev['id'] ?? 0);
            $dName = trim((string)($dev['name'] ?? ''));
            if ($dId <= 0 || $dName === '') {
                continue;
            }
            $safeName = trim((string)preg_replace('/[^A-Za-z0-9_\-]/', '_', $dName), '_');
            $nameToId[strtolower($safeName)] = $dId;
            $nameToId[strtolower($dName)] = $dId;

            if (preg_match('/^(\d+)/', $dName, $m)) {
                $codeToId[$m[1]] = $dId;
            }
        }

        foreach ($backupRoots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $entries = scandir($root);
            if (!$entries) {
                continue;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $fullPath = $root . $entry;

                // Case A: Subdirectory per device (e.g. 1001-MAIN_BRANCH__AKOLA)
                if (is_dir($fullPath)) {
                    $devId = $nameToId[strtolower($entry)] ?? null;
                    if (!$devId && preg_match('/^(\d+)/', $entry, $m)) {
                        $devId = $codeToId[$m[1]] ?? null;
                    }

                    if ($devId) {
                        $subFiles = scandir($fullPath);
                        if ($subFiles) {
                            foreach ($subFiles as $f) {
                                if (!str_ends_with($f, '.xml')) {
                                    continue;
                                }

                                $ts = $this->parseBackupTimestamp($f, $fullPath . '/' . $f);
                                if ($ts > ($map[$devId] ?? 0)) {
                                    $map[$devId] = $ts;
                                }
                            }
                        }
                    }
                    continue;
                }

                // Case B: Direct XML file in root directory (e.g. config-<id>-<ts>.xml or backup_<code/date>.xml)
                if (str_ends_with($entry, '.xml')) {
                    if (preg_match('/^config-(\d+)-(\d+)\.xml$/', $entry, $m)) {
                        $devId = (int)$m[1];
                        $ts    = (int)$m[2];
                        if ($ts > ($map[$devId] ?? 0)) {
                            $map[$devId] = $ts;
                        }
                    } elseif (preg_match('/^config-(\d+)-/', $entry, $m)) {
                        $devId = (int)$m[1];
                        $mtime = filemtime($fullPath);
                        if ($mtime > ($map[$devId] ?? 0)) {
                            $map[$devId] = $mtime;
                        }
                    } elseif (preg_match('/^backup_(\d{4})_(\d{8})_(\d{6})\.xml$/', $entry, $m)) {
                        $code = $m[1];
                        $devId = $codeToId[$code] ?? null;
                        if ($devId) {
                            $ts = strtotime($m[2] . ' ' . substr($m[3], 0, 2) . ':' . substr($m[3], 2, 2) . ':' . substr($m[3], 4, 2));
                            if ($ts > ($map[$devId] ?? 0)) {
                                $map[$devId] = $ts;
                            }
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Helper to parse timestamp from backup filename or file mtime
     */
    private function parseBackupTimestamp(string $filename, string $fullPath): int
    {
        // 1. backup_{branchCode}_{YYYYMMDD}_{HHMMSS}.xml
        if (preg_match('/backup_\d+_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})\.xml$/', $filename, $m)) {
            return mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
        }

        // 2. backup_{YYYYMMDD}_{HHMMSS}.xml
        if (preg_match('/backup_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})\.xml$/', $filename, $m)) {
            return mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[3], (int)$m[1]);
        }

        // 3. config-{id}-{timestamp}.xml
        if (preg_match('/config-\d+-(\d+)\.xml$/', $filename, $m)) {
            return (int)$m[1];
        }

        // 4. Fallback to file modification time
        return @filemtime($fullPath) ?: 0;
    }
}
