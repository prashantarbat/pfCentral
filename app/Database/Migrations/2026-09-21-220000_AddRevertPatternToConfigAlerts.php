<?php

namespace App\Database\Migrations;

use App\Models\ConfigAlertModel;
use CodeIgniter\Database\Migration;

/**
 * The fleet-pattern alert: several branches undoing *the same change* is one story, not N.
 *
 * A single device undoing a change minutes after a push is a branch problem — something on that box
 * rewrote `config.xml`. The same change coming undone on three, four or twelve branches inside the window
 * it asked for is a different fact entirely: the *change* is wrong, or fights something every branch runs
 * (a package sync that rewrites the section, a service that refuses to start and gets restored, a config
 * that survives a reload everywhere). The operator who reads twelve separate alerts at 03:00 has to work
 * that out from twelve rows; the one who reads "one change undone on twelve devices" already knows.
 *
 *   grouped_into   on a *member* row: the pattern alert that speaks for it. A grouped member is still a
 *                  real alert — it keeps its own device, fingerprint, rollback and resolution — it is just
 *                  not listed separately on the banner and its email is not sent one per device. Clearing
 *                  the pointer is never needed: a member only stops being grouped by being resolved, and
 *                  the pattern closes when its last member does.
 *   device_count   on the *pattern* row: how many devices are in it right now. Kept current as members
 *                  join and are repaired, which is the number the banner, the nav badge and the email all
 *                  quote — the count an operator acts on cannot be the count from when the alert was first
 *                  written.
 *
 * No new table: a pattern is an alert like any other, with the same open/resolved lifecycle, the same
 * email retry state and the same banner. The kind (`revert_pattern`) is what makes it read differently.
 *
 * That kind also has to be added to the `kind` column itself, which is an ENUM: MySQL stores a value it
 * does not know as `''` with a warning rather than failing, so a pattern row inserted without widening the
 * column would be written as an alert of no kind at all — visible on the banner, invisible to every
 * `isRevert()`/`isPattern()` test, and impossible to find by reading the code that inserted it.
 */
class AddRevertPatternToConfigAlerts extends Migration
{
    public function up()
    {
        $this->forge->addColumn('config_alerts', [
            'grouped_into' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'rollback_job_id',
            ],
            'device_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'grouped_into',
            ],
        ]);

        $this->forge->modifyColumn('config_alerts', [
            'kind' => [
                'type'       => 'ENUM',
                'constraint' => [ConfigAlertModel::KIND_DRIFT, ConfigAlertModel::KIND_REVERT, ConfigAlertModel::KIND_REVERT_PATTERN],
                'default'    => ConfigAlertModel::KIND_DRIFT,
            ],
        ]);

        // Finding one job's open reverts is the query the pattern runs on every revert and every
        // resolution, so it gets an index rather than a scan of the alert table.
        $this->forge->addKey(['job_id', 'status'], false, false, 'config_alerts_job_status');
        $this->forge->processIndexes('config_alerts');
    }

    public function down()
    {
        $this->forge->dropKey('config_alerts', 'config_alerts_job_status', false);
        $this->forge->dropColumn('config_alerts', ['grouped_into', 'device_count']);

        // Any pattern still open would be written as `''` by this narrower enum, so they are closed
        // first: a rollback of this migration must not leave alerts nobody can classify.
        db_connect()->table('config_alerts')
            ->where('kind', ConfigAlertModel::KIND_REVERT_PATTERN)
            ->update(['kind' => ConfigAlertModel::KIND_REVERT]);

        $this->forge->modifyColumn('config_alerts', [
            'kind' => [
                'type'       => 'ENUM',
                'constraint' => [ConfigAlertModel::KIND_DRIFT, ConfigAlertModel::KIND_REVERT],
                'default'    => ConfigAlertModel::KIND_DRIFT,
            ],
        ]);
    }
}
