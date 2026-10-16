<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ensures pf_inventory.active defaults to 1 (active) so auto-discovered and newly
 * registered branch firewalls are never left in an inactive state.
 * Also cleans up leading/trailing whitespace in device names.
 */
class DefaultPfInventoryActiveToOne extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('pf_inventory', [
            'active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
                'null'       => false,
            ],
        ]);

        // Clean up whitespace in device names and activate any named firewalls
        $this->db->query("UPDATE pf_inventory SET name = TRIM(name) WHERE name IS NOT NULL");
        $this->db->query("UPDATE pf_inventory SET active = 1 WHERE active = 0 AND name IS NOT NULL AND TRIM(name) != ''");
    }

    public function down()
    {
        $this->forge->modifyColumn('pf_inventory', [
            'active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
            ],
        ]);
    }
}
