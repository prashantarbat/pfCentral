<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;
use App\Models\PfBakupModel;
use App\Models\BranchAvailabilityStateModel;

use App\Support\CliOptions;

/**
 * Mock pfSense Device Simulator for Development, CI Testing, and Demos.
 *
 * Allows developers and system administrators to test telemetry ingestion,
 * backup pipelines, availability monitoring, and config job execution without
 * needing physical Netgate / pfSense hardware.
 *
 * Usage:
 *   php spark pfcentral:mock-device
 *   php spark pfcentral:mock-device --device=1 --mode=telemetry
 *   php spark pfcentral:mock-device --device=2 --mode=backup
 *   php spark pfcentral:mock-device --device=1 --mode=all
 */
class MockDevice extends BaseCommand
{
    protected $group       = 'pfCentral';
    protected $name        = 'pfcentral:mock-device';
    protected $description = 'Simulates a mock pfSense device reporting telemetry, backups, and health metrics.';
    protected $usage       = 'pfcentral:mock-device [--device=<id>] [--mode=<telemetry|backup|all>] [--latency=<ms>]';
    protected $options     = [
        '--device'  => 'Device ID in pf_inventory (default: 1)',
        '--mode'    => 'Simulation mode: telemetry, backup, or all (default: all)',
        '--latency' => 'Simulated latency in ms (default: 14.2ms)',
        '--status'  => 'Simulated device link state: up or down (default: up)',
    ];

    public function run(array $params)
    {
        $deviceId = CliOptions::int($params, 'device', 1);
        $mode     = CliOptions::value($params, 'mode', 'all');
        $status   = CliOptions::value($params, 'status', 'up');
        $latency  = CliOptions::value($params, 'latency', '14.2ms');

        $inventory = new PfInventoryModel();
        $device    = $inventory->find((int) $deviceId);

        if (!$device) {
            CLI::error("Device ID {$deviceId} not found in pf_inventory. Run 'php spark db:seed InitialSeeder' first.");
            return EXIT_ERROR;
        }

        CLI::write("====================================================", 'cyan');
        CLI::write("pfCentral Mock pfSense Simulator", 'green');
        CLI::write("Target Device: {$device['name']} (ID: {$device['id']}, IP: {$device['man_ip']})", 'yellow');
        CLI::write("Mode: {$mode} | Status: {$status} | Latency: {$latency}", 'white');
        CLI::write("====================================================", 'cyan');

        if ($mode === 'telemetry' || $mode === 'all') {
            $this->simulateTelemetry($device, $status, $latency);
        }

        if ($mode === 'backup' || $mode === 'all') {
            $this->simulateBackup($device);
        }

        CLI::write("Mock simulation finished successfully!", 'green');
        return EXIT_SUCCESS;
    }

