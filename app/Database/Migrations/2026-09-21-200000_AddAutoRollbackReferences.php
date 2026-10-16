<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Three pointers, all of them nullable, that let an automatic rollback be read back afterwards.
 *
 *   config_jobs.rollback_of_job_id   this job's payload *is* the pre-push snapshot of that job: the
 *                                    change is being undone rather than re-applied. A reconcile
 *                                    re-applies bytes a second approver already saw; a rollback puts
 *                                    the device back to what it was running before the push. Both are
 *                                    new jobs through the normal creation path, and this column is the
 *                                    only thing that tells them apart in the history.
 *   job_targets.rollback_job_id      the device this target was applied to has been handed to that
 *                                    rollback job. It is the once-only guard (a device is never
 *                                    rolled back twice for the same drift) and the reason a
 *                                    rolled-back device stops being offered as "drifted": the repair
 *                                    for a revert is offered to a *different* change, so a device we
 *                                    just put back must not be handed to a reconcile that would
 *                                    re-apply the very change it reverted.
 *   config_alerts.rollback_job_id    the alert that reported the revert is answered by that rollback
 *                                    job. Without it the email's "nothing was changed or rolled back
 *                                    by us" would be a lie the moment auto-rollback exists, and an
 *                                    operator reading the alert could not tell a handled revert from
 *                                    one still waiting for somebody to do something.
 *
 * No payload type is added for a rollback. Its payload is a complete config.xml — that is what a
 * pre-push snapshot *is* — so it is honestly an `xml` job: the same transports, the same
 * verification, the same deploy history, and the fragment/replace guard already in the merger. A
 * parallel payload type would have meant a second execution path that had to be kept in step with
 * the one that is already proven against real devices.
 */
class AddAutoRollbackReferences extends Migration
{
    public function up()
    {
        $this->forge->addColumn('config_jobs', [
            'rollback_of_job_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'reconcile_of_job_id',
            ],
        ]);

        $this->forge->addColumn('job_targets', [
            'rollback_job_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'backup_file',
            ],
        ]);

        $this->forge->addColumn('config_alerts', [
            'rollback_job_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'kind',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('config_jobs', 'rollback_of_job_id');
        $this->forge->dropColumn('job_targets', 'rollback_job_id');
        $this->forge->dropColumn('config_alerts', 'rollback_job_id');
    }
}
