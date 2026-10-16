<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drift alerts: what tells a human that a device stopped matching a change we made.
 *
 * The verification layer already *records* drift on the target (`job_targets.verify_status`). That is
 * the right place for the fact, but it is a silent one — nobody opens a job's detail table on the
 * off-chance that something reverted overnight. This table is the alert: a durable, visible record
 * that also carries the state of the attempt to email somebody about it, so a mail outage can never
 * lose the alert and a flapping device can never spam the inbox.
 *
 *   status           open | resolved
 *   fingerprint      hash of the drift detail, so the same drift alerts once and a *different*
 *                    drift alerts again
 *   notify_attempts  email attempts made, so a mail outage is retried but not forever
 *   notified_at      when an email actually went out (null = still owed)
 *   notify_error     why the last attempt failed, kept for the operator
 *   resolved_by      'verification' when the device matched again, a username when a human dismissed
 *                    it — a dismissal is recorded rather than silent
 */
class CreateConfigAlerts extends Migration
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
            'job_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'target_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'pf_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'fingerprint' => [
                'type'       => 'CHAR',
                'constraint' => 40,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['open', 'resolved'],
                'default'    => 'open',
            ],
            'summary' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'detail' => [
                'type'       => 'VARCHAR',
                'constraint' => 600,
                'null'       => true,
            ],
            'raised_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'notify_attempts' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'notified_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'notified_to' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'notify_error' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'resolved_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'resolved_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'resolution' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
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
        // The list the UI shows and the notification sweep both start from "open, oldest first".
        $this->forge->addKey(['status', 'raised_at']);
        $this->forge->addKey('target_id');
        $this->forge->createTable('config_alerts', true);
    }

    public function down()
    {
        $this->forge->dropTable('config_alerts', true);
    }
}