    private function simulateTelemetry(array $device, string $statusState, string $latency): void
    {
        CLI::write("[1/2] Ingesting simulated pfSense telemetry...", 'yellow');
        
        $statusModel = new PfStatusModel();
        $existing = $statusModel->where('pf_id', $device['id'])->first();

        $cpuUsage = rand(8, 42) . '%';
        $memUsage = '4096MB';
        $availMem = '2890MB';
        $uptime   = '38 days, 14 hours, 22 mins';

        $statusPayload = [
            'pf_id'             => $device['id'],
            'ip'                => $device['man_ip'],
            'port'              => $device['man_port'] ?? '443',
            'ssh_port'          => '22',
            'status'            => ($statusState === 'up') ? '1' : '0',
            'wan_ip'            => '198.51.100.12',
            'mpls_ip'           => '10.10.10.2',
            'ill_ip'            => '192.168.100.2',
            'bandwidthd'        => 1,
            'restapi'           => 1,
            'os_name'           => 'pfSense Plus',
            'os_version'        => '23.09.1-RELEASE',
            'hostname'          => strtolower($device['name']) . '.pfcentral.local',
            'build_identifier'  => 'pfSense-plus-23.09.1-amd64',
            'uptime_duration'   => $uptime,
            'users'             => 'admin (pts/0)',
            'load_averages'     => '0.12, 0.18, 0.14',
            'ping_latency'      => $latency,
            'cpu_usage'         => $cpuUsage,
            'total_memory'      => $memUsage,
            'available_memory'  => $availMem,
            'disk_usage'        => '18% (/), 4% (/tmp), 12% (/var)',
            'active_tunnel'     => 'WireGuard wg0 (Active: 3 peers, 0 packet loss)',
            'banner'            => 1,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $statusModel->update($existing['id'], $statusPayload);
            CLI::write("  Updated pf_status for device {$device['name']} (CPU: {$cpuUsage}, Memory: {$availMem}/{$memUsage}).", 'green');
        } else {
            $statusPayload['created_at'] = date('Y-m-d H:i:s');
            $statusModel->insert($statusPayload);
            CLI::write("  Created pf_status row for device {$device['name']}.", 'green');
        }

        // Update branch availability state
        try {
            $availStateModel = new BranchAvailabilityStateModel();
            $availRow = $availStateModel->where('pf_id', $device['id'])->first();
            $availData = [
                'pf_id'           => $device['id'],
                'current_state'   => ($statusState === 'up') ? 'up' : 'down',
                'active_tunnel'   => 'wireguard',
                'latency_ms'      => (float) str_replace(['ms', ' '], '', $latency),
                'last_checked_at' => date('Y-m-d H:i:s'),
                'updated_at'      => date('Y-m-d H:i:s'),
            ];
            if ($availRow) {
                $availStateModel->update($availRow['id'], $availData);
            } else {
                $availData['created_at'] = date('Y-m-d H:i:s');
                $availStateModel->insert($availData);
            }
        } catch (\Throwable $e) {
            // ignore if table not migrated yet
        }
    }

    private function simulateBackup(array $device): void
    {
        CLI::write("[2/2] Generating and saving mock pfSense config.xml snapshot...", 'yellow');

        $backupDir = WRITEPATH . 'pfcentral_backup/' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $device['name']);
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $filename  = "config_{$timestamp}.xml";
        $filepath  = $backupDir . '/' . $filename;

        $xml = <<<XML
<?xml version="1.0"?>
<pfsense>
    <version>23.9</version>
    <lastchange>
        <time>{$timestamp}</time>
        <description>Mock backup generated via pfCentral Mock Simulator</description>
        <username>admin</username>
    </lastchange>
    <system>
        <optimization>normal</optimization>
        <hostname>{$device['name']}</hostname>
        <domain>pfcentral.local</domain>
        <dnsserver>1.1.1.1</dnsserver>
        <dnsserver>8.8.8.8</dnsserver>
        <timezone>UTC</timezone>
        <time-update-interval>300</time-update-interval>
        <webgui>
            <protocol>https</protocol>
            <port>443</port>
            <max_procs>4</max_procs>
        </webgui>
    </system>
    <interfaces>
        <wan>
            <enable/>
            <if>igb0</if>
            <ipaddr>dhcp</ipaddr>
            <descr>WAN Interface</descr>
        </wan>
        <lan>
            <enable/>
            <if>igb1</if>
            <ipaddr>192.168.1.1</ipaddr>
            <subnet>24</subnet>
            <descr>LAN Interface</descr>
        </lan>
    </interfaces>
    <filter>
        <rule>
            <id></id>
            <tracker>1000000101</tracker>
            <type>pass</type>
            <interface>lan</interface>
            <ipprotocol>inet</ipprotocol>
            <statetype>keep state</statetype>
            <descr>Default allow LAN to any rule</descr>
            <source><network>lan</network></source>
            <destination><any/></destination>
        </rule>
    </filter>
</pfsense>
XML;

        file_put_contents($filepath, $xml);

        $backupModel = new PfBakupModel();
        $backupModel->insert([
            'pf_id'      => $device['id'],
            'file_name'  => $filename,
            'file_path'  => $filepath,
            'quantity'   => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        CLI::write("  Saved configuration XML: {$filepath}", 'green');
    }
}
