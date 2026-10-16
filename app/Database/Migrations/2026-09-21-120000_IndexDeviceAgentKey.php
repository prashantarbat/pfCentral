<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase 2: devices authenticate to /configpush/pickup with their key, so that lookup has to be
 * indexed. Unique because a device key identifies exactly one firewall — two rows claiming the
 * same key would mean one device could collect another's jobs.
 *
 * device_key is nullable so an enrolment row can exist before a key is assigned; MySQL permits
 * multiple NULLs in a unique index, which is what makes that workable.
 */
class IndexDeviceAgentKey extends Migration
{
    public function up()
    {
        $this->forge->addKey('device_key', false, true, 'uniq_device_agents_key');
        $this->forge->processIndexes('device_agents');
    }

    public function down()
    {
        $this->forge->dropKey('device_agents', 'uniq_device_agents_key', false);
    }
}
