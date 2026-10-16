<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Post-apply verification: did the change actually stick?
 *
 *   job_targets.verify_status    pending | matched | drifted | unavailable | not_applicable
 *   job_targets.verified_at      when the device's configuration was last checked
 *   job_targets.verify_detail    why, in the operator's words (a short summary, not a config dump)
 *   job_targets.verify_attempts  how many times the device could not be reached to check
 *
 * Deliberately separate from `status`: 'applied' means the write landed, verification asks whether
 * the device *still* looks like that. A drifted target stays 'applied' — the push succeeded — so
 * nothing here silently rewrites the outcome of a job that did what it was asked.
 *
 * Default 'pending' is correct for existing rows: every target applied before this migration was
 * never verified, and claiming otherwise would be inventing history.
 */
class AddVerificationToJobTargets extends Migration
{
    public function up()
    {
        $this->forge->addColumn('job_targets', [
            'verify_status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'matched', 'drifted', 'unavailable', 'not_applicable'],
                'default'    => 'pending',
                'after'      => 'error',
            ],
            'verified_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'verify_status',
            ],
            'verify_detail' => [
                'type'       => 'VARCHAR',
                'constraint' => 600,
                'null'       => true,
                'after'      => 'verified_at',
            ],
            'verify_attempts' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'default'    => 0,
                'after'      => 'verify_detail',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('job_targets', ['verify_status', 'verified_at', 'verify_detail', 'verify_attempts']);
    }
}
