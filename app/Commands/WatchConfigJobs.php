<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Support\CliOptions;
use App\Models\ConfigAlertModel;
use App\Models\ConfigJobModel;
use App\Models\PfInventoryModel;
use App\Services\ConfigPush\ConfigAlertService;
use App\Services\ConfigPush\ConfigVerificationService;

/**
 * Watches freshly applied changes for an hour, so an immediate revert is caught in minutes.
 *
 * The hourly sweep answers "is the fleet still what we pushed"; this answers the narrower and more
 * urgent question "did what we just pushed survive the next five minutes". Those are different failures.
 * A drift found a week later is usually somebody editing a firewall by hand. A drift found four minutes
 * after a push is the device itself rejecting or overwriting the change — a package sync rewriting
 * config.xml, a script that runs on a schedule, a restore — and every one of those looks like a clean
 * success to both the push result and an hourly check.
 *
 * Each target is read on the schedule in `ConfigVerificationService::WATCH_SCHEDULE` — 5, 15, 30 and 60
 * minutes after it was applied — so the window needs to run at least that often:
 *
 *   cron (every five minutes — a slot that is due is picked up on the next pass; written as
 *   `0-59/5` rather than the more familiar star-slash form because a literal star-slash would close
 *   this comment):
 *     0-59/5 * * * * cd /path/to/app && php spark jobs:watch --limit=25 >> writable/logs/config_watch.log 2>&1
 *
 * The verdicts are recorded by exactly the same code the sweep uses, so a revert found here raises the
 * same drift alert, with the same de-duplication, and emails the same people. Drift ends the target's
 * window immediately; a device that cannot be reached keeps its schedule, and its failed reads do not
 * consume the sweep's attempt budget — that budget is still intact when the window is up.
 */
class WatchConfigJobs extends BaseCommand
{
    protected $group       = 'Config Push';
    protected $name        = 'jobs:watch';
    protected $usage       = 'jobs:watch [--limit=<n>] [--no-email] [--verbose]';
    protected $description = 'Re-reads recently applied devices on a short schedule to catch an immediate revert.';
    protected $options     = [
        '--limit'    => 'How many due targets to check in one pass (default 25).',
        '--no-email' => 'Record drift alerts but send no email (maintenance windows).',
        '--verbose'  => 'Print a line for every device, not only the ones that drifted.',
    ];

    public function run(array $params)
    {
        $limit   = CliOptions::int($params, 'limit', 25);
        $verbose = CliOptions::flag($params, 'verbose');
        $noEmail = CliOptions::flag($params, 'no-email');

        $alerts   = new ConfigAlertService();
        $alerts->suppressEmail($noEmail);
        $verifier  = new ConfigVerificationService(null, null, null, null, null, $alerts);
        $jobModel  = new ConfigJobModel();
        $inventory = new PfInventoryModel();

        $due = $verifier->watchDue($limit);

        if ($due === []) {
            CLI::write('Nothing due: no applied change is waiting for its next window check.', 'green');

            return EXIT_SUCCESS;
        }

        $counts  = ['matched' => 0, 'drifted' => 0, 'unavailable' => 0, 'not_applicable' => 0];
        $stopped = 0;

        foreach ($due as $target) {
            $job = $jobModel->find((int) $target['job_id']);

            if (!$job) {
                // The job is gone but the target is not: close the window rather than asking about it
                // every five minutes forever.
                $verifier->advanceWatch((int) $target['id'], $target, (int) $target['watch_checks'], true);
                CLI::write('[target #' . $target['id'] . '] job #' . $target['job_id'] . ' no longer exists; window closed.', 'yellow');

                continue;
            }

            $device = $inventory->find((int) $target['pf_id'])
                ?? ['id' => (int) $target['pf_id'], 'name' => 'device #' . $target['pf_id']];
            $name = (string) ($device['name'] ?? ('device #' . $target['pf_id']));

            $outcome = $verifier->watchCheck($target, $job, $device);
            $status  = $outcome['status'];

            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;

            if ($outcome['stopped']) {
                $stopped++;
            }

            // How long after the push this was found is the diagnosis, not decoration: minutes means the
            // device undid it, hours or days means somebody changed it. That, and not the wording of the
            // verdict, is what tells an operator which of those to go and look for.
            $minutes = ConfigVerificationService::ageInMinutes((string) ($target['applied_at'] ?? ''));

            $when = $minutes === null ? 'recently applied' : $minutes . ' minute(s) after the push';

            if ($status === 'drifted') {
                CLI::write("[{$name}] DRIFTED {$when} — " . $outcome['detail'], 'red');

                $alert = $outcome['alert'] ?? null;

                if ($alert !== null) {
                    // A window check that found drift is usually a revert, and the log is where the night shift
                    // meets it: name the kind here rather than leaving it to be inferred from the wording.
                    $kind = ConfigAlertModel::isRevert($alert['kind'] ?? null) ? 'REVERT ' : '';
                    CLI::write('  alert ' . $kind . $alert['action'] . ': ' . $alert['message'], ($alert['notified'] ?? false) ? 'green' : 'yellow');
                }
            } elseif ($status === 'unavailable') {
                CLI::write("[{$name}] could not confirm ({$when}) — " . $outcome['detail'], 'yellow');
            } elseif ($verbose) {
                CLI::write("[{$name}] {$status} ({$when}) — " . $outcome['detail'], 'green');
            }
        }

        CLI::write(sprintf(
            'Window checks on %d target(s): matched=%d drifted=%d unavailable=%d n/a=%d, %d window(s) closed',
            count($due),
            $counts['matched'],
            $counts['drifted'],
            $counts['unavailable'],
            $counts['not_applicable'],
            $stopped
        ), $counts['drifted'] > 0 ? 'red' : 'green');

        if (!$noEmail) {
            foreach ($alerts->notifyPending(10) as $attempt) {
                CLI::write('[alert #' . ($attempt['alert']['id'] ?? '?') . '] ' . $attempt['message'], $attempt['ok'] ? 'green' : 'yellow');
            }
        }

        return $counts['drifted'] > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
