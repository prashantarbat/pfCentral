<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * When an alert's email becomes due — so the loudest alert can wait for the pattern to form.
 *
 * The fleet-pattern alert (Phase 3.11) removed the per-branch emails *once the pattern existed*, which is
 * too late to remove them: the branches that made the pattern recognisable had already been emailed, one
 * at a time, minutes before the third one arrived. Four branches undoing one change produced three
 * messages — two about devices that turned out to be part of a single fact about the change.
 *
 * So a revert's email is *deferred* rather than sent on the spot. The alert is still raised, still visible
 * and still loud; only the sending waits, for one read cycle (the window's first check is five minutes and
 * the sweep runs every five), which is exactly the interval in which a second and third device are read.
 * If they are, their alerts are grouped into the pattern and none of them is emailed individually: the
 * change produces one message. If they are not, the deferred email goes out and the single-branch revert is
 * reported as it always was, a few minutes later.
 *
 *   notify_after   when this row's email may be sent. NULL means \"as soon as possible\", which is what
 *                  every alert written before this column existed *was* — so the column needs no backfill
 *                  and no existing row changes behaviour.
 *
 * A drift is not deferred. It is not a member (a pattern is about devices that undid a change while it was
 * being watched), nothing will group it, and waiting would buy nothing but latency.
 */
class AddNotifyAfterToConfigAlerts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('config_alerts', [
            'notify_after' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'notify_error',
            ],
        ]);
    }

    public function down()
    {
        // Nothing becomes unsendable: a row whose deferral is dropped simply becomes due at once, and the
        // next sweep sends whatever is still owed.
        $this->forge->dropColumn('config_alerts', 'notify_after');
    }
}
