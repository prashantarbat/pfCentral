<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PfInventoryModel;
use App\Models\CoreConfigModel;

class LibrenmsSync extends BaseCommand
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
    protected $name = 'librenms:sync';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Synchronizes active devices from pfCentral to LibreNMS REST API.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'librenms:sync [options]';

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
        '--dry-run' => 'Run the sync simulation without adding/deleting devices.',
    ];

    /**
     * Actually execute the command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        CLI::write('⚡ Starting LibreNMS Device Synchronization...', 'cyan');

        // Respect the integration switch: an operator who turned LibreNMS off in Core Settings
        // does not expect the sync daemon to keep talking to the API behind their back.
        if (! \App\Support\Monitoring::librenmsEnabled()) {
            CLI::write('⏭️  LibreNMS integration is disabled in Core Settings — sync skipped.', 'yellow');

            return EXIT_SUCCESS;
        }

        // 1. Resolve configurations with Fallback Order:
        //    Prio 1: DB (core_config table) -> Prio 2: ENV (.env) -> Prio 3: Defaults
        $coreConfigModel = new CoreConfigModel();
        $dbConfigs = $coreConfigModel->whereIn('name', [
            'librenms_api_url',
            'librenms_api_token',
            'librenms_snmp_community',
            'librenms_snmp_version'
        ])->findAll();

        $configMap = array_column($dbConfigs, 'value', 'name');

        $librenmsApiUrl = $configMap['librenms_api_url'] 
            ?? env('LIBRENMS_API_URL') 
            ?? 'http://localhost/api/v0';

        $librenmsApiToken = $configMap['librenms_api_token'] 
            ?? env('LIBRENMS_API_TOKEN') 
            ?? 'YOUR_LIBRENMS_API_TOKEN';

        $snmpCommunity = $configMap['librenms_snmp_community'] 
            ?? env('LIBRENMS_SNMP_COMMUNITY') 
            ?? 'your-secure-snmp-community';

        $snmpVersion = $configMap['librenms_snmp_version'] 
            ?? env('LIBRENMS_SNMP_VERSION') 
            ?? 'v2c';

        $isDryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');

        CLI::write("ℹ️  Resolved Configuration Settings:");
        CLI::write("   • API URL:   {$librenmsApiUrl}");
        CLI::write("   • SNMP Ver:  {$snmpVersion}");
        CLI::write("   • SNMP Comm: " . str_repeat('*', strlen($snmpCommunity)));
        CLI::write("   • API Token: " . substr($librenmsApiToken, 0, 4) . str_repeat('*', max(4, strlen($librenmsApiToken) - 4)));

        if ($isDryRun) {
            CLI::write('🔬 Running in DRY-RUN mode. No changes will be applied.', 'yellow');
        }

        // 2. Fetch active branch devices from pf_devices, cross-referencing pf_inventory settings & pf_locations coordinates
        $db = \Config\Database::connect();
        $builder = $db->table('pf_devices');
        $builder->select('pf_devices.br_name, pf_devices.lan_ip, pf_inventory.active, pf_inventory.bypass, pf_locations.address, pf_locations.latitude, pf_locations.longitude');
        $builder->join('pf_inventory', 'TRIM(pf_inventory.name) = TRIM(pf_devices.br_name)', 'left');
        $builder->join('pf_locations', 'TRIM(pf_locations.name) = TRIM(pf_devices.br_name)', 'left');
        $pfDevices = $builder->get()->getResultArray();

        $targetHosts = [];
        foreach ($pfDevices as $dev) {
            $name = trim($dev['br_name']);
            $ip   = trim($dev['lan_ip']);

            // Default to active and not bypassed if no matching inventory record exists
            $isActive   = isset($dev['active']) ? (int)$dev['active'] === 1 : true;
            $isBypassed = isset($dev['bypass']) ? (int)$dev['bypass'] === 1 : false;

            if ($isActive && !$isBypassed && !empty($ip)) {
                // Construct location string using the full branch name and GPS coordinates for LibreNMS World Map
                $lat = !empty($dev['latitude']) ? trim($dev['latitude']) : '';
                $lon = !empty($dev['longitude']) ? trim($dev['longitude']) : '';

                $locationStr = (!empty($lat) && !empty($lon)) ? "{$name} [{$lat}, {$lon}]" : $name;

                $targetHosts[$ip] = [
                    'hostname' => $ip,
                    'display'  => $name,
                    'location' => $locationStr
                ];
            }
        }

        CLI::write('📊 Found ' . count($targetHosts) . ' active pfCentral devices in database.', 'green');

        // 3. Fetch existing devices in LibreNMS
        CLI::write('📡 Querying LibreNMS devices API...');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "{$librenmsApiUrl}/devices");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: {$librenmsApiToken}"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            CLI::error("❌ Failed to query LibreNMS API (HTTP Code: {$httpCode}). Please check your API URL and Token.");
            return;
        }

        $lnmsData = json_decode($response, true);
        $allExistingDevices = [];

        if (isset($lnmsData['devices'])) {
            foreach ($lnmsData['devices'] as $device) {
                $hostname = trim($device['hostname']);
                $ip = isset($device['ip']) ? trim($device['ip']) : '';

                // Exclude any IP addresses in the 10.37.x.x range entirely from synchronization management
                $isExcludedIp = str_starts_with($hostname, '10.37.') || (!empty($ip) && str_starts_with($ip, '10.37.'));
                if ($isExcludedIp) {
                    continue;
                }

                $isManagedBranch = (isset($device['purpose']) && $device['purpose'] === 'pfSense Branch Router')
                    || (isset($device['os']) && strtolower($device['os']) === 'pfsense');

                $deviceInfo = [
                    'device_id'  => $device['device_id'],
                    'hostname'   => $hostname,
                    'ip'         => $ip,
                    'display'    => $device['display'] ?? '',
                    'location'   => $device['location'] ?? '',
                    'purpose'    => $device['purpose'] ?? '',
                    'is_managed' => $isManagedBranch
                ];

                // Build case-insensitive lookup table for ALL existing devices to prevent duplicates
                $allExistingDevices[strtolower($hostname)] = $deviceInfo;
                if (!empty($ip)) {
                    $allExistingDevices[strtolower($ip)] = $deviceInfo;
                }
            }
        }

        // 4. Perform delta synchronization
        $added   = 0;
        $updated = 0;
        $removed = 0;

        // A. Add missing devices or update existing ones
        $communities = array_map('trim', explode(',', $snmpCommunity));
        if (empty($communities) || (count($communities) === 1 && $communities[0] === '')) {
            $communities = ['public'];
        }

        foreach ($targetHosts as $ip => $meta) {
            $ipLower = strtolower($ip);
            
            if (!isset($allExistingDevices[$ipLower])) {
                // Add new device
                CLI::write("➕ [Add Pending] Device: {$meta['display']} ({$ip})", 'yellow');

                if (!$isDryRun) {
                    $registeredSuccessfully = false;
                    
                    foreach ($communities as $index => $candidateCommunity) {
                        $prioNum = $index + 1;
                        CLI::write("   ⚡ [Attempt #{$prioNum}] Trying SNMP Community: '{$candidateCommunity}'...");

                        $postData = json_encode([
                            'hostname'             => $ip,
                            'version'              => $snmpVersion,
                            'community'            => $candidateCommunity,
                            'display'              => $meta['display'],
                            'location'             => $meta['location'],
                            'purpose'              => 'pfSense Branch Router',
                            'override_sysLocation' => 1
                        ]);

                        $addCh = curl_init("{$librenmsApiUrl}/devices");
                        curl_setopt($addCh, CURLOPT_CUSTOMREQUEST, 'POST');
                        curl_setopt($addCh, CURLOPT_POSTFIELDS, $postData);
                        curl_setopt($addCh, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($addCh, CURLOPT_HTTPHEADER, [
                            "X-Auth-Token: {$librenmsApiToken}",
                            "Content-Type: application/json"
                        ]);
                        
                        $addRes  = json_decode(curl_exec($addCh), true);
                        $addCode = curl_getinfo($addCh, CURLINFO_HTTP_CODE);
                        curl_close($addCh);

                        if ($addCode === 200 || $addCode === 201) {
                            CLI::write("   ✅ Successfully registered {$ip} using community '{$candidateCommunity}' (ID: " . ($addRes['device_id'] ?? 'unknown') . ").", 'green');
                            $added++;
                            $registeredSuccessfully = true;
                            break; // Exit community attempts loop on success
                        } else {
                            $errMsg = $addRes['message'] ?? 'SNMP connection/bind failure';
                            CLI::write("   ⚠️  Failed with community '{$candidateCommunity}': {$errMsg}", 'yellow');
                        }
                    }

                    if (!$registeredSuccessfully) {
                        CLI::error("   ❌ Failed to add {$ip} using any of the configured SNMP communities.");
                    }
                }
            } else {
                // Existing device! Check if display name, location coordinates, or purpose changed
                $existing = $allExistingDevices[$ipLower];
                $existingDisplay  = trim($existing['display']);
                $existingLocation = trim($existing['location']);
                $existingPurpose  = trim($existing['purpose']);

                $targetDisplay  = trim($meta['display']);
                $targetLocation = trim($meta['location']);
                $targetPurpose  = 'pfSense Branch Router';

                $fieldsToUpdate = [];
                $dataToUpdate   = [];

                if ($existingDisplay !== $targetDisplay) {
                    $fieldsToUpdate[] = 'display';
                    $dataToUpdate[]   = $targetDisplay;
                }

                if ($existingLocation !== $targetLocation) {
                    $fieldsToUpdate[] = 'location';
                    $dataToUpdate[]   = $targetLocation;
                    $fieldsToUpdate[] = 'override_sysLocation';
                    $dataToUpdate[]   = 1;
                }

                if ($existingPurpose !== $targetPurpose) {
                    $fieldsToUpdate[] = 'purpose';
                    $dataToUpdate[]   = $targetPurpose;
                }

                if (!empty($fieldsToUpdate)) {
                    CLI::write("🔄 [Update Pending] Device: {$targetDisplay} ({$ip})", 'cyan');
                    foreach ($fieldsToUpdate as $idx => $f) {
                        $oldVal = ($f === 'display') ? $existingDisplay : (($f === 'location') ? $existingLocation : (($f === 'purpose') ? $existingPurpose : ''));
                        CLI::write("   • Field '{$f}': '{$oldVal}' ➔ '{$dataToUpdate[$idx]}'");
                    }

                    if (!$isDryRun) {
                        $patchData = json_encode([
                            'field' => $fieldsToUpdate,
                            'data'  => $dataToUpdate
                        ]);

                        // Use device hostname from LibreNMS for the patch URL
                        $lnmsIdentifier = $existing['hostname'];
                        $upCh = curl_init("{$librenmsApiUrl}/devices/{$lnmsIdentifier}");
                        curl_setopt($upCh, CURLOPT_CUSTOMREQUEST, 'PATCH');
                        curl_setopt($upCh, CURLOPT_POSTFIELDS, $patchData);
                        curl_setopt($upCh, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($upCh, CURLOPT_HTTPHEADER, [
                            "X-Auth-Token: {$librenmsApiToken}",
                            "Content-Type: application/json"
                        ]);

                        $upRes  = json_decode(curl_exec($upCh), true);
                        $upCode = curl_getinfo($upCh, CURLINFO_HTTP_CODE);
                        curl_close($upCh);

                        if ($upCode === 200) {
                            CLI::write("   ✅ Successfully updated device details in LibreNMS.", 'green');
                            $updated++;
                        } else {
                            $errMsg = $upRes['message'] ?? 'API response error';
                            CLI::error("   ❌ Failed to update device details: {$errMsg}");
                        }
                    } else {
                        $updated++;
                    }
                }
            }
        }

        // B. Remove deactivated devices (Only delete devices that are flagged as managed branches in LibreNMS)
        if (isset($lnmsData['devices'])) {
            foreach ($lnmsData['devices'] as $device) {
                $hostname = trim($device['hostname']);
                $ip = isset($device['ip']) ? trim($device['ip']) : '';

                // Exclude any IP addresses in the 10.37.x.x range entirely from synchronization management
                $isExcludedIp = str_starts_with($hostname, '10.37.') || (!empty($ip) && str_starts_with($ip, '10.37.'));
                if ($isExcludedIp) {
                    continue;
                }

                $isManagedBranch = (isset($device['purpose']) && $device['purpose'] === 'pfSense Branch Router')
                    || (isset($device['os']) && strtolower($device['os']) === 'pfsense');

                if (!$isManagedBranch) {
                    continue; // Skip non-managed devices for safety
                }

                // Check if this managed device exists in our target hosts list (by either hostname or IP)
                $existsInTarget = isset($targetHosts[$hostname]) || (!empty($ip) && isset($targetHosts[$ip]));

                if (!$existsInTarget) {
                    $id = $device['device_id'];
                    CLI::write("➖ [Remove Pending] Device: {$hostname} (ID: {$id})", 'red');

                    if (!$isDryRun) {
                        $delCh = curl_init("{$librenmsApiUrl}/devices/{$hostname}");
                        curl_setopt($delCh, CURLOPT_CUSTOMREQUEST, 'DELETE');
                        curl_setopt($delCh, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($delCh, CURLOPT_HTTPHEADER, ["X-Auth-Token: {$librenmsApiToken}"]);
                        
                        $delRes  = json_decode(curl_exec($delCh), true);
                        $delCode = curl_getinfo($delCh, CURLINFO_HTTP_CODE);
                        curl_close($delCh);

                        if ($delCode === 200) {
                            CLI::write("   ✅ Successfully unregistered device {$hostname}.", 'green');
                            $removed++;
                        } else {
                            CLI::error("   ❌ Failed to remove device {$hostname}.");
                        }
                    } else {
                        $removed++;
                    }
                }
            }
        }

        // C. Synchronize GPS coordinates in the locations table to resolve Lat/Lng N/A immediately
        CLI::write('📡 Fetching all registered locations from LibreNMS to sync GPS coordinates...', 'cyan');
        $locCh = curl_init();
        curl_setopt($locCh, CURLOPT_URL, "{$librenmsApiUrl}/resources/locations");
        curl_setopt($locCh, CURLOPT_HTTPHEADER, ["X-Auth-Token: {$librenmsApiToken}"]);
        curl_setopt($locCh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($locCh, CURLOPT_TIMEOUT, 10);
        
        $locResponse = curl_exec($locCh);
        $locCode = curl_getinfo($locCh, CURLINFO_HTTP_CODE);
        curl_close($locCh);

        $locUpdated = 0;
        if ($locCode === 200) {
            $locRes = json_decode($locResponse, true);
            if (isset($locRes['locations'])) {
                foreach ($locRes['locations'] as $loc) {
                    $locId = $loc['id'];
                    $locName = trim($loc['location']);
                    
                    if (preg_match('/^(.+?)\s*\[\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*\]$/', $locName, $matches)) {
                        $targetLat = $matches[2];
                        $targetLng = $matches[3];
                        
                        $existingLat = isset($loc['lat']) && $loc['lat'] !== '' ? trim($loc['lat']) : '';
                        $existingLng = isset($loc['lng']) && $loc['lng'] !== '' ? trim($loc['lng']) : '';
                        
                        $needsUpdate = false;
                        if ($existingLat === '' || $existingLng === '') {
                            $needsUpdate = true;
                        } else {
                            if (abs((float)$existingLat - (float)$targetLat) > 0.0001 || 
                                abs((float)$existingLng - (float)$targetLng) > 0.0001) {
                                $needsUpdate = true;
                            }
                        }
                        
                        if ($needsUpdate) {
                            CLI::write("📍 [Location Sync] Updating coordinates for '{$locName}' (ID: {$locId})", 'cyan');
                            CLI::write("   • Coordinates: [{$existingLat}, {$existingLng}] ➔ [{$targetLat}, {$targetLng}]");
                            
                            if (!$isDryRun) {
                                $locPatchCh = curl_init("{$librenmsApiUrl}/locations/{$locId}");
                                curl_setopt($locPatchCh, CURLOPT_CUSTOMREQUEST, 'PATCH');
                                curl_setopt($locPatchCh, CURLOPT_POSTFIELDS, json_encode([
                                    'lat' => $targetLat,
                                    'lng' => $targetLng
                                ]));
                                curl_setopt($locPatchCh, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($locPatchCh, CURLOPT_HTTPHEADER, [
                                    "X-Auth-Token: {$librenmsApiToken}",
                                    "Content-Type: application/json"
                                ]);
                                
                                $patchRes = json_decode(curl_exec($locPatchCh), true);
                                $patchCode = curl_getinfo($locPatchCh, CURLINFO_HTTP_CODE);
                                curl_close($locPatchCh);
                                
                                if ($patchCode === 200 || $patchCode === 201) {
                                    CLI::write("   ✅ Location coordinates successfully updated.", 'green');
                                    $locUpdated++;
                                } else {
                                    $errMsg = $patchRes['message'] ?? 'API response error';
                                    CLI::error("   ❌ Failed to update location coordinates: {$errMsg}");
                                }
                            } else {
                                $locUpdated++;
                            }
                        }
                    }
                }
            }
            CLI::write("📍 Location Coordinates Sync Completed. Updated: {$locUpdated}", 'green');
        } else {
            CLI::error("❌ Failed to query locations API (HTTP Code: {$locCode}). Cannot synchronize GPS coordinates.");
        }

        CLI::write('🎉 Synchronization Completed successfully!', 'green');
        CLI::write("📊 Summary -> Added: {$added} | Updated: {$updated} | Removed: {$removed} | Locations Synced: {$locUpdated} (Dry-Run: " . ($isDryRun ? 'Yes' : 'No') . ")", 'cyan');
    }
}

