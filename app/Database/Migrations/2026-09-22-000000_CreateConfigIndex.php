<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fleet-wide configuration search: the facts of every device's configuration, extracted once.
 *
 * Searching the stored `config.xml` files directly would mean parsing a few hundred kilobytes of XML per
 * device on every keystroke — the operator waits, and the answer gets worse the more devices are added.
 * So the searchable substance is extracted ahead of time, by a command, into rows that a query can use:
 *
 *   config_index        one row per *leaf value* in a stored configuration, with enough context to be
 *                       read on its own (section, XML path, a human label, and whether the value is a
 *                       credential).
 *   config_index_state  one row per device: which snapshot was indexed, when, and — when it could not
 *                       be — *why*. This is the part that keeps the feature honest: a device whose
 *                       snapshot is missing or unreadable must be visibly absent from a search, not
 *                       silently indistinguishable from one that genuinely has no match.
 *
 * `value_norm` (lower-cased, whitespace-collapsed, truncated for the index) is what a contains-search
 * scans; `value_hash` is the exact-match fast path, because the common question here — "which devices
 * still point at 10.20.0.1?" — is an equality, and an equality can use an index.
 *
 * The index holds values, including secrets (an IPsec pre-shared key, an SNMP community): finding every
 * device that shares a leaked credential is one of the reasons to build this at all. `is_secret` marks
 * them so the page masks them by default rather than printing them into a screenshot.
 */
class CreateConfigIndex extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------- extracted facts
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'pf_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'device_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            // The root element the value lives under (`interfaces`, `filter`, `snmpd`, ...). Machine
            // value; the operator-facing name comes from ConfigSearchService::sectionLabel().
            'section' => ['type' => 'VARCHAR', 'constraint' => 60],
            // `interfaces/wan/ipaddr`, with `[n]` for repeated siblings so a path is unique and readable.
            'xml_path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'label' => ['type' => 'VARCHAR', 'constraint' => 160],
            'value_text' => ['type' => 'TEXT'],
            'value_norm' => ['type' => 'VARCHAR', 'constraint' => 191],
            'value_hash' => ['type' => 'CHAR', 'constraint' => 40],
            'is_secret' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            // When the *device's* configuration was captured, not when we indexed it: a search result
            // has to say how old the answer is.
            'captured_at' => ['type' => 'DATETIME', 'null' => true],
            'backup_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'indexed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['pf_id', 'section'], false, false, 'idx_config_index_device_section');
        $this->forge->addKey('value_norm', false, false, 'idx_config_index_value');
        $this->forge->addKey('value_hash', false, false, 'idx_config_index_hash');
        $this->forge->addKey('section', false, false, 'idx_config_index_section');
        $this->forge->createTable('config_index', true);

        // ---------------------------------------------------------------- per-device coverage
        $this->forge->addField([
            'pf_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'device_name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['indexed', 'no_snapshot', 'unreadable', 'invalid', 'too_large', 'not_indexed'],
                'default'    => 'not_indexed',
            ],
            'reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'backup_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'file_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'file_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'captured_at' => ['type' => 'DATETIME', 'null' => true],
            'file_size' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'nodes' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
            'indexed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('pf_id', true);
        $this->forge->addKey('status', false, false, 'idx_config_index_state_status');
        $this->forge->createTable('config_index_state', true);
    }

    public function down()
    {
        $this->forge->dropTable('config_index_state', true);
        $this->forge->dropTable('config_index', true);
    }
}
