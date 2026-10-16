<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Support\CliOptions;
use App\Models\DeviceAgentModel;
use App\Models\PfInventoryModel;

/**
 * Enrols a device for agent-delivered config jobs (Phase 2).
 *
 * The secret is shown when it is created and never again — it is stored on pfCentral and on the
 * device, and there is no way to read it back out of a running system without re-issuing it.
 *
 *   php spark agent:enroll --device=<id|name>
 *   php spark agent:enroll --device=12 --rotate
 *   php spark agent:enroll --list
 */
class EnrollAgent extends BaseCommand
{
    protected $group       = 'Config Push';
    protected $name        = 'agent:enroll';
    protected $description = 'Enrols a device for agent-delivered config jobs, or lists enrolled devices.';
    protected $usage       = 'agent:enroll --device=<id|name> [--key=<device_key>] [--rotate] | --list';
    protected $options     = [
        '--device' => 'Device id or exact name from pf_inventory.',
        '--key'    => 'Override the device key (defaults to pf_inventory.devicekey, else a new one).',
        '--rotate' => 'Issue a new secret for an already-enrolled device.',
        '--list'   => 'List enrolled devices and when they were last seen.',
    ];

    public function run(array $params)
    {
        if (CliOptions::flag($params, 'list')) {
            return $this->listAgents();
        }

        $needle = CliOptions::value($params, 'device');
        if ($needle === null || trim($needle) === '') {
            CLI::error('Specify --device=<id|name>, or --list to see what is already enrolled.');

            return EXIT_ERROR;
        }

        $inventory = new PfInventoryModel();
        $device    = ctype_digit((string) $needle)
            ? $inventory->find((int) $needle)
            : $inventory->where('name', (string) $needle)->first();

        if (!$device) {
            CLI::error("No device matches '{$needle}'.");

            return EXIT_ERROR;
        }

        $agents = new DeviceAgentModel();
        $pfId   = (int) $device['id'];
        $agent  = $agents->find($pfId);

        $rotate = CliOptions::flag($params, 'rotate');

        if ($agent && !$rotate) {
            CLI::write("Device #{$pfId} ({$device['name']}) is already enrolled.", 'yellow');
            CLI::write('Use --rotate to issue a new secret if the current one is lost.', 'yellow');

            return EXIT_SUCCESS;
        }

        $deviceKey = trim((string) CliOptions::value($params, 'key', ''));
        if ($deviceKey === '' || $deviceKey === true) {
            $deviceKey = trim((string) ($device['devicekey'] ?? ''));
        }
        if ($deviceKey === '') {
            // A device that has never checked in has no key yet; issue one it can be told.
            $deviceKey = bin2hex(random_bytes(16));
        }

        $secret = bin2hex(random_bytes(32));

        $conflict = $agents->where('device_key', $deviceKey)->where('pf_id !=', $pfId)->first();
        if ($conflict) {
            CLI::error("Device key '{$deviceKey}' is already enrolled to device #{$conflict['pf_id']}. Use --key to set a different one.");

            return EXIT_ERROR;
        }

        try {
            if ($agent) {
                $agents->update($pfId, ['device_key' => $deviceKey, 'auth_token' => $secret]);
            } else {
                $agents->insert([
                    'pf_id'      => $pfId,
                    'device_key' => $deviceKey,
                    'auth_token' => $secret,
                ]);
            }
        } catch (\Throwable $e) {
            CLI::error('Could not save the enrolment: ' . $e->getMessage());

            return EXIT_ERROR;
        }

        // Keep inventory's key in step when it had none, so /endpoint and pickup agree on identity.
        if (empty($device['devicekey'])) {
            $inventory->update($pfId, ['devicekey' => $deviceKey]);
            CLI::write('Wrote the new key to pf_inventory.devicekey as well.', 'yellow');
        }

        CLI::newLine();
        CLI::write('Device enrolled: #' . $pfId . ' ' . ($device['name'] ?? ''), 'green');
        CLI::newLine();
        CLI::write('  device key   : ' . $deviceKey, 'white');
        CLI::write('  agent secret : ' . $secret, 'white');
        CLI::newLine();
        CLI::write('This secret is shown once. Install it on the device as root:', 'yellow');
        CLI::write('  printf %s ' . escapeshellarg($secret) . ' > /var/pfcentral/agent_secret', 'white');
        CLI::write('  chmod 600 /var/pfcentral/agent_secret', 'white');
        CLI::newLine();
        CLI::write('Then install the job agent and run it every minute:', 'yellow');
        CLI::write('  /usr/local/bin/pfcentral-jobs.php', 'white');
        CLI::write('  # cron: * * * * * /usr/local/bin/php /usr/local/bin/pfcentral-jobs.php >> /var/log/pfcentral_jobs.log 2>&1', 'white');
        CLI::newLine();

        return EXIT_SUCCESS;
    }

    private function listAgents(): int
    {
        $rows = db_connect()
            ->table('device_agents')
            ->select('device_agents.pf_id, device_agents.device_key, device_agents.last_seen_at, device_agents.agent_version, pf_inventory.name')
            ->join('pf_inventory', 'pf_inventory.id = device_agents.pf_id', 'left')
            ->orderBy('device_agents.pf_id', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            CLI::write('No devices are enrolled for agent-delivered jobs yet.', 'yellow');

            return EXIT_SUCCESS;
        }

        CLI::newLine();
        CLI::write(sprintf('%-6s %-26s %-20s %-20s %s', 'pf_id', 'name', 'device_key', 'last_seen', 'status'), 'white');

        foreach ($rows as $row) {
            $seen   = $row['last_seen_at'] ? strtotime((string) $row['last_seen_at']) : null;
            $online = $seen !== null && (time() - $seen) <= 300;

            CLI::write(sprintf(
                '%-6s %-26s %-20s %-20s %s',
                $row['pf_id'],
                mb_substr((string) ($row['name'] ?? '—'), 0, 26),
                mb_substr((string) ($row['device_key'] ?? '—'), 0, 20),
                (string) ($row['last_seen_at'] ?? 'never'),
                $online ? 'online' : 'silent'
            ), $online ? 'green' : 'yellow');
        }

        CLI::newLine();

        return EXIT_SUCCESS;
    }
}
