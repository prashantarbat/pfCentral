<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Does the device still carry the pfCentral header?
 *
 * The flashing banner lives in `/usr/local/www/head.inc`, a file pfSense owns and rewrites on every
 * upgrade. When that happens the header disappears silently: the branch keeps working, the agent keeps
 * checking in, the row stays green, and nobody notices the number that is supposed to be on the login
 * page is gone. The only way to find out was to open each branch's webGUI by hand.
 *
 * So the agent now verifies the same thing a human would - grep head.inc for the banner - and reports it
 * on every normal check-in. One column, because the signal is one bit and it is read alongside the rest
 * of the status row.
 *
 * Three states, and the third is not an error:
 *
 *   1     the banner is present.
 *   0     the agent ran its self-heal and the banner is *still* missing - genuinely broken.
 *   NULL  this device has never reported (an agent older than the check). Unknown, not healthy: the whole
 *         point of the column is to stop "we have not looked" from reading as "all good".
 *
 * No default is set on purpose. A default of 1 would answer for the agents that never reported, which is
 * exactly the confusion this column exists to remove.
 */
class AddBannerToPfStatus extends Migration
{
    public function up()
    {
        $this->forge->addColumn('pf_status', [
            'banner' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
                'after'      => 'active_tunnel',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('pf_status', 'banner');
    }
}
