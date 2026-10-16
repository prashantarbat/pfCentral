<?php

namespace App\Services\PfSense;

use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;
use Config\Database;
use Exception;

/**
 * Service responsible for processing incoming agent telemetry payloads.
 *
 * Each pfCentral agent phones home periodically to `POST /endpoint` with a base64-encoded
 * XML payload containing device hardware, network, system health, and tunnel telemetry.
 */
class DeviceTelemetryService
{
    protected PfInventoryModel $inventoryModel;
    protected PfStatusModel $statusModel;

    public function __construct(?PfInventoryModel $inventoryModel = null, ?PfStatusModel $statusModel = null)
    {
        $this->inventoryModel = $inventoryModel ?? new PfInventoryModel();
        $this->statusModel    = $statusModel ?? new PfStatusModel();
    }

    /**
     * Process a raw HTTP request input payload (base64-encoded XML).
     *
     * @param string $rawInput
     * @return array{statusCode: int, body: array}
     */
    public function processRawPayload(string $rawInput): array
    {
        $decodedInput = base64_decode($rawInput, true);
        if ($decodedInput === false) {
            return [
                'statusCode' => 400,
                'body'       => ['error' => 'Invalid Base64-encoded XML data.'],
            ];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($decodedInput);
        if ($xml === false) {
            return [
                'statusCode' => 400,
                'body'       => ['error' => 'Invalid XML format.'],
            ];
        }

        $deviceKey = isset($xml->device_key) ? trim((string) $xml->device_key) : null;
        $netgateId = isset($xml->netid) ? trim((string) $xml->netid) : null;
        $lanIp     = trim((string) ($xml->lan_interface_ip ?? $xml->ip ?? ''));
        $hostname  = trim((string) ($xml->hostname ?? ''));

        if (!$deviceKey) {
            return [
                'statusCode' => 400,
                'body'       => ['error' => 'No device key provided.'],
            ];
        }

        $db = Database::connect();

        // Find or rebind corresponding inventory record
        $inventoryRecord = $this->inventoryModel->where('devicekey', $deviceKey)->first();

        if (!$inventoryRecord) {
            // Check if this device already exists in inventory (e.g. after agent reinstall with newly minted key)
            $existingInv = null;

            if ($lanIp !== '') {
                // 1. Match by man_ip in inventory
                $existingInv = $this->inventoryModel->where('man_ip', $lanIp)->first();

                // 2. Match via pf_devices LAN IP -> branch name
                if (!$existingInv) {
                    $devByIp = $db->table('pf_devices')->where('lan_ip', $lanIp)->get()->getRowArray();
                    if ($devByIp && !empty($devByIp['br_name'])) {
                        $existingInv = $this->inventoryModel->where('name', trim($devByIp['br_name']))->first();
                    }
                }

                // 3. Match via pf_locations IP -> location ID
                if (!$existingInv) {
                    $locByIp = $db->table('pf_locations')->where('ip', $lanIp)->get()->getRowArray();
                    if ($locByIp) {
                        $existingInv = $this->inventoryModel->where('location', $locByIp['id'])->first();
                    }
                }
            }

            // 4. Match by netgate_id if valid
            if (!$existingInv && !empty($netgateId) && !in_array(strtolower($netgateId), ['unknown', 'none', 'n.a.', 'na'])) {
                $existingInv = $this->inventoryModel->where('netgate_id', $netgateId)->first();
            }

            if ($existingInv) {
                // Rebind the existing inventory record to the newly minted deviceKey
                $rebindData = [
                    'devicekey'  => $deviceKey,
                    'active'     => 1,
                ];
                if (!empty($netgateId)) {
                    $rebindData['netgate_id'] = $netgateId;
                }
                if (!empty($lanIp) && empty($existingInv['man_ip'])) {
                    $rebindData['man_ip'] = $lanIp;
                }
                $this->inventoryModel->update($existingInv['id'], $rebindData);
                $inventoryRecord = $this->inventoryModel->find($existingInv['id']);
            } else {
                // Truly new device: resolve name and location upfront so it is never orphaned
                $resolvedName = '';
                $resolvedLocId = null;

                if ($lanIp !== '') {
                    $devMatch = $db->table('pf_devices')->where('lan_ip', $lanIp)->get()->getRowArray();
                    if ($devMatch && !empty($devMatch['br_name'])) {
                        $resolvedName = trim($devMatch['br_name']);
                    }
                    $locMatch = $db->table('pf_locations')->where('ip', $lanIp)->get()->getRowArray();
                    if ($locMatch) {
                        $resolvedLocId = $locMatch['id'];
                        if (empty($resolvedName) && !empty($locMatch['name'])) {
                            $resolvedName = trim($locMatch['name']);
                        }
                    }
                }

                if (!empty($resolvedName) && empty($resolvedLocId)) {
                    $locByName = $db->table('pf_locations')->where('name', $resolvedName)->get()->getRowArray();
                    if ($locByName) {
                        $resolvedLocId = $locByName['id'];
                    }
                }

                $newInventoryData = [
                    'name'       => $resolvedName ?: '',
                    'location'   => $resolvedLocId ?: null,
                    'man_ip'     => $lanIp ?: null,
                    'devicekey'  => $deviceKey,
                    'netgate_id' => $netgateId,
                    'active'     => 1,
                ];
                $newInventoryId  = $this->inventoryModel->insert($newInventoryData, true);
                $inventoryRecord = $this->inventoryModel->find($newInventoryId);
            }
        }

        // Self-heal: Ensure active = 1 and resolve missing name, location, or man_ip
        $invUpdates = [];
        if ((int)($inventoryRecord['active'] ?? 0) === 0) {
            $invUpdates['active'] = 1;
        }

        $currentName = trim((string)($inventoryRecord['name'] ?? ''));
        $currentLoc  = trim((string)($inventoryRecord['location'] ?? ''));
        $currentIp   = trim((string)($inventoryRecord['man_ip'] ?? ''));

        // Ensure man_ip uses the authoritative LAN IP from pf_devices if available, rejecting MPLS transport IPs
        $matchedDev = $db->table('pf_devices')->where('TRIM(br_name)', $inventoryRecord['name'])->get()->getRowArray();
        if ($matchedDev && !empty($matchedDev['lan_ip']) && filter_var(trim($matchedDev['lan_ip']), FILTER_VALIDATE_IP)) {
            $authLanIp = trim($matchedDev['lan_ip']);
            if (empty($currentIp) || $currentIp === trim($matchedDev['mpls_ip'] ?? '') || !filter_var($currentIp, FILTER_VALIDATE_IP)) {
                $invUpdates['man_ip'] = $authLanIp;
            }
        } elseif ($lanIp !== '' && empty($currentIp)) {
            $invUpdates['man_ip'] = $lanIp;
        }

        if (empty($currentName) && $lanIp !== '') {
            $matchedDev = $db->table('pf_devices')->where('lan_ip', $lanIp)->get()->getRowArray();
            if ($matchedDev && !empty($matchedDev['br_name'])) {
                $currentName = trim($matchedDev['br_name']);
                $invUpdates['name'] = $currentName;
            } else {
                $matchedLoc = $db->table('pf_locations')->where('ip', $lanIp)->get()->getRowArray();
                if ($matchedLoc && !empty($matchedLoc['name'])) {
                    $currentName = trim($matchedLoc['name']);
                    $invUpdates['name'] = $currentName;
                }
            }
        }

        if (empty($currentLoc)) {
            if (!empty($currentName)) {
                $matchedLoc = $db->table('pf_locations')->where('name', $currentName)->get()->getRowArray();
                if ($matchedLoc) {
                    $invUpdates['location'] = $matchedLoc['id'];
                }
            }
            if (!isset($invUpdates['location']) && $lanIp !== '') {
                $matchedLoc = $db->table('pf_locations')->where('ip', $lanIp)->get()->getRowArray();
                if ($matchedLoc) {
                    $invUpdates['location'] = $matchedLoc['id'];
                }
            }
        }

        if (!empty($invUpdates)) {
            $this->inventoryModel->update($inventoryRecord['id'], $invUpdates);
            $inventoryRecord = array_merge($inventoryRecord, $invUpdates);
        }

        $pfRecord = $this->statusModel->where('pf_id', $inventoryRecord['id'])->first();

        $statusDev = $db->table('pf_devices')->where('TRIM(br_name)', $inventoryRecord['name'])->get()->getRowArray();
        $authLanIp = ($statusDev && !empty($statusDev['lan_ip']) && filter_var(trim($statusDev['lan_ip']), FILTER_VALIDATE_IP)) ? trim($statusDev['lan_ip']) : null;
        $reportedIp = (string) ($xml->lan_interface_ip ?? $xml->ip ?? null);
        $finalStatusIp = $authLanIp ?: $reportedIp;
        if ($authLanIp && ($reportedIp === trim($statusDev['mpls_ip'] ?? '') || !filter_var($reportedIp, FILTER_VALIDATE_IP))) {
            $finalStatusIp = $authLanIp;
        }

        $data = [
            'pf_id'            => $inventoryRecord['id'],
            'ip'               => $finalStatusIp,
            'port'             => (string) ($xml->webgui_port ?? null),
            'darkstat_port'    => (string) ($xml->darkstat_port ?? null),
            'ssh_port'         => (string) ($xml->ssh_port ?? null),
            'lightsquid_port'  => (string) ($xml->lightsquid_port ?? null),
            'ntop_port'        => (string) ($xml->ntop_port ?? null),
            'bandwidthd'       => (string) ($xml->bandwidthd ?? null),
            'restapi'          => (string) ($xml->restapi ?? null),
            'serial'           => (string) ($xml->serial ?? null),
            'wan_ip'           => (string) ($xml->wan_ip ?? null),
            'status'           => (string) ($xml->status ?? null),
            'os_name'          => (string) ($xml->os_name ?? null),
            'os_version'       => (string) ($xml->os_version ?? null),
            'hostname'         => (string) ($xml->hostname ?? null),
            'build_identifier' => (string) ($xml->build_identifier ?? null),
            'uptime_duration'  => (string) ($xml->uptime_duration ?? null),
            'users'            => (string) ($xml->users ?? null),
            'load_averages'    => (string) ($xml->load_averages ?? null),
            'ping_latency'     => (string) ($xml->ping_latency ?? null),
            'cpu_usage'        => (string) ($xml->cpu_usage ?? null),
            'total_memory'     => (string) ($xml->total_memory ?? null),
            'available_memory' => (string) ($xml->available_memory ?? null),
            'disk_usage'       => (string) ($xml->disk_usage ?? null),
            'active_tunnel'    => (string) ($xml->active_tunnel ?? null),
        ];

        // Header-pack verification from the agent (agent.sh checkBannerStatus):
        // 1 = flashing banner present in head.inc, 0 = banner missing.
        // Guarded: only write if column exists and element is populated.
        if (Database::connect()->fieldExists('banner', 'pf_status')
            && isset($xml->banner_status) && (string) $xml->banner_status !== '') {
            $data['banner'] = ((string) $xml->banner_status === '1') ? 1 : 0;
        }

        try {
            if ($pfRecord) {
                $this->statusModel->where('pf_id', $inventoryRecord['id'])->set($data)->update();
                return [
                    'statusCode' => 200,
                    'body'       => ['message' => 'Updated successfully.'],
                ];
            }

            $this->statusModel->insert($data);
            return [
                'statusCode' => 201,
                'body'       => ['message' => 'Inserted successfully.'],
            ];
        } catch (Exception $e) {
            return [
                'statusCode' => 500,
                'body'       => ['error' => 'Database operation failed.', 'details' => $e->getMessage()],
            ];
        }
    }
}
