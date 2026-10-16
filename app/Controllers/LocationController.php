<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\CoreConfigModel;
use App\Models\LocationModel;
use App\Models\AppGroupsModel;
use App\Models\PfInventoryModel;

class LocationController extends BaseController
{
    protected $locationModel;
    protected $coreConfigModel;
    protected $appGroupsModel;
    protected $pfInventoryModel;
    protected $validation;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->validation = \Config\Services::validation();
        $this->coreConfigModel = new CoreConfigModel();  
        $this->locationModel = new LocationModel();
        $this->appGroupsModel = new AppGroupsModel();
        $this->pfInventoryModel = new PfInventoryModel();
    }

    public function index()
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';
    
        // Fetch configurations
        $appName = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';
    
        $data['title'] = strip_tags($appName) . ' 🔸 Sites & Locations';
        $data['app_name'] = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url'] = $appUrl;
    
        // Fetch all locations from the database
        $locations = $this->locationModel->findAll();
        // Fetch all groups from the database
        $groups = $this->appGroupsModel->findAll();
        // Fetch all inventory devices with management details
        $devices = $this->pfInventoryModel->select('id, name, man_ip, man_port, groups, location')->orderBy('name', 'ASC')->findAll();

        $deviceCountByLocation = [];
        $devicesByLocation = [];
        $deviceCountByGroup = [];
        $devicesByGroup = [];

        foreach ($devices as $d) {
            $locId = $d['location'] ?? null;
            if ($locId) {
                $deviceCountByLocation[$locId] = ($deviceCountByLocation[$locId] ?? 0) + 1;
                $devicesByLocation[$locId][] = $d;
            }
            $grpName = trim($d['groups'] ?? '');
            if ($grpName !== '') {
                $deviceCountByGroup[$grpName] = ($deviceCountByGroup[$grpName] ?? 0) + 1;
                $devicesByGroup[$grpName][] = $d;
            }
        }

        $data['locations'] = $locations;
        $data['groups'] = $groups;
        $data['devices'] = $devices;
        $data['deviceCountByLocation'] = $deviceCountByLocation;
        $data['devicesByLocation'] = $devicesByLocation;
        $data['deviceCountByGroup'] = $deviceCountByGroup;
        $data['devicesByGroup'] = $devicesByGroup;
    
        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('locations/index', $data)
            . view('frontend/footer', $data);
    }


    public function getLocations()
    {
        $locations = $this->locationModel->findAll();
        return $this->response->setJSON(['data' => $locations]);
    }

    public function store()
    {
        $data = [
            'name'      => $this->request->getPost('name'),
            'address'   => $this->request->getPost('address'),
            'email'     => $this->request->getPost('email'),
            'phone'     => $this->request->getPost('phone'),
            'latitude'  => $this->request->getPost('latitude'),
            'longitude' => $this->request->getPost('longitude'),
        ];

        if ($this->locationModel->insert($data)) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Location added successfully']);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to add location']);
    }

    public function edit($id)
    {
        $location = $this->locationModel->find($id);
        return $this->response->setJSON($location);
    }

    public function update($id)
    {
        $data = [
            'name'      => $this->request->getPost('name'),
            'address'   => $this->request->getPost('address'),
            'email'     => $this->request->getPost('email'),
            'phone'     => $this->request->getPost('phone'),
            'latitude'  => $this->request->getPost('latitude'),
            'longitude' => $this->request->getPost('longitude'),
        ];
    
        if ($this->locationModel->update($id, $data)) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Location updated successfully']);
        }
    
        return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to update location']);
    }


    public function delete($id)
    {
        if ($this->locationModel->delete($id)) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Location deleted successfully']);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'Failed to delete location']);
    }

    /**
     * Clear / Reset link flap count in wg_mpls, wg_ill, wg_bdr to 0
     */
    public function clearFlapCount()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Administrator access required to clear flap counts.'
            ]);
        }

        $linkType = strtolower(trim((string)$this->request->getPost('link_type')));
        $validTypes = ['mpls', 'impls', 'ill', 'bdr', 'all'];

        if (!in_array($linkType, $validTypes, true)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Invalid link type selected. Choose IMPLS, ILL, BDR, or ALL.'
            ]);
        }

        $db = \Config\Database::connect();
        $tablesUpdated = [];

        try {
            if ($linkType === 'mpls' || $linkType === 'impls' || $linkType === 'all') {
                $db->query("UPDATE wg_mpls SET flap_count = 0");
                $tablesUpdated[] = 'IMPLS (wg_mpls)';
            }

            if ($linkType === 'ill' || $linkType === 'all') {
                $db->query("UPDATE wg_ill SET flap_count = 0");
                $tablesUpdated[] = 'ILL (wg_ill)';
            }

            if ($linkType === 'bdr' || $linkType === 'all') {
                $db->query("UPDATE wg_bdr SET flap_count = 0");
                $tablesUpdated[] = 'BDR (wg_bdr)';
            }

            $tableListStr = implode(', ', $tablesUpdated);
            return $this->response->setJSON([
                'status'   => 'success',
                'message'  => "Flap count successfully cleared to 0 for {$tableListStr}."
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Error clearing flap counts: ' . $e->getMessage());
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Database error while resetting flap counts: ' . $e->getMessage()
            ]);
        }
    }
}

