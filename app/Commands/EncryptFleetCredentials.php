<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\PfInventoryModel;
use App\Services\Security\CredentialEncryptionService;

class EncryptFleetCredentials extends BaseCommand
{
    protected $group       = 'Fleet';
    protected $name        = 'fleet:encrypt-credentials';
    protected $description = 'Encrypts unencrypted fleet credentials (passwords, API tokens) in pf_inventory at rest.';

    protected $options = [
        '--dry-run' => 'Simulate the encryption process without saving changes to the database.',
        '--status'  => 'Display the current encryption status of all fleet inventory records.',
    ];

    public function run(array $params)
    {
        $dryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');
        $statusOnly = array_key_exists('status', $params) || CLI::getOption('status');

        $encryptionService = new CredentialEncryptionService();
        $db = \Config\Database::connect();
        
        // Fetch raw database rows (without model afterFind decryption)
        $devices = $db->table('pf_inventory')->select('id, name, man_ip, password, api')->get()->getResultArray();

        if (empty($devices)) {
            CLI::write('No devices found in pf_inventory.', 'yellow');
            return;
        }

        $total = count($devices);
        $alreadyEncrypted = 0;
        $needsEncryption = 0;
        $blank = 0;

        $table = [
            ['ID', 'Device Name', 'IP', 'Password Status', 'API Token Status']
        ];

        foreach ($devices as $d) {
            $hasPwd = !empty($d['password']);
            $hasApi = !empty($d['api']);

            $pwdEnc = $hasPwd ? $encryptionService->isEncrypted($d['password']) : null;
            $apiEnc = $hasApi ? $encryptionService->isEncrypted($d['api']) : null;

            if (!$hasPwd && !$hasApi) {
                $blank++;
                $table[] = [$d['id'], $d['name'] ?: 'UNKNOWN', $d['man_ip'] ?: '-', 'None', 'None'];
                continue;
            }

            $needsWork = ($hasPwd && !$pwdEnc) || ($hasApi && !$apiEnc);
            if ($needsWork) {
                $needsEncryption++;
            } else {
                $alreadyEncrypted++;
            }

            $table[] = [
                $d['id'],
                $d['name'] ?: 'UNKNOWN',
                $d['man_ip'] ?: '-',
                !$hasPwd ? 'None' : ($pwdEnc ? CLI::color('Encrypted', 'green') : CLI::color('Plaintext', 'red')),
                !$hasApi ? 'None' : ($apiEnc ? CLI::color('Encrypted', 'green') : CLI::color('Plaintext', 'red')),
            ];
        }

        CLI::table($table, ['ID', 'Device Name', 'IP', 'Password Status', 'API Token Status']);
        CLI::newLine();
        CLI::write("Total Devices: $total | Already Encrypted: $alreadyEncrypted | Needs Encryption: $needsEncryption | No Creds: $blank", 'cyan');

        if ($statusOnly) {
            return;
        }

        if ($needsEncryption === 0) {
            CLI::write('All fleet credentials are already encrypted at rest. No action needed.', 'green');
            return;
        }

        if ($dryRun) {
            CLI::write("[DRY RUN] Would encrypt credentials for $needsEncryption device(s). No changes written.", 'yellow');
            return;
        }

        $updatedCount = 0;
        foreach ($devices as $d) {
            $update = [];
            if (!empty($d['password']) && !$encryptionService->isEncrypted($d['password'])) {
                $update['password'] = $encryptionService->encrypt($d['password']);
            }
            if (!empty($d['api']) && !$encryptionService->isEncrypted($d['api'])) {
                $update['api'] = $encryptionService->encrypt($d['api']);
            }

            if (!empty($update)) {
                $db->table('pf_inventory')->where('id', $d['id'])->update($update);
                $updatedCount++;
            }
        }

        CLI::write("Successfully encrypted credentials for $updatedCount device(s) at rest.", 'green');
    }
}
