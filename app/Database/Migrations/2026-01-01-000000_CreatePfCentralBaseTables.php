<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePfCentralBaseTables extends Migration
{
    public function up()
    {
        // 1. app_groups
        if (!$this->db->tableExists('app_groups')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'description' => ['type' => 'TEXT', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('app_groups', true);
        }

        // 2. app_settings
        if (!$this->db->tableExists('app_settings')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'appname' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'pfCentral'],
                'tagline' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Centralized pfSense Fleet Management'],
                'aapurl' => ['type' => 'VARCHAR', 'constraint' => 512, 'default' => 'http://localhost:8080'],
                'pri_color' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => '#0d6efd'],
                'sec_color' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => '#6c757d'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('app_settings', true);
        }

        // 3. core_config
        if (!$this->db->tableExists('core_config')) {
            $this->forge->addField([
                'name' => ['type' => 'VARCHAR', 'constraint' => 128],
                'value' => ['type' => 'VARCHAR', 'constraint' => 512],
            ]);
            $this->forge->addKey('name', true);
            $this->forge->createTable('core_config', true);
        }

        // 4. alert_log
        if (!$this->db->tableExists('alert_log')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'branch_name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'last_status' => ['type' => 'ENUM', 'constraint' => ['UP', 'DOWN'], 'default' => 'UP'],
                'last_alert_sent' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('branch_name');
            $this->forge->createTable('alert_log', true);
        }

        // 5. settings
        if (!$this->db->tableExists('settings')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'class' => ['type' => 'VARCHAR', 'constraint' => 255],
                'key' => ['type' => 'VARCHAR', 'constraint' => 255],
                'value' => ['type' => 'TEXT', 'null' => true],
                'type' => ['type' => 'VARCHAR', 'constraint' => 31, 'default' => 'string'],
                'context' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('settings', true);
        }

        // 6. pf_locations
        if (!$this->db->tableExists('pf_locations')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 256],
                'ip' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'wg_mpls' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'wg_ill' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'wg_bdr' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => ''],
                'address' => ['type' => 'VARCHAR', 'constraint' => 512, 'default' => ''],
                'email' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
                'phone' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => ''],
                'latitude' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
                'longitude' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
                'groups' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('pf_locations', true);
        }

        // 7. pf_inventory
        if (!$this->db->tableExists('pf_inventory')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 256, 'null' => true],
                'netgate_id' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
                'serial' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'bypass' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'man_ip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'man_port' => ['type' => 'VARCHAR', 'constraint' => 6, 'null' => true],
                'api' => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
                'location' => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
                'tags' => ['type' => 'VARCHAR', 'constraint' => 512, 'null' => true],
                'groups' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'platform' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'last_checked' => ['type' => 'TIMESTAMP', 'null' => true],
                'username' => ['type' => 'VARCHAR', 'constraint' => 256, 'null' => true],
                'password' => ['type' => 'VARCHAR', 'constraint' => 256, 'null' => true],
                'devicekey' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('pf_inventory', true);
        }

        // 8. pf_status
        if (!$this->db->tableExists('pf_status')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'pf_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'ip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'port' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'ssh_port' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'mpls_ip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ill_ip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'wan_ip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'lightsquid_port' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'darkstat_port' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'ntop_port' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'bandwidthd' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'restapi' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
                'os_name' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'os_version' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'hostname' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'build_identifier' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'uptime_duration' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'users' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'load_averages' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ping_latency' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'cpu_usage' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'total_memory' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'available_memory' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'disk_usage' => ['type' => 'TEXT', 'null' => true],
                'active_tunnel' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('pf_id');
            $this->forge->createTable('pf_status', true);
        }

        // 9. pf_devices
        if (!$this->db->tableExists('pf_devices')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'hostname' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
                'br_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'priv_key' => ['type' => 'TEXT', 'null' => true],
                'pub_key' => ['type' => 'TEXT', 'null' => true],
                'lan_ip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'lan_subnet' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'mpls_ip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'mpls_gw' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'mpls_ip_add' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'mpls_wgip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'mpls_port' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'ill_iface' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ill_ip' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'ill_port' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
                'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Firewall Device'],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('pf_devices', true);
        }

        // 10. pf_backups
        if (!$this->db->tableExists('pf_backups')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                'pf_id' => ['type' => 'INT', 'constraint' => 11],
                'file_name' => ['type' => 'VARCHAR', 'constraint' => 255],
                'file_path' => ['type' => 'TEXT'],
                'quantity' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                'created_at' => ['type' => 'TIMESTAMP'],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('pf_id');
            $this->forge->createTable('pf_backups', true);
        }

        // 11. wg_bdr, wg_ill, wg_mpls
        $wgTables = ['wg_bdr', 'wg_ill', 'wg_mpls'];
        foreach ($wgTables as $wgTable) {
            if (!$this->db->tableExists($wgTable)) {
                $this->forge->addField([
                    'id' => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
                    'location_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
                    'interface' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                    'status' => ['type' => 'TINYINT', 'null' => true],
                    'flap_count' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                    'created_at' => ['type' => 'DATETIME', 'null' => true],
                    'updated_at' => ['type' => 'DATETIME', 'null' => true],
                    'deleted_at' => ['type' => 'DATETIME', 'null' => true],
                ]);
                $this->forge->addKey('id', true);
                $this->forge->createTable($wgTable, true);
            }
        }

        // 12. config_templates
        if (!$this->db->tableExists('config_templates')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 120],
                'scope' => ['type' => 'ENUM', 'constraint' => ['full_xml', 'fragment', 'script'], 'default' => 'fragment'],
                'payload' => ['type' => 'MEDIUMTEXT'],
                'schema_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'created_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('name', 'uq_config_templates_name');
            $this->forge->createTable('config_templates', true);
        }

        // 13. Extend users table if exists
        if ($this->db->tableExists('users')) {
            $fieldsToAdd = [];
            $cols = $this->db->getFieldNames('users');
            $extra = [
                'first_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'middle_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'last_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'mobile_number' => ['type' => 'VARCHAR', 'constraint' => 15, 'default' => ''],
                'location' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'department' => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
                'is_ad' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            ];
            foreach ($extra as $name => $def) {
                if (!in_array($name, $cols)) {
                    $fieldsToAdd[$name] = $def;
                }
            }
            if (!empty($fieldsToAdd)) {
                $this->forge->addColumn('users', $fieldsToAdd);
            }
        }
    }

    public function down()
    {
        $tables = [
            'config_templates',
            'wg_mpls',
            'wg_ill',
            'wg_bdr',
            'pf_backups',
            'pf_devices',
            'pf_status',
            'pf_inventory',
            'pf_locations',
            'settings',
            'alert_log',
            'core_config',
            'app_settings',
            'app_groups'
        ];
        foreach ($tables as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
