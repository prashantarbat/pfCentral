<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Durable record of every per-device config push attempt.
 *
 * Phase 0 of remote_config_architecture.md: results previously existed only in the
 * browser's NDJSON stream, so a failed push left no trace once the page was closed.
 */
class CreateConfigPushLogs extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // Groups every device row belonging to one "Deploy" click.
            'batch_id' => [
                'type'       => 'CHAR',
                'constraint' => 36,
            ],
            'pf_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'ip' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'target_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'payload_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'started',
            ],
            'message' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'log' => [
                'type' => 'MEDIUMTEXT',
                'null' => true,
            ],
            // Link to the pre-push safety backup written to pf_backups.
            'backup_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'backup_file' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'duration_ms' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('batch_id');
        $this->forge->addKey('pf_id');
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->createTable('config_push_logs', true); // true = IF NOT EXISTS
    }

    public function down()
    {
        $this->forge->dropTable('config_push_logs', true);
    }
}
