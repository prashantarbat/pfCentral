<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The verification window becomes a property of the change, not of the system.
 *
 * One schedule for every push is the wrong shape for both ends of the risk spectrum. A cosmetic change —
 * a description, an alias, a hostname — is watched for an hour nobody needs, and a change that reroutes
 * traffic or rewrites a VPN is unwatched after that same hour, even though the failure it should catch
 * (a nightly restore, a weekly package sync, an engineer who "fixes" it back on Tuesday) happens long
 * after the window closed.
 *
 * Two columns, and the second is the one that matters:
 *
 *   watch_profile   the choice, for the operator's eye: none | once | standard | extended.
 *   watch_schedule  the minute offsets that choice resolved to, frozen on the job at creation.
 *
 * The schedule is stored rather than derived at read time because `watch_checks` *indexes into it*: a
 * window is a walk through a list, and if editing a profile's slots changed how an in-flight window is
 * interpreted, a job half-way through its first hour would silently be walked through a different list.
 * Freezing it also means the row answers "what was this change watched for?" without depending on a
 * constant that will be edited again.
 *
 * Neither column is nullable-by-accident: `watch_schedule` null means *no window* (a script has no
 * configuration to compare, and `none` is a deliberate choice), which is different from an older job that
 * predates this migration — those are read through the default profile, which is exactly the schedule they
 * were created under.
 */
class AddWatchProfileToConfigJobs extends Migration
{
    public function up()
    {
        $this->forge->addColumn('config_jobs', [
            'watch_profile' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'payload_mode',
            ],
            'watch_schedule' => [
                'type'  => 'JSON',
                'null'  => true,
                'after' => 'watch_profile',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('config_jobs', 'watch_schedule');
        $this->forge->dropColumn('config_jobs', 'watch_profile');
    }
}
