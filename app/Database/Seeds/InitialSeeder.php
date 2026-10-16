<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialSeeder extends Seeder
{
    public function run()
    {
        // 1. Seed Generic Locations
        $locations = [
            [
                'name'      => 'HQ Data Center (New York)',
                'ip'        => '10.10.0.1',
                'address'   => '100 Technology Plaza, New York, NY 10001',
                'email'     => 'ops-ny@example.com',
                'phone'     => '+1 (555) 019-2831',
                'latitude'  => '40.7128',
                'longitude' => '-74.0060',
                'groups'    => 'Data Center Clusters',
            ],
            [
                'name'      => 'Regional Branch East (Boston)',
                'ip'        => '192.168.10.1',
                'address'   => '500 Harbor Boulevard, Boston, MA 02110',
                'email'     => 'branch-bos@example.com',
                'phone'     => '+1 (555) 019-2832',
                'latitude'  => '42.3601',
                'longitude' => '-71.0589',
                'groups'    => 'Branch Gateways',
            ],
            [
                'name'      => 'Regional Branch West (San Francisco)',
                'ip'        => '192.168.20.1',
                'address'   => '10 Innovation Parkway, San Francisco, CA 94105',
                'email'     => 'branch-sf@example.com',
                'phone'     => '+1 (555) 019-2833',
                'latitude'  => '37.7749',
                'longitude' => '-122.4194',
                'groups'    => 'Branch Gateways',
            ]
        ];

        $locationModel = new \App\Models\LocationModel();
        foreach ($locations as $loc) {
            if (!$locationModel->where('name', $loc['name'])->first()) {
                $locationModel->insert($loc);
            }
        }

        // 2. Seed Application Groups
        $groups = [
            [
                'name'        => 'Perimeter Firewalls',
                'description' => 'Primary perimeter network edge firewalls.'
            ],
            [
                'name'        => 'Branch Gateways',
                'description' => 'Remote office and branch firewall gateways.'
            ],
            [
                'name'        => 'Data Center Clusters',
                'description' => 'High-availability core data center cluster pairs.'
            ]
        ];

        $groupModel = new \App\Models\AppGroupsModel();
        foreach ($groups as $grp) {
            if (!$groupModel->where('name', $grp['name'])->first()) {
                $groupModel->insert($grp);
            }
        }

        // 3. Seed Initial Inventory Devices
        $inventoryModel = new \App\Models\PfInventoryModel();
        $devices = [
            [
                'name'        => 'FW-HQ-PRIMARY',
                'netgate_id'  => 'NET-GATE-HQ01',
                'active'      => '1',
                'bypass'      => '0',
                'groups'      => 'Perimeter Firewalls',
                'man_ip'      => '127.0.0.1',
                'man_port'    => '443',
                'serial'      => 'SN-HQ-998811A',
                'api'         => 'https://127.0.0.1/api/v1',
                'location'    => '1', // HQ Data Center
                'platform'    => 'pfSense Plus',
                'notes'       => 'Primary edge firewall cluster node A.',
                'username'    => 'admin',
                'password'    => 'pfsense',
                'devicekey'   => 'devkey_demo_hq_primary_001'
            ],
            [
                'name'        => 'FW-BRANCH-BOSTON',
                'netgate_id'  => 'NET-GATE-BOS01',
                'active'      => '1',
                'bypass'      => '0',
                'groups'      => 'Branch Gateways',
                'man_ip'      => '192.168.10.1',
                'man_port'    => '443',
                'serial'      => 'SN-BOS-221144B',
                'api'         => 'https://192.168.10.1/api/v1',
                'location'    => '2', // Boston
                'platform'    => 'pfSense CE',
                'notes'       => 'Boston branch gateway with WireGuard tunnel.',
                'username'    => 'admin',
                'password'    => 'pfsense',
                'devicekey'   => 'devkey_demo_branch_bos_002'
            ]
        ];

        foreach ($devices as $dev) {
            if (!$inventoryModel->where('name', $dev['name'])->first()) {
                $inventoryModel->insert($dev);
            }
        }
    }
}
