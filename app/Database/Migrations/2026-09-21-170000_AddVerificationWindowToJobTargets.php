<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The verification window: read a device back several times in the hour after a change.
 *
 * A single read-back straight after a push proves the write landed, and the hourly sweep proves the
 * change is still there *an hour later* — which is exactly the window in which the failure this
 * feature exists for is invisible. pfSense rewrites config.xml on its next package sync, a branch
 * engineer restores an older configuration, a script runs a minute after the push: all of those undo
 * a change within minutes, and all of them used to go unnoticed until the next sweep or until someone
 * noticed the firewall was wrong.
 *
 *   job_targets.watch_started_at  when this device entered its window (null: never did)
 *   job_targets.watch_until       when the window closes (applied_at + the last slot)
 *   job_targets.watch_next_at     when the next re-check is due (null: nothing outstanding)
 *   job_targets.watch_checks      how many window checks have already run
 *
 * The schedule itself lives in ConfigVerificationService, not here: these columns only say *when*,
 * and `watch_checks` indexes into the schedule, so changing the cadence does not need a migration.
 *
 * Null `watch_next_at` is the honest default for existing rows: nothing before this migration was ever
 * watched, and back-filling a window for changes applied last week would mean re-checking history
 * rather than catching a revert.
 */
class AddVerificationWindowToJobTargets extends Migration
{
    public function up()
    {
        $this->forge->addColumn('job_targets', [
            'watch_started_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'verify_attempts',
            ],
            'watch_until' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'watch_started_at',
            ],
            'watch_next_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'watch_until',
            ],
            'watch_checks' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'default'    => 0,
                'after'      => 'watch_next_at',
            ],
        ]);

        // The watch runner's only query is "what is due", and it runs every few minutes over every job
        // ever applied — so the due column is worth an index even though the table is small today.
        foreach ($this->db->getIndexData('job_targets') as $index) {
            if ($index->name === 'idx_job_targets_watch_next') {
                return;
            }
        }

        $this->db->query('CREATE INDEX idx_job_targets_watch_next ON job_targets (status, watch_next_at)');
    }

    public function down()
    {
        foreach ($this->db->getIndexData('job_targets') as $index) {
            if ($index->name === 'idx_job_targets_watch_next') {
                $this->db->query('DROP INDEX idx_job_targets_watch_next ON job_targets');
            }
        }

        $this->forge->dropColumn('job_targets', ['watch_started_at', 'watch_until', 'watch_next_at', 'watch_checks']);
    }
}
