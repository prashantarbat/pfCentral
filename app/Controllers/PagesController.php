<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CoreConfigModel;

class PagesController extends BaseController
{
    protected $coreConfigModel;

    public function __construct()
    {
        $this->coreConfigModel = new CoreConfigModel();
    }

    private function getCommonData()
    {
        $data = [];
        $config = $this->coreConfigModel->whereIn('name', ['app_name', 'app_version'])->findAll();
        
        $data['app_name'] = 'My Application';
        $data['app_version'] = '1.0.0';
        
        foreach ($config as $c) {
            if ($c['name'] == 'app_name') $data['app_name'] = $c['value'];
            if ($c['name'] == 'app_version') $data['app_version'] = $c['value'];
        }

        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        return $data;
    }

    public function privacyPolicy()
    {
        $data = $this->getCommonData();
        $data['title'] = $data['app_name'] . ' 🔸 Privacy Policy';

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('pages/privacy', $data)
            . view('frontend/footer', $data);
    }

    public function termsOfService()
    {
        $data = $this->getCommonData();
        $data['title'] = $data['app_name'] . ' 🔸 Terms of Service';

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('pages/terms', $data)
            . view('frontend/footer', $data);
    }
}
