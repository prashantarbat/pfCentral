<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Widens pf_inventory.password and pf_inventory.api columns to TEXT
 * to safely store base64-encoded encrypted ciphertexts at rest.
 */
class ExpandPfInventoryCredentialColumns extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('pf_inventory', [
            'password' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'api' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('pf_inventory', [
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => 256,
                'null'       => true,
            ],
            'api' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
            ],
        ]);
    }
}
