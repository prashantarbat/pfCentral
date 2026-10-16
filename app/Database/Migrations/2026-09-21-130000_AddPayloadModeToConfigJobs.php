<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Records whether a job's payload replaces a device's configuration or merges into it.
 *
 *   config_jobs.payload_mode   'replace' (a complete <pfsense> document) or 'merge' (a section).
 *
 * The mode is *derived from the payload* at validation time, never from a submitted flag, so the
 * recorded value can never disagree with the bytes that will be applied. Persisting it anyway buys
 * two things: the queue, the dashboard and the audit trail say what a job will do without having to
 * parse a 200 KB payload, and a future change to the inference rule cannot silently rewrite what an
 * old job did.
 *
 * Existing rows default to 'replace', which is exactly what they were: before this, every accepted
 * xml payload had to be a complete document.
 */
class AddPayloadModeToConfigJobs extends Migration
{
    public function up()
    {
        $this->forge->addColumn('config_jobs', [
            'payload_mode' => [
                'type'       => 'ENUM',
                'constraint' => ['replace', 'merge'],
                'default'    => 'replace',
                'after'      => 'payload_type',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('config_jobs', 'payload_mode');
    }
}
