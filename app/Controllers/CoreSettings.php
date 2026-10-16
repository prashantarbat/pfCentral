<?php

namespace App\Controllers;

use App\Models\CoreConfigModel;
use CodeIgniter\Controller;

class CoreSettings extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new CoreConfigModel(); // Initialize model
    }

    public function index()
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        // Fetch configurations safely
        $data['app_name'] = $this->model->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $data['app_version'] = $this->model->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $data['app_url'] = $this->model->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';

        $data['title'] = strip_tags($data['app_name']) . ' 🔸 Core Application Settings';

        // Fetch all settings as an associative array
        $settingsData = $this->model->findAll();
        $settings = [];
        foreach ($settingsData as $setting) {
            $settings[$setting['name']] = $setting; // Ensure key-based structure
        }
        $data['settings'] = $settings; // Pass settings to the view

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('core_settings', $data) // 🔹 Changed to `core_settings.php`
            . view('frontend/footer', $data);
    }

    public function update()
    {
        $request = $this->request->getPost();

        // Define all known settings keys and their default fallback values when absent (e.g. unchecked checkboxes)
        $allKeys = [
            'app_name' => 'pfCentral',
            'app_url' => 'https://pfcentral.local/',
            'app_version' => 'I.VI',
            'check_timeout' => '5',
            'company_name' => 'pfCentral Organization',
            'date_format' => 'Y-m-d;yyyy-mm-dd',
            'default_lang' => 'en',
            'history_retention' => '90',
            'log_retention' => '90',
            'timezone' => 'UTC',
            'website_timeout' => '100',
            'week_start' => '1',
            'xss_filtering' => 'true',
            'email_from_address' => 'nmon@example.com',
            'email_from_name' => 'nMon',
            // Without an SMTP host, mail falls back to the server's sendmail; drift alerts then record
            // and stay visible but cannot be delivered, and say so rather than failing silently.
            'email_smtp_enable' => 'false',
            'email_smtp_host' => '',
            'email_smtp_port' => '587',
            'email_smtp_username' => '',
            'email_smtp_password' => '',
            'email_smtp_security' => 'tls',

            // Config drift alerts: emailed when a device stops matching an applied job. With no
            // recipient list, every administrator with an email login is told.
            'drift_alert_enable' => '1',
            'drift_alert_recipients' => '',

            // Live Dashboard Alert Settings
            'live_alerts_enable' => '1',
            'live_alerts_timing' => '60',

            // Global Backup & Config Generator Encryption Keys
            'backup_encryption_enable'      => '0',
            'backup_encryption_password'    => '',
            'backup_encryption_allow_plain' => '0',

            // SMS branch-down alerts: disabled by default; the cron endpoint refuses to send
            // anything until it is explicitly enabled here. Credentials live in core_config
            // instead of being hardcoded in the controller so they can be rotated from the UI.
            'sms_alerts_enable' => '0',
            'sms_gateway_type' => 'vfirst',
            'sms_api_url' => '',
            'sms_api_username' => '',
            'sms_api_password' => '',
            'sms_sender_id' => '',
            'sms_template_id' => '',
            'sms_alert_recipients' => '',
            // Shared secret the SMS cron caller must present as ?token=…; blank means
            // every caller is refused (the endpoint fails closed).
            'sms_cron_token' => '',

            // LibreNMS & Grafana Settings Keys
            'librenms_enable' => '1',
            'grafana_enable' => '1',
            'librenms_api_url' => 'http://localhost/api/v0',
            'librenms_api_token' => '',
            'librenms_snmp_community' => 'your-secure-snmp-community',
            'librenms_snmp_version' => 'v2c',
            'grafana_url' => 'http://localhost:3000/',

            // Network Diagram Visual Configuration Keys
            'netdiag_mpls_color'        => '#16a34a',
            'netdiag_ill_color'         => '#f59e0b',
            'netdiag_down_mpls_color'   => '#dc2626',
            'netdiag_down_ill_color'    => '#c2410c',
            'netdiag_hub_color'         => '#2563eb',
            'netdiag_branch_color'      => '#16a34a',
            'netdiag_down_branch_color' => '#dc2626',
            'netdiag_mpls_line_type'    => 'solid',
            'netdiag_ill_line_type'     => 'dashed',
            'netdiag_line_width'        => '3',
            'netdiag_line_smooth'       => 'curvedCW',
            'netdiag_hub_icon'          => 'f233',
            'netdiag_branch_icon'       => 'f1ad',
            'netdiag_down_branch_icon'  => 'f1ad',
            'netdiag_hub_image'         => '',
            'netdiag_branch_image'      => '',
            'netdiag_down_branch_image' => '',
            'netdiag_enable_shapes'     => '0',
            'netdiag_node_shape'        => 'circle',
            'netdiag_hub_shape'         => 'default',
            'netdiag_branch_shape'      => 'default',
            'netdiag_down_branch_shape' => 'default',
            'netdiag_shape_style'       => 'solid',

            // Active Directory Config Keys
            'ad_enable_auth' => '0',
            'ad_host' => 'dc01.corp.example.local',
            'ad_port' => '636',
            'ad_base_dn' => 'DC=corp,DC=example,DC=local',
            'ad_domain' => 'corp.example.local',
            'ad_netbios_domain' => 'CORP',
            'ad_use_ldaps' => '0',
            'ad_verify_peer' => '0',
            'ad_allow_self_signed' => '0',
            'ad_bind_dn' => 'CN=Binder Administrator,CN=Users,DC=corp,DC=example,DC=local',
            // Plain sAMAccountName, not a UPN: the bind identity builder appends the
            // domain itself, so a 'user@domain' value here produces 'user@domain@domain'.
            'ad_bind_username' => 'ADBINDER',
            // No credential belongs in source. This fallback only applies when the form
            // does not submit the field; the stored value lives in core_config.
            'ad_bind_password' => '',
            'ad_user_filter_attribute' => 'sAMAccountName',
            'ad_fallback_to_local' => '0',
            'ad_auto_create_user' => '0',
            'ad_default_group' => 'user',
            'ad_allow_any_ad_user' => '0',
            'ad_group_auth_enabled' => '0',
        ];

        // Dynamically add AD mappings for all defined roles
        $authGroups = config('AuthGroups')->groups ?? [];
        foreach ($authGroups as $gKey => $gData) {
            $allKeys['ad_group_mapping_' . $gKey] = '';
        }

        if (!empty($request)) {
            // Merge actual POST request values over the default values list
            foreach ($allKeys as $key => $defaultValue) {
                $value = isset($request[$key]) ? $request[$key] : $defaultValue;

                // If it is a checkbox from request, map "on" or 1 to "1"
                if ($value === 'on' || $value === 1) {
                    $value = '1';
                }

                // Safely update or insert
                $exists = $this->model->where('name', $key)->first();
                if ($exists) {
                    $this->model->update($key, ['value' => $value]);
                } else {
                    $this->model->insert([
                        'name' => $key,
                        'value' => $value
                    ]);
                }
            }

            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Global configurations updated successfully.'
                ]);
            }

            session()->setFlashdata('success', 'Settings updated successfully.');
        } else {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'No configuration changes were provided.'
                ])->setStatusCode(400);
            }

            session()->setFlashdata('error', 'No changes were made.');
        }

        return redirect()->back(); // Redirects to the previous page
    }

    public function verifyMasterPassword()
    {
        $currentPass = $this->request->getPost('current_password');
        $row = $this->model->where('name', 'mast_pass')->first();
        $secret = $row['value'] ?? 'p@ssw0rd';

        if ($currentPass !== null && (string)$currentPass === (string)$secret) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Current master password verified successfully.'
            ]);
        }

        return $this->response->setJSON([
            'success' => false,
            'message' => 'Incorrect current master password.'
        ])->setStatusCode(400);
    }

    public function changeMasterPassword()
    {
        $currentPass = $this->request->getPost('current_password');
        $newPass     = $this->request->getPost('new_password');
        $confirmPass = $this->request->getPost('confirm_password');

        $row = $this->model->where('name', 'mast_pass')->first();
        $secret = $row['value'] ?? 'p@ssw0rd';

        if ($currentPass === null || (string)$currentPass !== (string)$secret) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Incorrect current master password.'
            ])->setStatusCode(400);
        }

        if (empty($newPass)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'New master password cannot be empty.'
            ])->setStatusCode(400);
        }

        if ($newPass !== $confirmPass) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'New master password and confirmation do not match.'
            ])->setStatusCode(400);
        }

        if ($row) {
            $this->model->where('name', 'mast_pass')->set(['value' => $newPass])->update();
        } else {
            $this->model->insert(['name' => 'mast_pass', 'value' => $newPass]);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Master password updated successfully.'
        ]);
    }

    /**
     * AJAX endpoint to upload a custom SVG/PNG icon for the network diagram
     */
    public function uploadDiagramIcon()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Unauthorized action.'
            ])->setStatusCode(403);
        }

        $target = $this->request->getPost('target'); // 'hub', 'branch', or 'down_branch'
        if (!in_array($target, ['hub', 'branch', 'down_branch'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid node target specified.'
            ])->setStatusCode(400);
        }

        $file = $this->request->getFile('icon_file');
        if (!$file || !$file->isValid() || $file->hasMoved()) {
            return $this->response->setJSON([
                'success' => false,
                'message' => $file ? $file->getErrorString() : 'No image file uploaded.'
            ])->setStatusCode(400);
        }

        $ext = strtolower($file->getClientExtension());
        $allowed = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
        if (!in_array($ext, $allowed, true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid file format. Allowed formats: SVG, PNG, WEBP, JPG.'
            ])->setStatusCode(400);
        }

        $uploadDir = FCPATH . 'assets/uploads/topology/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Clean up previous custom image for this target if exists
        $prevSetting = $this->model->where('name', 'netdiag_' . $target . '_image')->first();
        if (!empty($prevSetting['value'])) {
            $prevPath = FCPATH . ltrim($prevSetting['value'], '/\\');
            if (is_file($prevPath)) {
                @unlink($prevPath);
            }
        }

        $newName = 'icon_' . $target . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $file->move($uploadDir, $newName);

        $relPath = 'assets/uploads/topology/' . $newName;

        // Persist to core_config
        $key = 'netdiag_' . $target . '_image';
        $exists = $this->model->where('name', $key)->first();
        if ($exists) {
            $this->model->update($key, ['value' => $relPath]);
        } else {
            $this->model->insert(['name' => $key, 'value' => $relPath]);
        }

        return $this->response->setJSON([
            'success'   => true,
            'message'   => ucfirst(str_replace('_', ' ', $target)) . ' custom icon uploaded successfully.',
            'target'    => $target,
            'rel_path'  => $relPath,
            'image_url' => base_url($relPath),
        ]);
    }

    /**
     * AJAX endpoint to remove a custom SVG/PNG icon and restore font icon
     */
    public function removeDiagramIcon()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Unauthorized action.'
            ])->setStatusCode(403);
        }

        $target = $this->request->getPost('target');
        if (!in_array($target, ['hub', 'branch', 'down_branch'], true)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Invalid node target specified.'
            ])->setStatusCode(400);
        }

        $key = 'netdiag_' . $target . '_image';
        $prevSetting = $this->model->where('name', $key)->first();
        if (!empty($prevSetting['value'])) {
            $prevPath = FCPATH . ltrim($prevSetting['value'], '/\\');
            if (is_file($prevPath)) {
                @unlink($prevPath);
            }
        }

        if ($prevSetting) {
            $this->model->update($key, ['value' => '']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Custom image removed. Font icon restored.',
            'target'  => $target,
        ]);
    }
}
