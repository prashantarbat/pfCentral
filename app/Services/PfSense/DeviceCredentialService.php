<?php

namespace App\Services\PfSense;

use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;
use App\Models\DeviceAgentModel;

/**
 * Service responsible for device identity verification, key rotation, and pfCentral header derivation.
 */
class DeviceCredentialService
{
    protected PfInventoryModel $inventoryModel;
    protected PfStatusModel $statusModel;
    protected DeviceAgentModel $deviceAgentModel;

    public function __construct(
        ?PfInventoryModel $inventoryModel = null,
        ?PfStatusModel $statusModel = null,
        ?DeviceAgentModel $deviceAgentModel = null
    ) {
        $this->inventoryModel   = $inventoryModel ?? new PfInventoryModel();
        $this->statusModel      = $statusModel ?? new PfStatusModel();
        $this->deviceAgentModel = $deviceAgentModel ?? new DeviceAgentModel();
    }

    /**
     * Resolves the identity for the pfCentral header pack based on DEVICEKEY, IP address, or hostname.
     *
     * @param string|null $deviceKey
     * @param string|null $requestIp
     * @param string|null $requestHostname
     * @return array{statusCode: int, body: array}
     */
    public function getHeaderIdentity(?string $deviceKey, ?string $requestIp = null, ?string $requestHostname = null, ?string $requestBranchCode = null): array
    {
        $deviceKey = trim((string) $deviceKey);
        $requestBranchCode = trim((string) $requestBranchCode);
        $inventoryRecord = null;

        if ($deviceKey !== '') {
            $inventoryRecord = $this->inventoryModel->where('devicekey', $deviceKey)->first();
        }

        // Fallback 1: Match by IP address (man_ip or status IP) if devicekey not found
        if (!$inventoryRecord && !empty($requestIp) && filter_var($requestIp, FILTER_VALIDATE_IP)) {
            $inventoryRecord = $this->inventoryModel->where('man_ip', $requestIp)->first();
            if (!$inventoryRecord) {
                $statusRec = $this->statusModel->where('ip', $requestIp)->first();
                if ($statusRec) {
                    $inventoryRecord = $this->inventoryModel->find($statusRec['pf_id']);
                }
            }
        }

        // Fallback 2: Match by explicit Branch Code (e.g. 1021 or 21)
        if (!$inventoryRecord && !empty($requestBranchCode)) {
            $digits = preg_replace('/\D/', '', $requestBranchCode);
            if ($digits !== '') {
                $inventoryRecord = $this->inventoryModel->like('name', $digits, 'after')->first();
                if (!$inventoryRecord && strlen($digits) <= 3) {
                    $expanded = ($digits === '99') ? '9999' : sprintf('1%03d', (int) $digits);
                    $inventoryRecord = $this->inventoryModel->like('name', $expanded, 'after')->first();
                }
                if (!$inventoryRecord) {
                    $inventoryRecord = $this->inventoryModel->like('name', $digits)->first();
                }
            }
        }

        // Fallback 3: Match by Hostname or branch code in Hostname (e.g. AU21WsABM13803P -> 1021, AU30PfBr1030 -> 1030)
        if (!$inventoryRecord && !empty($requestHostname)) {
            $cleanHost = trim($requestHostname);
            $inventoryRecord = $this->inventoryModel->where('name', $cleanHost)->first();
            
            if (!$inventoryRecord) {
                // If hostname has bank prefix AU followed by 2-3 digits e.g. AU21... -> 1021, AU99... -> 9999
                if (preg_match('/^AU0?(\d{2,3})/i', $cleanHost, $aum)) {
                    $expanded = ($aum[1] === '99') ? '9999' : sprintf('1%03d', (int) $aum[1]);
                    $inventoryRecord = $this->inventoryModel->like('name', $expanded, 'after')->first();
                }
            }

            if (!$inventoryRecord) {
                if (preg_match('/(\d{4})/', $cleanHost, $m)) {
                    $code = $m[1];
                    $inventoryRecord = $this->inventoryModel->like('name', $code, 'after')->first();
                    if (!$inventoryRecord) {
                        $inventoryRecord = $this->inventoryModel->like('name', $code)->first();
                    }
                }
            }
        }

        // Fallback 4: If not yet in pf_inventory, check pf_devices or pf_locations
        if (!$inventoryRecord) {
            $db = \Config\Database::connect();
            $matchedDev = null;
            $matchedLoc = null;

            if (!empty($requestBranchCode)) {
                $digits = preg_replace('/\D/', '', $requestBranchCode);
                if ($digits !== '') {
                    $matchedDev = $db->table('pf_devices')->like('br_name', $digits, 'after')->get()->getRowArray();
                    if (!$matchedDev) {
                        $matchedLoc = $db->table('pf_locations')->like('name', $digits, 'after')->get()->getRowArray();
                    }
                }
            }
            if (!$matchedDev && !empty($requestHostname)) {
                $cleanHost = trim($requestHostname);
                $matchedDev = $db->table('pf_devices')->where('hostname', $cleanHost)->get()->getRowArray();
                if (!$matchedDev && preg_match('/(\d{4})/', $cleanHost, $m)) {
                    $matchedDev = $db->table('pf_devices')->like('br_name', $m[1], 'after')->get()->getRowArray();
                }
            }
            if (!$matchedDev && !empty($requestIp) && filter_var($requestIp, FILTER_VALIDATE_IP)) {
                $matchedDev = $db->table('pf_devices')->where('lan_ip', $requestIp)->get()->getRowArray();
                if (!$matchedDev) {
                    $matchedLoc = $db->table('pf_locations')->where('ip', $requestIp)->get()->getRowArray();
                }
            }

            if ($matchedDev || $matchedLoc) {
                $resolvedName = trim((string) ($matchedDev['br_name'] ?? ($matchedLoc['name'] ?? '')));
                $resolvedIp = trim((string) ($matchedDev['lan_ip'] ?? ($matchedLoc['ip'] ?? ($requestIp ?? ''))));
                $resolvedLocId = null;
                if ($matchedLoc) {
                    $resolvedLocId = $matchedLoc['id'];
                } elseif ($resolvedName !== '') {
                    $loc = $db->table('pf_locations')->where('TRIM(name)', $resolvedName)->get()->getRowArray();
                    if ($loc) {
                        $resolvedLocId = $loc['id'];
                    }
                }

                $newInvData = [
                    'name'       => $resolvedName,
                    'location'   => $resolvedLocId ?: null,
                    'man_ip'     => filter_var($resolvedIp, FILTER_VALIDATE_IP) ? $resolvedIp : null,
                    'devicekey'  => ($deviceKey !== '' && strlen($deviceKey) === 64 && ctype_xdigit($deviceKey)) ? $deviceKey : null,
                    'active'     => 1,
                ];
                $newId = $this->inventoryModel->insert($newInvData, true);
                if ($newId) {
                    $inventoryRecord = $this->inventoryModel->find($newId);
                    $this->statusModel->insert([
                        'pf_id'    => $newId,
                        'ip'       => filter_var($resolvedIp, FILTER_VALIDATE_IP) ? $resolvedIp : ($requestIp ?: ''),
                        'hostname' => $matchedDev['hostname'] ?? ($requestHostname ?: ''),
                        'status'   => 'offline',
                    ]);
                }
            }
        }

        if (!$inventoryRecord) {
            return [
                'statusCode' => 404,
                'body'       => ['error' => 'Unknown device or key.'],
            ];
        }

        // Auto-bind deviceKey to inventory record if device is presenting a valid new key
        // and no conflicting device uses that key
        if ($deviceKey !== '' && strlen($deviceKey) === 64 && ctype_xdigit($deviceKey)) {
            if (($inventoryRecord['devicekey'] ?? '') !== $deviceKey) {
                $clash = $this->inventoryModel->where('devicekey', $deviceKey)
                                              ->where('id !=', $inventoryRecord['id'])
                                              ->first();
                if (!$clash) {
                    $this->inventoryModel->update($inventoryRecord['id'], ['devicekey' => $deviceKey]);
                    $inventoryRecord['devicekey'] = $deviceKey;
                }
            }
        }

        $statusRecord = $this->statusModel->where('pf_id', $inventoryRecord['id'])->first();

        $name = trim((string) ($inventoryRecord['name'] ?? ''));

        // Helper to check for a valid IPv4 address
        $isValidIp = static function ($val): bool {
            return !empty($val) && (bool) filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        };

        $statusIp = trim((string) ($statusRecord['ip'] ?? ''));
        $manIp    = trim((string) ($inventoryRecord['man_ip'] ?? ''));
        $reqIp    = trim((string) ($requestIp ?? ''));

        $db = \Config\Database::connect();
        $devRow = null;
        if ($name !== '') {
            $devRow = $db->table('pf_devices')->where('TRIM(br_name)', $name)->get()->getRowArray();
            if (!$devRow && preg_match('/^(\d{4})/', $name, $bm)) {
                $devRow = $db->table('pf_devices')->like('br_name', $bm[1], 'after')->get()->getRowArray();
            }
        }
        if (!$devRow && !empty($requestBranchCode)) {
            $digits = preg_replace('/\D/', '', $requestBranchCode);
            if ($digits !== '') {
                $devRow = $db->table('pf_devices')->like('br_name', $digits, 'after')->get()->getRowArray();
            }
        }
        if (!$devRow && !empty($requestHostname)) {
            $devRow = $db->table('pf_devices')->where('hostname', trim($requestHostname))->get()->getRowArray();
        }

        $mplsIp = trim((string) ($devRow['mpls_ip'] ?? ''));

        // PRIORITY 1: Authoritative LAN IP from pf_devices table in DB
        $ip = '';
        if ($devRow && $isValidIp(trim($devRow['lan_ip'] ?? ''))) {
            $ip = trim($devRow['lan_ip']);
        }

        // PRIORITY 2: pf_locations IP in DB
        if ($ip === '' && $name !== '') {
            $locRow = $db->table('pf_locations')->where('TRIM(name)', $name)->get()->getRowArray();
            if ($locRow && $isValidIp(trim($locRow['ip'] ?? ''))) {
                $ip = trim($locRow['ip']);
            }
        }

        // PRIORITY 3: Inventory Management IP (if valid and not MPLS transport IP)
        if ($ip === '' && $isValidIp($manIp) && ($mplsIp === '' || $manIp !== $mplsIp)) {
            $ip = $manIp;
        }

        // PRIORITY 4: Status IP (if valid and not MPLS transport IP)
        if ($ip === '' && $isValidIp($statusIp) && ($mplsIp === '' || $statusIp !== $mplsIp)) {
            $ip = $statusIp;
        }

        // PRIORITY 5: Request IP reported by caller (if valid and not MPLS transport IP)
        if ($ip === '' && $isValidIp($reqIp) && ($mplsIp === '' || $reqIp !== $mplsIp)) {
            $ip = $reqIp;
        }

        // Self-heal DB records: ensure pf_status.ip and pf_inventory.man_ip store the true LAN IP,
        // correcting any MPLS link IP (e.g. 192.168.x.x) or invalid string that was previously stored.
        if ($ip !== '') {
            if ($statusRecord && !empty($statusRecord['id']) && (string)($statusRecord['ip'] ?? '') !== $ip) {
                $this->statusModel->update($statusRecord['id'], ['ip' => $ip]);
            }
            if (!empty($inventoryRecord['id']) && (string)($inventoryRecord['man_ip'] ?? '') !== $ip) {
                $this->inventoryModel->update($inventoryRecord['id'], ['man_ip' => $ip]);
            }
        }

        // Branch code: numeric part of inventory name reversed (e.g. "1030-BRAHMAN SABHA" -> "0301")
        $branchcode = '';
        if (preg_match('/(\d+)/', $name, $m)) {
            $branchcode = strrev($m[1]);
        }

        return [
            'statusCode' => 200,
            'body'       => [
                'name'       => $name,
                'ip'         => $ip,
                'branchcode' => $branchcode,
                'unknown'    => ($name === '' || strcasecmp($name, 'UNKNOWN') === 0),
            ],
        ];
    }

