<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Approval gate + canary rollout for the config job queue.
 *
 *   config_jobs.status        gains 'awaiting_approval' so a job that needs sign-off sits in a
 *                             state the runner will never pick up, instead of masquerading as
 *                             'pending' and relying on a query filter being written correctly.
 *   requires_approval         records what the rule demanded at creation time, so later rule
 *                             changes do not retroactively rewrite a job's audit trail.
 *   approved_at               complements the existing approved_by.
 *   canary_pf_id              the single device a job must succeed on before fanning out.
 *   job_targets.is_canary     marks that row in the UI.
 */
class AddApprovalAndCanaryToConfigJobs extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('config_jobs', [
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'awaiting_approval', 'pending', 'running', 'completed', 'failed', 'canceled'],
                'default'    => 'draft',
            ],
        ]);

        $this->forge->addColumn('config_jobs', [
            'requires_approval' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'status',
            ],
            'approved_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'approved_by',
            ],
            'canary_pf_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'approved_at',
            ],
        ]);

        $this->forge->addColumn('job_targets', [
            'is_canary' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'after'      => 'status',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('job_targets', 'is_canary');
        $this->forge->dropColumn('config_jobs', ['canary_pf_id', 'approved_at', 'requires_approval']);

        $this->forge->modifyColumn('config_jobs', [
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'pending', 'running', 'completed', 'failed', 'canceled'],
                'default'    => 'draft',
            ],
        ]);
    }
}
