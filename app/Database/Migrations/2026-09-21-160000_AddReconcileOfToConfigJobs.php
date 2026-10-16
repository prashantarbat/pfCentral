<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Links a reconcile job to the job it is repairing.
 *
 * A reconcile is a *new* job rather than a re-queue inside the old one, so that the original keeps an
 * honest history ("it applied everywhere; three devices later drifted" is a fact worth keeping) and so
 * the repair has its own approval record, canary, progress and outcome. That leaves one thing to
 * record: which job this one exists to fix.
 *
 * Two behaviours depend on it, and both would be guesses without it:
 *
 *  - a duplicate guard, so a double-click or two operators cannot queue two reconciles for the same job;
 *  - clearing the *source* target's drift only once the reconcile has been verified — the drift stays
 *    visible until a real read agrees it is gone.
 */
class AddReconcileOfToConfigJobs extends Migration
{
    public function up()
    {
        $this->forge->addColumn('config_jobs', [
            'reconcile_of_job_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'canary_pf_id',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('config_jobs', 'reconcile_of_job_id');
    }
}
