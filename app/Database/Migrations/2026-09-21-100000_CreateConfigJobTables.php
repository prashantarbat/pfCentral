<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase 1 of remote_config_architecture.md: the durable job queue that takes config
 * pushes out of the web request.
 *
 *   config_templates -> reusable, versioned payloads (optional; a job may carry a raw payload)
 *   config_jobs      -> one deployment request (who asked, what payload, which targets)
 *   job_targets      -> per-device fanout + state machine (the resumable part)
 *   device_agents    -> credentials for the Phase 2 agent transport
 *
 * Deviations from the blueprint, all additive:
 *   - config_jobs.batch_id ties the job to its config_push_logs rows (Phase 0 audit table).
 *   - job_targets gains a 'running' status so an interrupted attempt is distinguishable from
 *     a queued one and can be retried, plus backup_id/backup_file/error/duration_ms so the
 *     SSH transport's pre-push snapshot is linked to the queue row.
 *   - job_targets.config_before/config_after exist per the blueprint but are left NULL by the
 *     SSH transport, which keeps the artifact on disk in pf_backups instead of duplicating a
 *     full config.xml into the database for every device and every job.
 */
class CreateConfigJobTables extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------- templates
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

        // ---------------------------------------------------------------- jobs
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'batch_id' => ['type' => 'CHAR', 'constraint' => 36],
            'template_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            // Fully rendered for THIS job, so later template edits cannot rewrite history.
            'rendered_payload' => ['type' => 'MEDIUMTEXT'],
            'payload_type' => ['type' => 'ENUM', 'constraint' => ['xml', 'script'], 'default' => 'xml'],
            'target_spec' => ['type' => 'JSON', 'null' => true],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'pending', 'running', 'completed', 'failed', 'canceled'],
                'default'    => 'draft',
            ],
            'requested_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'approved_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'last_error' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'started_at' => ['type' => 'DATETIME', 'null' => true],
            'finished_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->createTable('config_jobs', true);

        // ---------------------------------------------------------------- targets
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'job_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'pf_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['queued', 'running', 'delivered', 'applied', 'failed', 'skipped', 'rolled_back'],
                'default'    => 'queued',
            ],
            'attempts' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 0],
            'delivered_at' => ['type' => 'DATETIME', 'null' => true],
            'applied_at' => ['type' => 'DATETIME', 'null' => true],
            'result_log' => ['type' => 'MEDIUMTEXT', 'null' => true],
            'config_before' => ['type' => 'LONGTEXT', 'null' => true],
            'config_after' => ['type' => 'LONGTEXT', 'null' => true],
            'backup_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'backup_file' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'error' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'duration_ms' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['job_id', 'pf_id'], 'uq_job_targets_device');
        $this->forge->addKey(['pf_id', 'status'], false, false, 'idx_job_targets_device_status');
        $this->forge->createTable('job_targets', true);

        // ---------------------------------------------------------------- agents
        $this->forge->addField([
            'pf_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'device_key' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'auth_token' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
            'agent_version' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('pf_id', true);
        $this->forge->createTable('device_agents', true);
    }

    public function down()
    {
        $this->forge->dropTable('device_agents', true);
        $this->forge->dropTable('job_targets', true);
        $this->forge->dropTable('config_jobs', true);
        $this->forge->dropTable('config_templates', true);
    }
}
