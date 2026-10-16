<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\WgMplsModel;
use App\Models\WgIllModel;

class TxRxDataController extends ResourceController
{
    public function index()
    {
        $iface = $this->request->getGet('iface');
        $from  = $this->request->getGet('from');
        $to    = $this->request->getGet('to');

        if (!$iface || !$from || !$to) {
            return $this->fail('Missing parameters');
        }

        // Assume input times are in IST
        date_default_timezone_set('Asia/Kolkata');
        $fromIST = new \DateTime($from);
        $toIST   = new \DateTime($to);

        // Convert to UTC for querying DB
        $fromUTC = $fromIST->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $toUTC   = $toIST->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        // Choose model
        $model = str_starts_with($iface, 'wg5') ? new WgIllModel() : new WgMplsModel();

        // Fetch data using UTC range
        $data = $model->where('interface', $iface)
                      ->where('created_at >=', $fromUTC)
                      ->where('created_at <=', $toUTC)
                      ->orderBy('created_at', 'ASC')
                      ->findAll();

        // Return time in UTC (frontend already converts to local)
        $response = array_map(function($row) {
            return [
                'time' => $row['created_at'], // Still in UTC
                'tx'   => round($row['tx'], 2),
                'rx'   => round($row['rx'], 2),
            ];
        }, $data);

        return $this->respond($response);
    }
}