    /**
     * Rebinds an existing inventory record to a newly minted random device key.
     *
     * @param string|null $oldKey
     * @param string|null $newKey
     * @return array{statusCode: int, body: array}
     */
    public function rebindDeviceKey(?string $oldKey, ?string $newKey): array
    {
        $oldKey = trim((string) $oldKey);
        $newKey = strtolower(trim((string) $newKey));

        if ($oldKey === '') {
            return [
                'statusCode' => 400,
                'body'       => ['error' => 'No DEVICEKEY provided.'],
            ];
        }

        if (strlen($newKey) !== 64 || !ctype_xdigit($newKey)) {
            return [
                'statusCode' => 400,
                'body'       => ['error' => 'NEWDEVICEKEY must be 64 hex characters.'],
            ];
        }

        $inventoryRecord = $this->inventoryModel->where('devicekey', $oldKey)->first();
        if (!$inventoryRecord) {
            return [
                'statusCode' => 404,
                'body'       => ['error' => 'Unknown device key.'],
            ];
        }

        if (!hash_equals((string) $inventoryRecord['devicekey'], $newKey)) {
            $clash = $this->inventoryModel->where('devicekey', $newKey)
                                          ->where('id !=', $inventoryRecord['id'])
                                          ->first();
            if ($clash) {
                return [
                    'statusCode' => 409,
                    'body'       => ['error' => 'That key is already in use by device #' . $clash['id'] . '.'],
                ];
            }

            $this->inventoryModel->update($inventoryRecord['id'], ['devicekey' => $newKey]);

            // Sync phase-2 job agent if enrolled with the same credential
            $agentRow = $this->deviceAgentModel
                ->where('pf_id', $inventoryRecord['id'])
                ->where('device_key', $oldKey)
                ->first();

            if ($agentRow && !$this->deviceAgentModel->where('device_key', $newKey)->first()) {
                $this->deviceAgentModel->update($agentRow['pf_id'], ['device_key' => $newKey]);
            }

            log_message('info', sprintf(
                'Device key rotated for #%d (%s); the previous key is no longer accepted.',
                $inventoryRecord['id'],
                (string) ($inventoryRecord['name'] ?? 'UNKNOWN')
            ));
        }

        return [
            'statusCode' => 200,
            'body'       => [
                'ok'    => true,
                'pf_id' => (int) $inventoryRecord['id'],
                'name'  => (string) ($inventoryRecord['name'] ?? ''),
            ],
        ];
    }
}
