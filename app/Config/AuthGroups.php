<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    public string $defaultGroup = 'user';

    public array $groups = [
  'admin' => 
  [
    'title' => 'Admin',
    'description' => 'Day to day administrators of the site.',
  ],
  'manager' => 
  [
    'title' => 'Manager',
    'description' => 'Manager administrators of the site.',
  ],
  'user' => 
  [
    'title' => 'User',
    'description' => 'General maker users of the site. Often customers.',
  ],
];

    public array $permissions = [
  'admin.access' => 'Can access the core settings and administration area',
  'users.manage' => 'Can manage users, roles, and access control',
  'devices.manage' => 'Can provision and edit device configurations',
  'devices.view' => 'Can view device monitoring and backups',
  'dashboard.view' => 'Can view the main dashboard',
];

    public array $matrix = [
  'admin' => [
    'admin.access',
    'users.manage',
    'devices.manage',
    'devices.view',
    'dashboard.view',
  ],
  'manager' => [
    'devices.view',
    'dashboard.view',
  ],
  'user' => [
    'dashboard.view',
  ],
];
}
