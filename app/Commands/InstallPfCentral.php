<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class InstallPfCentral extends BaseCommand
{
    protected $group       = 'pfCentral';
    protected $name        = 'pfcentral:install';
    protected $description = 'Complete 1-command installer for pfCentral database and administrator initialization.';
    protected $usage       = 'pfcentral:install [--admin-user=<user>] [--admin-pass=<pass>] [--admin-email=<email>]';
    protected $options     = [
        '--admin-user'  => 'Administrator username (default: admin)',
        '--admin-pass'  => 'Administrator password (default: random secure password)',
        '--admin-email' => 'Administrator email (default: admin@pfcentral.local)',
    ];

    public function run(array $params)
    {
        CLI::write("==================================================", 'cyan');
        CLI::write("         pfCentral Platform Installer            ", 'green');
        CLI::write(" Centralized pfSense Fleet Management Platform    ", 'white');
        CLI::write("==================================================", 'cyan');

        // Step 1: Check environment and requirements
        CLI::write("[1/4] Checking system prerequisites...", 'yellow');
        $extensions = ['curl', 'intl', 'mbstring', 'xml', 'openssl'];
        $missing = [];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        if (!empty($missing)) {
            CLI::error("Missing required PHP extensions: " . implode(', ', $missing));
            return EXIT_ERROR;
        }
        CLI::write("  PHP " . PHP_VERSION . " and all required extensions are loaded.", 'green');

        // Step 2: Ensure writable directories
        CLI::write("[2/4] Ensuring writable directory permissions...", 'yellow');
        $dirs = [
            WRITEPATH . 'cache',
            WRITEPATH . 'debugbar',
            WRITEPATH . 'logs',
            WRITEPATH . 'session',
            WRITEPATH . 'tmp',
            WRITEPATH . 'uploads',
            WRITEPATH . 'pfcentral_backup'
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        CLI::write("  Writable storage directories verified.", 'green');

        // Step 3: Run database migrations and seeders
        CLI::write("[3/4] Running database migrations and seeders...", 'yellow');
        try {
            // Run migrations across all namespaces (Shield + App)
            $runner = \Config\Services::migrations();
            $runner->setNamespace(null);
            $runner->latest();
            CLI::write("  Database schema migrated successfully.", 'green');

            // Run initial seeder
            $seeder = \Config\Database::seeder();
            $seeder->call('App\Database\Seeds\InitialSeeder');
            CLI::write("  Default demo locations, groups, and device templates seeded.", 'green');
        } catch (\Throwable $e) {
            CLI::error("Database migration/seed error: " . $e->getMessage());
            return EXIT_ERROR;
        }

        // Step 4: Create or reset administrator account
        CLI::write("[4/4] Provisioning primary administrator account...", 'yellow');
        $adminUser  = CLI::getOption('admin-user') ?? 'admin';
        $adminEmail = CLI::getOption('admin-email') ?? 'admin@pfcentral.local';
        $adminPass  = CLI::getOption('admin-pass') ?? ('PfCentral#' . bin2hex(random_bytes(4)) . '!');

        // Call admin:create command
        $this->call('admin:create', [
            'username' => $adminUser,
            'email'    => $adminEmail,
            'password' => $adminPass,
        ]);

        CLI::write("\n==================================================", 'cyan');
        CLI::write("     pfCentral Installation Successfully Completed! ", 'green');
        CLI::write("==================================================", 'cyan');
        CLI::write("Web Portal URL   : " . base_url('login'), 'white');
        CLI::write("Admin Username   : {$adminUser}", 'cyan');
        CLI::write("Admin Password   : {$adminPass}", 'yellow');
        CLI::write("Admin Email      : {$adminEmail}", 'cyan');
        CLI::write("Commercial & MSP : https://pfcentral.microninfo.com", 'green');
        CLI::write("Documentation    : " . base_url('docs'), 'white');
        CLI::write("==================================================", 'cyan');

        return EXIT_SUCCESS;
    }
}
