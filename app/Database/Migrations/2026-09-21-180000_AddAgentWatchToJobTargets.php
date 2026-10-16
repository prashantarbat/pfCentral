<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Watching a device that pfCentral cannot reach: the device reads itself.
 *
 * Over SSH pfCentral reads the configuration for a window check. On the agent channel it cannot — the
 * whole point of that channel is that the branch opens the connection, not pfCentral — so the device
 * reads its own `/cf/conf/config.xml` and reports what it found. Two columns make that possible:
 *
 *   job_targets.watch_hash  sha256 of the configuration the device had *after* it applied the change
 *   job_targets.watch_via   'ssh' | 'agent' — who is expected to do the reading
 *
 * `watch_hash` is what turns the common case into a few hundred bytes instead of a megabyte: the device
 * hashes the file it is looking at, and when the hash still equals the one recorded at apply time then
 * nothing — not our change, and not anything else — has touched that file since. That is stronger
 * evidence than the convergence test it replaces, and it needs no upload at all. When the hash *differs*
 * the device sends the configuration and pfCentral runs the ordinary convergence check, so a later
 * unrelated change is still tolerated rather than reported as drift.
 *
 * `watch_via` records who was expected to read, rather than re-deriving it later from transport
 * reachability: the reason a window exists for a device is worth keeping, and a device whose SSH
 * credentials are removed next week should not silently rewrite the history of how it was watched.
 *
 * Null `watch_hash` is honest for every existing row: no change before this migration recorded the
 * configuration it installed, so those devices send a full read for their first window check and are
 * verified the ordinary way from then on.
 */
class AddAgentWatchToJobTargets extends Migration
{
    public function up()
    {
        $this->forge->addColumn('job_targets', [
            'watch_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'watch_checks',
            ],
            'watch_via' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'after'      => 'watch_hash',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('job_targets', ['watch_hash', 'watch_via']);
    }
}
