<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CoreConfigModel;

class RolesController extends BaseController
{
    protected $CoreConfigModel;

    public function __construct()
    {
        helper('form');
        $this->CoreConfigModel = new CoreConfigModel();
    }

    public function index()
    {
        $user = auth()->user();
        
        // Only allow users with admin access
        if (!$user || !$user->can('admin.access')) {
            return redirect()->to('/dashboard')->with('error', 'Unauthorized access.');
        }

        $data['first_name'] = $user->first_name ?? 'Not Login';
        
        // Fetch app_name and version from the core_config table
        $appNameConfig = $this->CoreConfigModel->where('name', 'app_name')->first();
        $appName = $appNameConfig['value'] ?? 'My Application';
        $data['app_name'] = $appName;
        
        $appVersionConfig = $this->CoreConfigModel->where('name', 'version')->first();
        $appVersion = $appVersionConfig['value'] ?? '1.0.0';
        $data['app_version'] = $appVersion;
        
        $data['title'] = strip_tags($appName) . ' 🔸 Access Roles & Permissions';
        
        // Get Shield groups and permissions
        $authGroups = config('AuthGroups');
        $data['groups'] = $authGroups->groups;
        $data['permissions'] = $authGroups->permissions;
        $data['matrix'] = $authGroups->matrix;

        // Count members per group
        $db = db_connect();
        $memberCounts = [];
        $rows = $db->table('auth_groups_users')->select('group, count(*) as cnt')->groupBy('group')->get()->getResultArray();
        foreach ($rows as $r) {
            $memberCounts[$r['group']] = (int) $r['cnt'];
        }
        $data['memberCounts'] = $memberCounts;

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('Auth/Roles/index', $data)
            . view('frontend/footer', $data);
    }

    public function addGroup()
    {
        $key = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $this->request->getPost('group_key')));
        $title = $this->request->getPost('group_title');
        $desc = $this->request->getPost('group_desc');

        if (empty($key) || empty($title)) return redirect()->back()->with('error', 'Key and Title are required.');

        $config = config('AuthGroups');
        $groups = $config->groups;
        $permissions = $config->permissions;
        $matrix = $config->matrix;

        if (isset($groups[$key])) return redirect()->back()->with('error', 'Group already exists.');

        $groups[$key] = [
            'title' => $title,
            'description' => $desc,
        ];
        $matrix[$key] = [];

        $this->rewriteAuthGroupsConfig($config->defaultGroup, $groups, $permissions, $matrix);
        return redirect()->back()->with('message', 'Group added successfully.');
    }

    public function deleteGroup()
    {
        $key = $this->request->getPost('group_key');
        if ($key === 'admin' || $key === 'user') return redirect()->back()->with('error', 'Cannot delete core groups.');

        $config = config('AuthGroups');
        $groups = $config->groups;
        $permissions = $config->permissions;
        $matrix = $config->matrix;

        unset($groups[$key]);
        unset($matrix[$key]);

        $this->rewriteAuthGroupsConfig($config->defaultGroup, $groups, $permissions, $matrix);
        return redirect()->back()->with('message', 'Group deleted successfully.');
    }

    public function addPermission()
    {
        $key = strtolower(preg_replace('/[^a-zA-Z0-9_.]/', '', $this->request->getPost('perm_key')));
        $desc = $this->request->getPost('perm_desc');

        if (empty($key) || empty($desc)) return redirect()->back()->with('error', 'Key and Description are required.');

        $config = config('AuthGroups');
        $groups = $config->groups;
        $permissions = $config->permissions;
        $matrix = $config->matrix;

        if (isset($permissions[$key])) return redirect()->back()->with('error', 'Permission already exists.');

        $permissions[$key] = $desc;

        $this->rewriteAuthGroupsConfig($config->defaultGroup, $groups, $permissions, $matrix);
        return redirect()->back()->with('message', 'Permission added successfully.');
    }

    public function deletePermission()
    {
        $key = $this->request->getPost('perm_key');

        $config = config('AuthGroups');
        $groups = $config->groups;
        $permissions = $config->permissions;
        $matrix = $config->matrix;

        unset($permissions[$key]);
        foreach ($matrix as $grp => $perms) {
            $matrix[$grp] = array_values(array_diff($perms, [$key]));
        }

        $this->rewriteAuthGroupsConfig($config->defaultGroup, $groups, $permissions, $matrix);
        return redirect()->back()->with('message', 'Permission deleted successfully.');
    }

    public function updateMatrix()
    {
        $matrixData = $this->request->getPost('matrix');

        $config = config('AuthGroups');
        $groups = $config->groups;
        $permissions = $config->permissions;
        
        $newMatrix = [];
        foreach ($groups as $gKey => $gVal) {
            $newMatrix[$gKey] = isset($matrixData[$gKey]) ? array_keys($matrixData[$gKey]) : [];
        }

        $this->rewriteAuthGroupsConfig($config->defaultGroup, $groups, $permissions, $newMatrix);
        return redirect()->back()->with('message', 'Permissions matrix updated successfully.');
    }

    protected function rewriteAuthGroupsConfig($defaultGroup, $groups, $permissions, $matrix)
    {
        $exportGroups = var_export($groups, true);
        $exportPerms = var_export($permissions, true);
        $exportMatrix = var_export($matrix, true);

        // Clean up array syntax for modern PHP
        $exportGroups = str_replace(['array (', ')'], ['[', ']'], $exportGroups);
        $exportPerms = str_replace(['array (', ')'], ['[', ']'], $exportPerms);
        $exportMatrix = str_replace(['array (', ')'], ['[', ']'], $exportMatrix);

        $fileContent = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Config;\n\nuse CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;\n\nclass AuthGroups extends ShieldAuthGroups\n{\n";
        $fileContent .= "    public string \$defaultGroup = '{$defaultGroup}';\n\n";
        $fileContent .= "    public array \$groups = {$exportGroups};\n\n";
        $fileContent .= "    public array \$permissions = {$exportPerms};\n\n";
        $fileContent .= "    public array \$matrix = {$exportMatrix};\n";
        $fileContent .= "}\n";

        file_put_contents(APPPATH . 'Config/AuthGroups.php', $fileContent);
    }
}
