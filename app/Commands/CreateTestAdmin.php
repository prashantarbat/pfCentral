<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\UserModel;
use App\Services\Users\UserAccountService;
use CodeIgniter\Shield\Entities\User;

class CreateTestAdmin extends BaseCommand
{
    protected $group       = 'pfCentral';
    protected $name        = 'admin:create';
    protected $description = 'Creates or resets an administrator account with full administrative permissions.';

    public function run(array $params)
    {
        $usersModel = model(UserModel::class);
        $service    = new UserAccountService();
        $db         = db_connect();

        $username = CLI::getOption('username') ?? ($params[0] ?? 'admin');
        $email    = CLI::getOption('email') ?? ($params[1] ?? 'admin@pfcentral.local');
        $password = CLI::getOption('password') ?? ($params[2] ?? 'PfCentral#2026!Secure');
        $mobile   = CLI::getOption('mobile') ?? '+15550192831';

        // Check if username exists in users table
        $userRow = $db->table('users')->where('username', $username)->get()->getRowArray();
        
        // Or check if email identity exists in auth_identities
        $identityRow = $db->table('auth_identities')
            ->like('type', 'email', 'after')
            ->where('secret', $email)
            ->get()
            ->getRowArray();

        $userId = $userRow['id'] ?? ($identityRow['user_id'] ?? null);

        if ($userId !== null) {
            CLI::write("User account already exists (ID: {$userId}). Updating credentials...", 'yellow');

            /** @var User|null $existing */
            $existing = $usersModel->findById((int) $userId);
            if ($existing !== null) {
                $existing->first_name    = 'Test';
                $existing->last_name     = 'Admin';
                $existing->active        = 1;
                $existing->password      = $password;
                $existing->mobile_number = $mobile;
                $existing->department    = 'IT Operations';
                $existing->location      = 'Head Office';

                $usersModel->save($existing);

                // Unban if banned
                if ($existing->isBanned()) {
                    $existing->unBan();
                }

                // Ensure admin group
                $existing->syncGroups('admin');

                CLI::write('----------------------------------------', 'green');
                CLI::write('Test Admin Account Ready!', 'green');
                CLI::write('----------------------------------------', 'green');
                CLI::write('Login URL : ' . base_url('login'), 'white');
                CLI::write('Username  : ' . $existing->username, 'cyan');
                CLI::write('Email     : ' . $email, 'cyan');
                CLI::write('Password  : ' . $password, 'yellow');
                CLI::write('Role      : admin', 'green');
                CLI::write('Permissions: Full Administrative Access', 'white');
                CLI::write('----------------------------------------', 'green');

                return EXIT_SUCCESS;
            }
        }

        // Create new account via UserAccountService
        $input = [
            'username'         => $username,
            'email'            => $email,
            'first_name'       => 'Test',
            'last_name'        => 'Admin',
            'mobile_number'    => $mobile,
            'password'         => $password,
            'password_confirm' => $password,
            'group'            => 'admin',
            'department'       => 'IT Operations',
            'location'         => 'Head Office',
        ];

        $outcome = $service->create($input, null);

        if ($outcome['ok']) {
            CLI::write('----------------------------------------', 'green');
            CLI::write('SUCCESS: Test Admin Account Created!', 'green');
            CLI::write('----------------------------------------', 'green');
            CLI::write('Login URL : ' . base_url('login'), 'white');
            CLI::write('Username  : ' . $username, 'cyan');
            CLI::write('Email     : ' . $email, 'cyan');
            CLI::write('Password  : ' . $password, 'yellow');
            CLI::write('Role      : admin', 'green');
            CLI::write('Permissions: Full Administrative Access', 'white');
            CLI::write('----------------------------------------', 'green');

            return EXIT_SUCCESS;
        }

        CLI::error('Failed to create account: ' . $outcome['message']);
        foreach ($outcome['errors'] as $field => $err) {
            CLI::error(" - {$field}: {$err}");
        }

        return EXIT_ERROR;
    }
}
