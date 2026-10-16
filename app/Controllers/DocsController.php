<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CoreConfigModel;
use App\Models\LocationModel;
use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;

/**
 * System Documentation & Operations Manual.
 *
 * Provides a comprehensive, live technical reference for the pfCentral platform,
 * client-side pfSense firewall scripts, critical file paths (head.inc, proxy.pac,
 * authgui.inc, agent), and all scheduled cron jobs (server-side daemons & client crons).
 */
class DocsController extends BaseController
{
    protected CoreConfigModel $coreConfigModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->coreConfigModel = new CoreConfigModel();
    }

    public function index()
    {
        // Enforce session & administrative access
        if (function_exists('auth')) {
            $user = auth()->user();
            if ($user === null) {
                return redirect()->to(base_url('login'));
            }
            if (! $user->can('admin.access')) {
                return redirect()->to(base_url('loggedlive'))->with('error', 'Access restricted to administrators.');
            }
            $firstName = $user->first_name ?? 'Operator';
        } else {
            $firstName = 'Operator';
        }

        // Fetch application configurations
        $appName    = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'pfCentral';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl     = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? base_url();
        $cronToken  = $this->coreConfigModel->where('name', 'sms_cron_token')->first()['value'] ?? 'YOUR_CRON_TOKEN';

        // Count fleet metrics safely
        $deviceCount   = 0;
        $locationCount = 0;
        try {
            $deviceCount   = (new PfInventoryModel())->countAllResults();
            $locationCount = (new LocationModel())->countAllResults();
        } catch (\Throwable $e) {
            // Non-fatal if tables are momentarily unreachable
        }

        // Read script versions dynamically from disk so docs never go stale
        $addHeaderVersion = $this->extractVersion(FCPATH . 'assets/endpoint/add_header.sh');
        $agentVersion     = $this->extractVersion(FCPATH . 'assets/endpoint/agent.sh');
        $backupVersion    = $this->extractVersion(FCPATH . 'assets/endpoint/backup.sh');
        $installVersion   = $this->extractVersion(FCPATH . 'assets/endpoint/install.sh');
        $uninstallVersion = $this->extractVersion(FCPATH . 'assets/endpoint/uninstall.sh');

        $data = [
            'first_name'        => $firstName,
            'firstName'         => $firstName,
            'title'             => strip_tags($appName) . ' 🔸 System Operations & Technical Documentation',
            'app_name'          => $appName,
            'appName'           => $appName,
            'app_version'       => $appVersion,
            'appVersion'        => $appVersion,
            'app_url'           => rtrim($appUrl, '/'),
            'appUrl'            => rtrim($appUrl, '/'),
            'cron_token'        => $cronToken,
            'cronToken'         => $cronToken,
            'device_count'      => $deviceCount,
            'deviceCount'       => $deviceCount,
            'location_count'    => $locationCount,
            'locationCount'     => $locationCount,
            'versions'          => [
                'add_header' => $addHeaderVersion ?: '0910202603',
                'agent'      => $agentVersion ?: '300120251659',
                'backup'     => $backupVersion ?: '300120251659',
                'install'    => $installVersion ?: '0910202603',
                'uninstall'  => $uninstallVersion ?: '300120251659',
            ],
        ];

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('docs/index', $data)
            . view('frontend/footer', $data);
    }

    /**
     * Extracts the VERSION string from a shell script file.
     */
    private function extractVersion(string $filePath): ?string
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath, false, null, 0, 1024);
        if ($content !== false && preg_match("/VERSION=['\"]?([a-zA-Z0-9_\.\-]+)['\"]?/", $content, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
