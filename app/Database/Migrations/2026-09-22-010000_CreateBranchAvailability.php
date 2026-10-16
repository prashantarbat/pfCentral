<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Per-branch availability: what each site's link is doing, and every time it stopped doing it.
 *
 * Nothing in this system recorded history. `wg_ill` / `wg_bdr` / `wg_mpls` hold a *current* status and a
 * flap count, `pf_status` holds the last check-in, and `alert_log` holds only whether an SMS has already
 * gone out — so "was Yavatmal down last Tuesday, and for how long?" had no answer, and neither did
 * "did the outage start four minutes after we pushed that change?".
 *
 * Two tables, because a report needs both a current answer and a history:
 *
 *   branch_availability_state   one row per branch: what we last saw, since when, and the counters that
 *                               keep one dropped packet from being reported as an outage.
 *   branch_availability_events  one row per *outage*: when it started, when it ended, how it was
 *                               detected, and — when it lines up — the config job that was applied to
 *                               that device just before it.
 *
 * Uptime is computed from the events rather than from a sampled series. A five-minute sample per branch
 * would be eleven thousand rows a day to say "nothing happened"; an outage row is written when something
 * actually changed, which is both smaller and *more* precise — the boundary of the outage is the
 * observation that found it, not a bucket it happened to fall into.
 *
 * `failing_since` is the column that makes that precision honest: two consecutive failed probes are
 * needed before a branch is called down (one timeout is a switch doing something), so the outage starts
 * at the *first* failure rather than at the second one that confirmed it.
 *
 * `kind` separates what was measured from what was inferred. `unreachable` is a probe that failed;
 * `silent` is a branch with no address we can probe that stopped checking in — the only evidence
 * available for it, and labelled as such so nobody reads an inference as a measurement.
 */
class CreateBranchAvailability extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------- current state
        $this->forge->addField([
            'pf_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'device_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'location_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'state' => [
                'type'       => 'ENUM',
                'constraint' => ['up', 'down', 'unknown'],
                'default'    => 'unknown',
            ],
            // When the current state began: the start of this up period, or of this outage.
            'since' => ['type' => 'DATETIME', 'null' => true],
            // When we first started watching at all. An uptime figure is meaningless before this, and the
            // report says "not observed" instead of a flattering 100%.
            'monitored_since' => ['type' => 'DATETIME', 'null' => true],
            'last_probe_at' => ['type' => 'DATETIME', 'null' => true],
            'last_probe_ok' => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'last_probe_ms' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'last_probe_target' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            // The first failure of the current run: the outage starts here, not at the probe that
            // confirmed it.
            'failing_since' => ['type' => 'DATETIME', 'null' => true],
            'consecutive_failures' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'consecutive_successes' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            // The device's own last check-in (from pf_status): a branch can be reachable and silent, which
            // is a different problem from being unreachable.
            'last_checkin_at' => ['type' => 'DATETIME', 'null' => true],
            'source' => [
                'type'       => 'ENUM',
                'constraint' => ['probe', 'agent', 'manual'],
                'default'    => 'probe',
            ],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('pf_id', true);
        $this->forge->addKey('state', false, false, 'idx_branch_availability_state_state');
        $this->forge->createTable('branch_availability_state', true);

        // ---------------------------------------------------------------- outages
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'pf_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'device_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'location_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'kind' => [
                'type'       => 'ENUM',
                'constraint' => ['unreachable', 'silent'],
                'default'    => 'unreachable',
            ],
            'started_at' => ['type' => 'DATETIME'],
            // NULL while the outage is open: the branch is down right now.
            'ended_at' => ['type' => 'DATETIME', 'null' => true],
            'duration_seconds' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'evidence' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            // The change that was applied to this device shortly before the outage began, when there is one.
            'job_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'job_gap_minutes' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['pf_id', 'started_at'], false, false, 'idx_branch_availability_events_device');
        $this->forge->addKey('started_at', false, false, 'idx_branch_availability_events_started');
        // One open outage per branch: the service reads this key to close one instead of opening a second.
        $this->forge->addKey(['pf_id', 'ended_at'], false, false, 'idx_branch_availability_events_open');
        $this->forge->createTable('branch_availability_events', true);
    }

    public function down()
    {
        $this->forge->dropTable('branch_availability_events', true);
        $this->forge->dropTable('branch_availability_state', true);
    }
}
