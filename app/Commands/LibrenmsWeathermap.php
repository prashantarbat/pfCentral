<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\CoreConfigModel;

class LibrenmsWeathermap extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Integration';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'librenms:weathermap';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a live PHP Weathermap configuration file based on LibreNMS active branches & WireGuard interfaces.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'librenms:weathermap [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--output'      => 'Target file path to save the generated conf. Defaults to stdout.',
        '--hub-mpls'   => 'The identifier or label for the central VyOS MPLS DC controller. Defaults to "VyOS-MPLS".',
        '--hub-inet'   => 'The identifier or label for the central VyOS Internet DC controller. Defaults to "VyOS-INET".',
        '--rrd-path'    => 'The absolute path prefix for LibreNMS RRD files. Defaults to "/opt/librenms/rrd/".',
        '--mode'        => 'Interface selection mode: "wireguard" (default, tunnels wgXXX) or "physical" (physical NICs igc0/igc1/igb0/igb1).',
        '--dry-run'     => 'Simulates discovery without actually generating the file.',
    ];

    /**
     * Actually execute the command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        CLI::write('⚡ Starting Dynamic LibreNMS Weathermap Configuration Generator...', 'cyan');

        // Respect the integration switch: no LibreNMS, no weathermap config to generate.
        if (! \App\Support\Monitoring::librenmsEnabled()) {
            CLI::write('⏭️  LibreNMS integration is disabled in Core Settings — weathermap generation skipped.', 'yellow');

            return EXIT_SUCCESS;
        }

        // 1. Resolve configuration parameters (DB -> Env -> Defaults)
        $coreConfigModel = new CoreConfigModel();
        $dbConfigs = $coreConfigModel->whereIn('name', [
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

        $isDryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');
        
        // Robust option parsing with raw argv fallback
        $outputPath = CLI::getOption('output') ?: null;
        $rrdBase = CLI::getOption('rrd-path') ?: '/opt/librenms/rrd/';
        $hubMplsName = CLI::getOption('hub-mpls') ?: 'VyOS-MPLS';
        $hubInetName = CLI::getOption('hub-inet') ?: 'VyOS-INET';
        $mode = CLI::getOption('mode') ?: 'wireguard';

        if (isset($_SERVER['argv'])) {
            foreach ($_SERVER['argv'] as $arg) {
                if (str_starts_with($arg, '--output=')) {
                    $outputPath = substr($arg, 9);
                }
                if (str_starts_with($arg, '--rrd-path=')) {
                    $rrdBase = substr($arg, 11);
                }
                if (str_starts_with($arg, '--hub-mpls=')) {
                    $hubMplsName = substr($arg, 11);
                }
                if (str_starts_with($arg, '--hub-inet=')) {
                    $hubInetName = substr($arg, 11);
                }
                if (str_starts_with($arg, '--mode=')) {
                    $mode = substr($arg, 7);
                }
                if ($arg === '--dry-run') {
                    $isDryRun = true;
                }
            }
        }

        // Strip any surrounding quotes passed from terminal
        if ($outputPath) {
            $outputPath = trim($outputPath, '"\'');
        }
        if ($rrdBase) {
            $rrdBase = trim($rrdBase, '"\'');
        }
        if ($hubMplsName) {
            $hubMplsName = trim($hubMplsName, '"\'');
        }
        if ($hubInetName) {
            $hubInetName = trim($hubInetName, '"\'');
        }
        if ($mode) {
            $mode = trim($mode, '"\'');
        }

        // Normalize discovery mode
        $mode = strtolower($mode);
        if (!in_array($mode, ['wireguard', 'physical', 'wg', 'nic'])) {
            $mode = 'wireguard';
        }
        if ($mode === 'wg') {
            $mode = 'wireguard';
        }
        if ($mode === 'nic') {
            $mode = 'physical';
        }

        // Ensure RRD base path ends with a slash
        $rrdBase = rtrim($rrdBase, '/') . '/';

        CLI::write("ℹ️  Resolved Configuration Settings:");
        CLI::write("   • API URL:        {$librenmsApiUrl}");
        CLI::write("   • Hub (MPLS):     {$hubMplsName}");
        CLI::write("   • Hub (Internet): {$hubInetName}");
        CLI::write("   • RRD Base:       {$rrdBase}");
        CLI::write("   • Discovery Mode: {$mode}");
        if ($outputPath) {
            CLI::write("   • Output Path:    {$outputPath}");
        } else {
            CLI::write("   • Output Path:    STDOUT (Printing to screen)");
        }

        // 2. Query active branches from our pf_locations / pf_inventory table
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
        CLI::write("📊 Found {$branchCount} active branch locations in database.", 'green');

        if ($branchCount === 0) {
            CLI::error('❌ No active locations found to map. Exiting.');
            return;
        }

        // 3. Query all devices in LibreNMS via REST API once to build a lookup mapping IP -> device details
        CLI::write('📡 Fetching devices list from LibreNMS API...');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$librenmsApiUrl}/devices");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: {$librenmsApiToken}"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            CLI::error("❌ Failed to query LibreNMS API (HTTP Code: {$httpCode}). Please verify credentials.");
            return;
        }

        $lnmsDevicesData = json_decode($response, true);
        $lnmsDevices = $lnmsDevicesData['devices'] ?? [];
        CLI::write('   ✅ Fetched ' . count($lnmsDevices) . ' devices from LibreNMS.', 'green');

        // Create IP/Hostname -> Device ID and Display mapping table
        $deviceLookup = [];
        foreach ($lnmsDevices as $d) {
            $hostname = strtolower(trim($d['hostname'] ?? ''));
            $ip = strtolower(trim($d['ip'] ?? ''));
            $id = $d['device_id'];

            if (!empty($hostname)) {
                $deviceLookup[$hostname] = $d;
            }
            if (!empty($ip)) {
                $deviceLookup[$ip] = $d;
            }
        }

        // 4. Discover interfaces and query ports for each branch
        CLI::write('🔎 Discovering interface port IDs for all branches...');
        $branchMappings = [];
        $index = 0;

        foreach ($activeBranches as $branch) {
            $index++;
            $name = trim($branch['name']);
            $ip = strtolower(trim($branch['ip'] ?? ''));

            CLI::write("   ({$index}/{$branchCount}) Resolving branch '{$name}' [IP: {$ip}]...", 'cyan');

            $device = null;
            if (!empty($ip) && isset($deviceLookup[$ip])) {
                $device = $deviceLookup[$ip];
            } elseif (isset($deviceLookup[strtolower($name)])) {
                $device = $deviceLookup[strtolower($name)];
            }

            if (!$device) {
                CLI::write("      ⚠️  Warning: Device not discovered/found in LibreNMS. Links will render as offline.", 'yellow');
                $branchMappings[] = [
                    'db' => $branch,
                    'device_id' => null,
                    'mpls_port' => null,
                    'ill_port'  => null,
                ];
                continue;
            }

            $deviceId = $device['device_id'];
            $deviceHostname = $device['hostname'];

            // Query ports for this device with explicit column selection
            $portsCh = curl_init();
            curl_setopt($portsCh, CURLOPT_URL, "{$librenmsApiUrl}/devices/{$deviceId}/ports?columns=port_id,ifName,ifDescr,ifAlias,ifType");
            curl_setopt($portsCh, CURLOPT_HTTPHEADER, ["X-Auth-Token: {$librenmsApiToken}"]);
            curl_setopt($portsCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($portsCh, CURLOPT_TIMEOUT, 10);
            $portsRes = curl_exec($portsCh);
            $portsCode = curl_getinfo($portsCh, CURLINFO_HTTP_CODE);
            curl_close($portsCh);

            $mplsPort = null;
            $illPort = null;

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

            if ($mplsPort) {
                $matchedIface = $mplsPort['ifName'] . (!empty($mplsPort['ifAlias']) ? " (" . $mplsPort['ifAlias'] . ")" : "");
                CLI::write("      ✅ MPLS Interface matched: {$matchedIface} -> Port ID: {$mplsPort['port_id']}");
            } else {
                CLI::write("      ⚠️  MPLS Interface ({$branch['wg_mpls']}) not found in LibreNMS.", 'yellow');
            }

            if ($illPort) {
                $matchedIface = $illPort['ifName'] . (!empty($illPort['ifAlias']) ? " (" . $illPort['ifAlias'] . ")" : "");
                CLI::write("      ✅ Internet/ILL Interface matched: {$matchedIface} -> Port ID: {$illPort['port_id']}");
            } else {
                CLI::write("      ⚠️  Internet/ILL Interface ({$branch['wg_ill']}) not found in LibreNMS.", 'yellow');
            }

            $branchMappings[] = [
                'db' => $branch,
                'device_id' => $deviceId,
                'device_hostname' => $deviceHostname,
                'mpls_port' => $mplsPort,
                'ill_port'  => $illPort,
            ];
        }

        // 5. Generate Weathermap configuration content
        if ($isDryRun) {
            CLI::write('🔬 [Dry-Run] Discovery successful. Skipping configuration generation.', 'yellow');
            return;
        }

        CLI::write('✍️  Compiling Weathermap configuration file...', 'cyan');

        $out = [];
        $out[] = "# ==========================================================================";
        $out[] = "# pfCentral - Live Operations Topology Map";
        $out[] = "# Automatically generated on " . date('Y-m-d H:i:s');
        $out[] = "# ==========================================================================";
        $out[] = "";

        // Global Configurations
        $out[] = "# Global Styling & Colors";
        $out[] = "BACKGROUND images/dark_noc_bg.png";
        $out[] = "HTMLSTYLE floating";
        $out[] = "TITLE pfCentral Platform - Network Topology Map";
        $out[] = "TIMEPOS 10 30 Live: %Y-%m-%d %H:%M:%S";
        $out[] = "FONTDEFINE 100 docs/fonts/LiberationSans-Regular.ttf 8";
        $out[] = "FONTDEFINE 200 docs/fonts/LiberationSans-Bold.ttf 10";
        $out[] = "";
        $out[] = "# Color Scale for Bandwidth Utilization";
        $out[] = "KEYPOS DEFAULT 30 920 Bandwidth Legend";
        $out[] = "KEYTEXTCOLOR 241 245 249";
        $out[] = "KEYBGCOLOR 15 23 42";
        $out[] = "BGCOLOR 15 23 42";
        $out[] = "TITLECOLOR 241 245 249";
        $out[] = "";
        $out[] = "# Bandwidth Scales (Green -> Yellow -> Orange -> Red)";
        $out[] = "SCALE DEFAULT 0    10   148 163 184   # Gray: Idle / Stale";
        $out[] = "SCALE DEFAULT 10   50    34 197  94   # Green: Normal (10-50%)";
        $out[] = "SCALE DEFAULT 50   85   234 179   8   # Yellow/Orange: Alert (50-85%)";
        $out[] = "SCALE DEFAULT 85   100  239  68  68   # Red: Critical (85-100%)";
        $out[] = "";

        // DC Hubs
        $out[] = "# -------------------------------------------------------------";
        $out[] = "# CENTRAL VYOS HUBS (Data Center)";
        $out[] = "# -------------------------------------------------------------";
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

        // Active Spoke Nodes & Links
        $out[] = "# -------------------------------------------------------------";
        $out[] = "# BRANCH OFFICE SPOKE NODES & DUAL WIREGUARD LINKS";
        $out[] = "# -------------------------------------------------------------";

        // Ellipse parameters for layout: center at (960, 540), radius R_x = 760, R_y = 440
        $cx = 960;
        $cy = 540;
        $rx = 760;
        $ry = 440;

        $nodeIndex = 0;
        foreach ($branchMappings as $mapping) {
            $branch = $mapping['db'];
            $branchName = trim($branch['name']);
            
            // Clean Node ID for Weathermap (alphanumeric + dashes only)
            $nodeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $branchName);
            $nodeId = 'BR-' . trim($nodeId, '-');

            // Calculate elliptical coordinates
            $angle = (2 * M_PI * $nodeIndex) / $branchCount;
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

            // Link 1: MPLS (WireGuard)
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
                $out[] = "    # TARGET none";
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
                $out[] = "    # TARGET none";
            }
            $out[] = "    OUTLINECOLOR 51 65 85";
            $out[] = "    BWLABEL bits";
            $out[] = "    BWSTYLE classic";
            $out[] = "";
        }

        $confContent = implode("\n", $out);

        // 6. Output target handling
        if ($outputPath) {
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            if (file_put_contents($outputPath, $confContent) !== false) {
                CLI::write("🎉 Configuration successfully generated and saved to: {$outputPath}", 'green');
            } else {
                CLI::error("❌ Failed to write configuration file to: {$outputPath}");
            }
        } else {
            // Print directly to console
            CLI::write("\n--- START OF WEATHERMAP CONFIGURATION ---", 'yellow');
            echo $confContent . "\n";
            CLI::write("--- END OF WEATHERMAP CONFIGURATION ---\n", 'yellow');
        }
    }
}
