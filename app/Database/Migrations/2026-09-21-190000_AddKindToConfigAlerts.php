<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Not every drift is the same event, and treating them alike is how the urgent one gets read as routine.
 *
 *   revert  the change was found undone *while the verification window was still watching it* — minutes
 *           after it was applied. Something on the device or at the branch put it back: a package sync,
 *           a rotating config restore, a local script, a person who did not know a push had landed. It
 *           is the same hour the push reported success in, which makes it the loudest kind of failure
 *           this system has.
 *   drift   the device stopped matching a change applied long enough ago that the window had closed.
 *           Real, and worth alerting, but it is a later change rather than a device undoing what it was
 *           just told to do.
 *
 * The distinction is stored rather than re-derived at read time, because it depends on *when the drift
 * was found* — and `watch_until` is rewritten when a window is used again, so a later read could not
 * reconstruct it honestly. Existing rows are `drift`: none of them was classified, and claiming
 * otherwise after the fact would be inventing evidence.
 *
 * The kind escalates and never downgrades (see `ConfigAlertService::escalate()`): once a difference is
 * known to have been a revert, a later sighting of the same difference cannot make it quieter.
 */
class AddKindToConfigAlerts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('config_alerts', [
            'kind' => [
                'type'       => 'ENUM',
                'constraint' => ['drift', 'revert'],
                'default'    => 'drift',
                'after'      => 'detail',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('config_alerts', 'kind');
    }
}
