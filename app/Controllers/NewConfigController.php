<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\CoreConfigModel;
use App\Models\LocationModel;
use App\Models\NewDeviceModel;
use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;
use App\Services\ConfigGen\DeviceConfigInput;
use App\Services\ConfigGen\GeneratedConfigValidator;

class NewConfigController extends BaseController
{
    protected $locationModel;
    protected $coreConfigModel;
    protected $validation;
    protected $NewDeviceModel;
    protected $pfInventoryModel;
    protected $pfStatusModel;
    protected $wgMplsModel;
    protected $wgIllModel;
    protected $wgBdrModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->validation = \Config\Services::validation();
        // Fetched through the shared model factory so a test can substitute them; the models below
        // are constructed directly.
        $this->coreConfigModel = model(CoreConfigModel::class);
        $this->locationModel = model(LocationModel::class);
        $this->NewDeviceModel = new NewDeviceModel();
        $this->pfInventoryModel = new PfInventoryModel();
        $this->pfStatusModel = new PfStatusModel();
        $this->wgMplsModel = new \App\Models\WgMplsModel();
        $this->wgIllModel = new \App\Models\WgIllModel();
        $this->wgBdrModel = new \App\Models\WgBdrModel();
    }

    /**
     * The device-configuration page, and the whole of what it says about itself.
     *
     * This page was two of them, `/newconfig` and `/newdevice`: one page with a variant each, sharing
     * the shell (header, sidebar, app settings, site list), the form, the script, the stylesheet and
     * the install modal, and differing in a description, in whether the key generator was offered,
     * and in how a branch's LAN was described — a subnet, or an address. `locationSearch` was not even
     * a real difference, since both carried a location selector. All of it is now simply what the page
     * does: the LAN field takes either shape and is derived accordingly, the key generator is always
     * there, and the location search that fills the form in is always available. What is left is this
     * description, in one place, because a page that exists once cannot drift from itself.
     *
     * @var array{
     *     title: string,
     *     hero: array{icon: string, title: string, copy: string},
     *     panel: string,
     * }
     */
    private const PAGE = [
        'title' => 'Device Config Generator',
        'hero'  => [
            'icon'  => 'fa-file-code',
            'title' => 'Generate Device Configuration',
            'copy'  => 'Create a new pfSense XML configuration for a branch — pick a site to fill the addressing in, or describe the device yourself.',
        ],
        'panel' => 'Define the device identity, location assignment, and WAN/LAN addressing. Choosing a site fills these in from the branch record; you can override anything.',
    ];

    /**
     * Render the device-configuration page.
     */
    public function index(): string
    {
        $page = self::PAGE;

        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        // Fetch configurations
        $appName = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';

        $data['title'] = strip_tags($appName) . ' 🔸 ' . $page['title'];
        $data['app_name'] = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url'] = $appUrl;

        // Fetch all locations from the database
        $data['locations'] = $this->locationModel->findAll();

        // The page's description, for the view it is rendered by.
        $data['form'] = $page;
        $data['crypto_settings'] = \App\Services\Security\PfSenseCryptoService::getGlobalSettings();

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('config/form', $data)
            . view('frontend/footer', $data);
    }

    /**
     * The two URLs this page used to be served at, which now answer rather than 404.
     *
     * `/newconfig` and `/newdevice` were named in install notes, runbooks, bookmarks and mail, and a
     * page that moves should not turn those into broken links. A 302 rather than a 301: this is a
     * redirect we may still want to undo, and browsers keep a 301 for as long as they like.
     */
    public function wasAt(string $was): ResponseInterface
    {
        if (! in_array($was, ['newconfig', 'newdevice'], true)) {
            throw PageNotFoundException::forPageNotFound("Unknown configuration form: {$was}");
        }

        return redirect()->to(base_url('config-generator'));
    }

    public function getDeviceByLocation()
    {
        $location = trim((string) $this->request->getPost('location_name'));

        if ($location === '') {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No location provided']);
        }

        $branchCode = '';
        if (preg_match('/^(\d{4})/', $location, $m)) {
            $branchCode = $m[1];
        }

        $device = $this->NewDeviceModel
            ->where('TRIM(br_name)', $location)
            ->first();

        if (!$device && $branchCode !== '') {
            $device = $this->NewDeviceModel
                ->like('br_name', $branchCode, 'after')
                ->first();
        }

        // Check pf_locations and pf_inventory for defaults
        $loc = $this->locationModel->where('TRIM(name)', $location)->first();
        if (!$loc && $branchCode !== '') {
            $loc = $this->locationModel->like('name', $branchCode, 'after')->first();
        }

        $last2Digits = $branchCode !== '' ? substr($branchCode, -2) : '';
        $defaultMplsAdd = !empty($loc['wg_mpls']) ? $loc['wg_mpls'] : ($branchCode === '9999' ? 'wg100' : ($last2Digits !== '' ? 'wg1' . $last2Digits : ''));
        $defaultIllIface = !empty($loc['wg_ill']) ? $loc['wg_ill'] : ($branchCode === '9999' ? 'wg500' : ($last2Digits !== '' ? 'wg5' . $last2Digits : ''));

        if ($device) {
            if (empty($device['description'])) {
                $inv = $this->pfInventoryModel->where('TRIM(name)', $location)->first();
                if ($inv && !empty($inv['notes'])) {
                    $device['description'] = $inv['notes'];
                }
            }
            if (empty($device['mpls_ip_add']) && !empty($defaultMplsAdd)) {
                $device['mpls_ip_add'] = $defaultMplsAdd;
            }
            if (empty($device['ill_iface']) && !empty($defaultIllIface)) {
                $device['ill_iface'] = $defaultIllIface;
            }
            return $this->response->setJSON(['status' => 'success', 'data' => $device]);
        }

        // If not yet in pf_devices, prefill sensible defaults
        $inv = $this->pfInventoryModel->where('TRIM(name)', $location)->first();

        $defaultLanIp = $loc['ip'] ?? $inv['man_ip'] ?? '';
        $defaultDesc = $inv['notes'] ?? '';

        return $this->response->setJSON([
            'status' => 'success',
            'data'   => [
                'id'           => '',
                'hostname'     => '',
                'br_name'      => $location,
                'lan_ip'       => $defaultLanIp,
                'lan_subnet'   => '24',
                'mpls_ip_add'  => $defaultMplsAdd,
                'ill_iface'    => $defaultIllIface,
                'description'  => $defaultDesc,
            ]
        ]);
    }

    public function generatePublicKey()
    {
        try {
            $privateKeyInput = $this->request->getPost('private_key');

            if (empty($privateKeyInput)) {
                return $this->response->setJSON(['error' => 'Private key is empty']);
            }

            // Decode Base64 WireGuard private key
            $decodedPrivateKey = base64_decode($privateKeyInput, true);

            if ($decodedPrivateKey === false || strlen($decodedPrivateKey) !== 32) {
                return $this->response->setJSON(['error' => 'Invalid WireGuard private key. Expected 32 bytes.']);
            }

            // WireGuard public key derivation (Curve25519 scalar multiplication with base point)
            $basePoint = sodium_crypto_box_publickey_from_secretkey($decodedPrivateKey);

            // Return public key in Base64 format (WireGuard uses Base64 encoding)
            return $this->response->setJSON([
                'public_key' => base64_encode($basePoint)
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Error generating public key: ' . $e->getMessage());
            return $this->response->setJSON(['error' => 'Error generating key', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Generate and download the configuration the submitted form describes.
     *
     * One action for what used to be two, because the branch it describes is one whether its LAN was
     * given as a subnet or as an address: the only branch-specific step is deriving the LAN, and
     * `DeviceConfigInput` does that from the shape of the value. Everything after it — the shared
     * validation, the template, the refusal of anything unsound, the download — was already common.
     */
    public function generate(): ?ResponseInterface
    {
        if ($this->request->getMethod() !== 'POST') {
            return null;
        }

        // A subnet (`10.60.81.0`) or the appliance's own address (`10.60.81.129`); either way the
        // address the appliance is configured with is what comes back.
        $lan = DeviceConfigInput::lanFromField((string) $this->request->getPost('lan_ip'));

        if (! $lan['ok']) {
            return $this->inputError($lan['message']);
        }

        // The prefix length the LAN belongs to. It is submitted filled in — the field defaults to the
        // /24 the page has always assumed — and it is what the branch's own network is derived from,
        // so a branch on a wider mask is describable rather than silently rounded to a /24.
        $mask = DeviceConfigInput::lanMask((string) $this->request->getPost('lan_subnet'));

        if (! $mask['ok']) {
            return $this->inputError($mask['message']);
        }

        $shared = DeviceConfigInput::validateShared((array) $this->request->getPost());

        if (! $shared['ok']) {
            return $this->inputError($shared['message']);
        }

        $fields = $shared['fields'] + [
            'subnet_id'  => $lan['subnet_id'],
            'lan_ip'     => $lan['lan_ip'],
            'lan_subnet' => $mask['mask'],
        ];

        // Render the single shared pfSense config template, validate it, then download.
        return $this->serveGeneratedConfig($fields, $fields['hostname'], $fields['location']);
    }

    /**
     * The one refusal both forms give for bad input: the same body and the same status, whichever form
     * asked, so a value that is wrong is reported the same way on both routes.
     */
    private function inputError(string $message): ResponseInterface
    {
        return $this->response->setJSON(['status' => 'error', 'message' => $message])->setStatusCode(422);
    }

    /**
     * Render a generated configuration, refuse it if it is not sound, otherwise download it.
     *
     * Nothing invalid is downloaded: the appliance this file configures usually has no console, so a
     * configuration carrying an unsubstituted placeholder or an empty required element is an outage
     * rather than a bug report. The problems are returned to the caller so the person who filled the
     * form can see what to fix, and logged so the refusal is visible after the fact.
     */
    private function serveGeneratedConfig(array $fields, string $hostname, string $locationName): ResponseInterface
    {
        $xmlContent = $this->renderDeviceConfig($fields);

        if ($xmlContent === null) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Template file not found.']);
        }

        $report = GeneratedConfigValidator::check($xmlContent);

        if (! $report['ok']) {
            log_message('error', sprintf(
                'Refused to serve a device configuration for %s (%s): %s',
                $locationName,
                $hostname,
                implode(' ', $report['problems']),
            ));

            return $this->response->setJSON([
                'status'   => 'error',
                'message'  => 'The generated configuration did not pass validation, so nothing was downloaded. ' . $report['message'],
                'problems' => $report['problems'],
            ])->setStatusCode(422);
        }

        // Apply Global pfSense Backup Encryption if enabled
        $cryptoSettings = \App\Services\Security\PfSenseCryptoService::getGlobalSettings();
        $skipEncryption = ($this->request->getPost('plain_xml') === '1') && $cryptoSettings['allow_plain'];

        if ($cryptoSettings['enabled'] && ! $skipEncryption) {
            $xmlContent = \App\Services\Security\PfSenseCryptoService::encryptConfig($xmlContent, $cryptoSettings['password']);
        }

        return $this->downloadXml($xmlContent, $hostname, $locationName);
    }

    /**
     * Render the single shared pfSense configuration template.
     *
     * The page describes a branch; the export is the same pfSense document whichever shape the
     * branch's LAN was given in, so there is one template and not one per input shape.
     *
     * @param array $fields hostname, interface, location, sitecode, subnet_id,
     *                      lan_ip, lan_subnet, mpls_ip, mpls_gw, mpls_wg_gw,
     *                      mpls_port_name, mpls_port_number, mpls_wg_ip,
     *                      ill_wg_gw, ill_port_name, ill_port_number, ill_wg_ip,
     *                      private_key, public_key
     * @return string|null null when the template file is missing
     */
    private function renderDeviceConfig(array $fields): ?string
    {
        // Kept outside public/: these exports carry VPN keys, certificates and
        // passwords, and under a plain web root they were downloadable by anyone.
        $templatePath = ROOTPATH . 'templates/New_Device_Config.xml';
        if (!file_exists($templatePath)) {
            return null;
        }

        $subnetId = $fields['subnet_id'];
        $lanIp    = $fields['lan_ip'];
        $lanMask  = $fields['lan_subnet'];
        $lanParts = explode('.', $lanIp);
        // Third octet of the LAN IP, e.g. "21" -> opt4 / BDR tunnel address 172.31.0.21
        $bdrOctet = $lanParts[2] ?? '0';

        // The network the branch is actually addressed on. The submitted address and prefix length
        // name it together; the first three octets alone do not — they describe the /24 the address
        // happens to sit in, which is the wrong network for a branch on any other mask.
        $lanNetwork = DeviceConfigInput::network($lanIp, $lanMask);

        $placeholders = [
            '{hostname}'           => $fields['hostname'],
            '{location}'           => $fields['location'],
            '{interface}'          => $fields['interface'],
            '{sitecode}'           => $fields['sitecode'],
            '{subnet}'             => $subnetId,
            '{subnet_id}'          => $subnetId,
            '{lan_ip}'             => $lanIp,
            '{lan_subnet}'         => $fields['lan_subnet'],
            '{mpls_ip}'            => $fields['mpls_ip'],
            '{mpls_gw}'            => $fields['mpls_gw'],
            '{mpls_wg_gw}'         => $fields['mpls_wg_gw'],
            '{mpls_port_name}'     => $fields['mpls_port_name'],
            '{mpls_port_number}'   => $fields['mpls_port_number'],
            '{mpls_wg_ip}'         => $fields['mpls_wg_ip'],
            '{ill_wg_gw}'          => $fields['ill_wg_gw'],
            '{ill_port_name}'      => $fields['ill_port_name'],
            '{ill_port_number}'    => $fields['ill_port_number'],
            '{ill_wg_ip}'          => $fields['ill_wg_ip'],
            '{private_key}'        => $fields['private_key'],
            '{public_key}'         => $fields['public_key'],
            // Squid's proxy allow-list, in the two fields the package reads: the branch's own network
            // and the one host exempted from it. Both are padded exactly once, by base64_encode.
            '{allownetwork64}'     => base64_encode($lanNetwork . '/' . $lanMask),
            '{allowhost64}'        => base64_encode($subnetId . '.49/32'),
            '{lanip_proxy_base64}' => base64_encode($lanIp),
            '{bdr_ip4}'            => $bdrOctet,
        ];

        return str_replace(
            array_keys($placeholders),
            array_values($placeholders),
            file_get_contents($templatePath)
        );
    }

    /**
     * Force-download generated XML, named after the host and location.
     */
    private function downloadXml(string $xmlContent, string $hostname, string $locationName): ResponseInterface
    {
        $filename = $hostname . '_' . str_replace(' ', '_', $locationName) . '.xml';

        return $this->response->setHeader('Content-Type', 'application/xml')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($xmlContent);
    }

    public function saveDeviceConfig()
    {
        try {
            $enteredMasterPass = $this->request->getPost('master_password');
            $row = $this->coreConfigModel->where('name', 'mast_pass')->first();
            $secret = $row['value'] ?? 'p@ssw0rd';

            if ($enteredMasterPass === null || (string)$enteredMasterPass !== (string)$secret) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Incorrect master password. Configuration not saved.'
                ])->setStatusCode(400);
            }

            $id = $this->request->getPost('id');
            $data = [
                'hostname'    => trim((string) $this->request->getPost('hostname')),
                'br_name'     => trim((string) $this->request->getPost('br_name')),
                'priv_key'    => trim((string) $this->request->getPost('priv_key')),
                'pub_key'     => trim((string) $this->request->getPost('pub_key')),
                'lan_ip'      => trim((string) $this->request->getPost('lan_ip')),
                'lan_subnet'  => trim((string) $this->request->getPost('lan_subnet')),
                'mpls_ip'     => trim((string) $this->request->getPost('mpls_ip')),
                'mpls_gw'     => trim((string) $this->request->getPost('mpls_gw')),
                'mpls_ip_add' => trim((string) $this->request->getPost('mpls_ip_add')),
                'mpls_wgip'   => trim((string) $this->request->getPost('mpls_wgip')),
                'mpls_port'   => trim((string) $this->request->getPost('mpls_port')),
                'ill_iface'   => trim((string) $this->request->getPost('ill_iface')),
                'ill_ip'      => trim((string) $this->request->getPost('ill_ip')),
                'ill_port'    => trim((string) $this->request->getPost('ill_port')),
                'description' => trim((string) $this->request->getPost('description')),
            ];

            if (empty($data['br_name'])) {
                return $this->response->setJSON(['status' => 'error', 'message' => 'Branch name is required.']);
            }

            if ($id) {
                $this->NewDeviceModel->update($id, $data);
            } else {
                // Check if already exists for this branch
                $existing = $this->NewDeviceModel->where('TRIM(br_name)', $data['br_name'])->first();
                if ($existing) {
                    $this->NewDeviceModel->update($existing['id'], $data);
                } else {
                    $this->NewDeviceModel->insert($data);
                }
            }

            // Sync updated info into pf_inventory and pf_status
            $inv = $this->pfInventoryModel->where('TRIM(name)', $data['br_name'])->first();
            if (!$inv) {
                $loc = $this->locationModel->where('TRIM(name)', $data['br_name'])->first();
                $newInv = [
                    'name'     => $data['br_name'],
                    'man_ip'   => filter_var($data['lan_ip'], FILTER_VALIDATE_IP) ? $data['lan_ip'] : null,
                    'notes'    => $data['description'] ?? '',
                    'location' => $loc['id'] ?? null,
                    'active'   => 1,
                ];
                $invId = $this->pfInventoryModel->insert($newInv, true);
                if ($invId) {
                    $inv = $this->pfInventoryModel->find($invId);
                }
            }

            if ($inv) {
                $invUpdate = [];
                if ((empty($inv['man_ip']) || !filter_var($inv['man_ip'], FILTER_VALIDATE_IP)) && !empty($data['lan_ip'])) {
                    $invUpdate['man_ip'] = $data['lan_ip'];
                }
                if (!empty($data['description'])) {
                    $invUpdate['notes'] = $data['description'];
                }
                if (empty($inv['active']) || (int)$inv['active'] === 0) {
                    $invUpdate['active'] = 1;
                }
                if (!empty($invUpdate)) {
                    $this->pfInventoryModel->update($inv['id'], $invUpdate);
                }

                $statusRecord = $this->pfStatusModel->where('pf_id', $inv['id'])->first();
                $statusUpdate = [];
                if (!empty($data['hostname'])) {
                    $statusUpdate['hostname'] = $data['hostname'];
                }
                if (!empty($data['mpls_ip'])) {
                    $statusUpdate['mpls_ip'] = $data['mpls_ip'];
                }
                if (!empty($data['ill_ip'])) {
                    $statusUpdate['ill_ip'] = $data['ill_ip'];
                }
                if (!empty($data['lan_ip']) && (empty($statusRecord) || empty($statusRecord['ip']) || !filter_var($statusRecord['ip'], FILTER_VALIDATE_IP))) {
                    $statusUpdate['ip'] = $data['lan_ip'];
                }

                if (!empty($statusUpdate)) {
                    if ($statusRecord) {
                        $this->pfStatusModel->where('pf_id', $inv['id'])->set($statusUpdate)->update();
                    } else {
                        $statusUpdate['pf_id'] = $inv['id'];
                        $statusUpdate['status'] = 'offline';
                        $this->pfStatusModel->insert($statusUpdate);
                    }
                }
            }

            // Automatically sync WireGuard tables (wg_bdr, wg_ill, wg_mpls) and pf_locations
            $this->syncWireguardTables($data);

            return $this->response->setJSON(['status' => 'success', 'message' => 'Configuration saved successfully.']);
        } catch (\Throwable $e) {
            log_message('error', 'saveDeviceConfig failed: ' . $e->getMessage());
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to save configuration: ' . $e->getMessage()]);
        }
    }

    /**
     * Automatically update pf_locations, wg_bdr, wg_ill, and wg_mpls tables
     * using common branch code / location matching logic and user input.
     */
    private function syncWireguardTables(array $data): void
    {
        $brName = trim((string) ($data['br_name'] ?? ''));
        $hostname = trim((string) ($data['hostname'] ?? ''));
        if ($brName === '') {
            return;
        }

        // 1. Extract 4-digit branch code
        $branchCode = '';
        if (preg_match('/^(\d{4})/', $brName, $bm)) {
            $branchCode = $bm[1];
        } elseif (preg_match('/(?:AU\d{2}Pf)?(?:Br|Ho)?(\d{4})/i', $hostname, $hm)) {
            $branchCode = $hm[1];
        }

        // 2. Resolve matching pf_locations record
        $loc = $this->locationModel->where('TRIM(name)', $brName)->first();
        if (!$loc && $branchCode !== '') {
            $loc = $this->locationModel->like('name', $branchCode, 'after')->first();
        }
        if (!$loc) {
            $inv = $this->pfInventoryModel->where('TRIM(name)', $brName)->first();
            if ($inv && !empty($inv['location'])) {
                $loc = $this->locationModel->find($inv['location']);
            }
        }

        // If location does not exist in pf_locations, create it
        if (!$loc) {
            $newLocData = [
                'name'    => $brName,
                'ip'      => filter_var($data['lan_ip'] ?? '', FILTER_VALIDATE_IP) ? $data['lan_ip'] : null,
                'address' => $data['description'] ?? '',
            ];
            $newLocId = $this->locationModel->insert($newLocData, true);
            if ($newLocId) {
                $loc = $this->locationModel->find($newLocId);
            }
        }

        if (!$loc) {
            return;
        }

        $locId = (int) $loc['id'];
        $last2Digits = $branchCode !== '' ? substr($branchCode, -2) : '';

        // 3. Resolve interface names for each link (User Input -> Existing Location Value -> Branch Code Convention)
        // MPLS interface (e.g. wg138, wg101, MPLS)
        $userMpls = trim((string) ($data['mpls_ip_add'] ?? ''));
        $mplsIface = $userMpls !== ''
            ? $userMpls
            : (!empty($loc['wg_mpls']) ? $loc['wg_mpls'] : ($branchCode === '9999' ? 'wg100' : ($last2Digits !== '' ? 'wg1' . $last2Digits : '')));

        // ILL interface (e.g. wg538, wg501, WG60001)
        $userIll = trim((string) ($data['ill_iface'] ?? ''));
        $illIface = $userIll !== ''
            ? $userIll
            : (!empty($loc['wg_ill']) ? $loc['wg_ill'] : ($branchCode === '9999' ? 'wg500' : ($last2Digits !== '' ? 'wg5' . $last2Digits : '')));

        // BDR interface (always calculated automatically in lowercase, e.g. br1038, ho9999)
        $bdrIface = $branchCode === '9999'
            ? 'ho9999'
            : ($branchCode !== '' ? 'br' . strtolower($branchCode) : (!empty($loc['wg_bdr']) ? strtolower(trim($loc['wg_bdr'])) : ''));

        // 4. Update pf_locations with verified interface and IP values
        $locUpdate = [];
        if (!empty($mplsIface) && ($loc['wg_mpls'] ?? '') !== $mplsIface) {
            $locUpdate['wg_mpls'] = $mplsIface;
        }
        if (!empty($illIface) && ($loc['wg_ill'] ?? '') !== $illIface) {
            $locUpdate['wg_ill'] = $illIface;
        }
        if (!empty($bdrIface) && ($loc['wg_bdr'] ?? '') !== $bdrIface) {
            $locUpdate['wg_bdr'] = $bdrIface;
        }
        $validLanIp = filter_var($data['lan_ip'] ?? '', FILTER_VALIDATE_IP) ? $data['lan_ip'] : null;
        if ($validLanIp && (empty($loc['ip']) || !filter_var($loc['ip'], FILTER_VALIDATE_IP))) {
            $locUpdate['ip'] = $validLanIp;
        }
        if (!empty($locUpdate)) {
            $this->locationModel->update($locId, $locUpdate);
        }

        // 5. Update wg_mpls table (by location_id or interface)
        if (!empty($mplsIface)) {
            $existingMpls = $this->wgMplsModel->where('location_id', $locId)->first();
            if (!$existingMpls) {
                $existingMpls = $this->wgMplsModel->where('interface', $mplsIface)->first();
            }
            if ($existingMpls) {
                $this->wgMplsModel->update($existingMpls['id'], [
                    'location_id' => $locId,
                    'interface'   => $mplsIface,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            } else {
                $this->wgMplsModel->insert([
                    'location_id' => $locId,
                    'interface'   => $mplsIface,
                    'status'      => 0,
                    'flap_count'  => 0,
                ]);
            }
        }

        // 6. Update wg_ill table (by location_id or interface)
        if (!empty($illIface)) {
            $existingIll = $this->wgIllModel->where('location_id', $locId)->first();
            if (!$existingIll) {
                $existingIll = $this->wgIllModel->where('interface', $illIface)->first();
            }
            if ($existingIll) {
                $this->wgIllModel->update($existingIll['id'], [
                    'location_id' => $locId,
                    'interface'   => $illIface,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            } else {
                $this->wgIllModel->insert([
                    'location_id' => $locId,
                    'interface'   => $illIface,
                    'status'      => 0,
                    'flap_count'  => 0,
                ]);
            }
        }

        // 7. Update wg_bdr table (by location_id or interface, stores lowercase br1038 / ho9999)
        if (!empty($bdrIface)) {
            $existingBdr = $this->wgBdrModel->where('location_id', $locId)->first();
            if (!$existingBdr) {
                $existingBdr = $this->wgBdrModel->where('interface', $bdrIface)->first();
            }
            if ($existingBdr) {
                $this->wgBdrModel->update($existingBdr['id'], [
                    'location_id' => $locId,
                    'interface'   => $bdrIface,
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            } else {
                $this->wgBdrModel->insert([
                    'location_id' => $locId,
                    'interface'   => $bdrIface,
                    'status'      => 0,
                    'flap_count'  => 0,
                ]);
            }
        }

        // 8. Ensure pf_inventory points to this location_id
        $inv = $this->pfInventoryModel->where('TRIM(name)', $brName)->first();
        if ($inv && (empty($inv['location']) || (int) $inv['location'] !== $locId)) {
            $this->pfInventoryModel->update($inv['id'], ['location' => $locId]);
        }
    }

    public function deleteDeviceConfig()
    {
        try {
            $enteredMasterPass = $this->request->getPost('master_password');
            $row = $this->coreConfigModel->where('name', 'mast_pass')->first();
            $secret = $row['value'] ?? 'p@ssw0rd';

            if ($enteredMasterPass === null || (string)$enteredMasterPass !== (string)$secret) {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Incorrect master password. Configuration not deleted.'
                ])->setStatusCode(400);
            }

            $id = $this->request->getPost('id');
            if ($id) {
                $this->NewDeviceModel->delete($id);
                return $this->response->setJSON(['status' => 'success', 'message' => 'Configuration deleted successfully.']);
            }
            return $this->response->setJSON(['status' => 'error', 'message' => 'ID is required.']);
        } catch (\Throwable $e) {
            log_message('error', 'deleteDeviceConfig failed: ' . $e->getMessage());
            return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to delete configuration: ' . $e->getMessage()]);
        }
    }

}