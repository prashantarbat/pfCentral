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
 * Re-checks devices that were changed, and reports any that no longer match.
 *
 * A push reports that it *wrote* something. That is not the same as the device still running it: a
 * package can rewrite config.xml on its next save, and a branch engineer restoring an older
 * configuration silently undoes a change nobody will hear about. This command reads the devices back
 * and records what it finds, so drift is something you can query instead of something you discover
 * during an outage.
 *
 * Drift raises a visible alert and emails the admins, so this command is not just a reporter: it is
 * the thing that tells somebody. Alerts are de-duplicated by the difference they describe, so the
 * hourly run does not re-send for a device that is still drifted — and `--no-email` exists for a
 * maintenance window where you know what is changing and do not want the noise.
 *
 * Cron (hourly is plenty — this reads devices and changes nothing):
 *   0 * * * * cd /path/to/app && php spark jobs:verify --limit=25 >> writable/logs/config_verify.log 2>&1
 *
 * Manual:
 *   php spark jobs:verify --job=42 --verbose
 */
class VerifyConfigJobs extends BaseCommand
{
    protected $group       = 'Config Push';
    protected $name        = 'jobs:verify';
    protected $description = 'Reads devices back and records whether applied config jobs actually match.';
    protected $usage       = 'jobs:verify [--job=<id>] [--limit=<n>] [--all] [--no-email] [--verbose]';
    protected $options     = [
        '--job'      => 'Verify only the targets of one job.',
        '--limit'    => 'How many targets to check in one pass (default 10).',
        '--all'      => 'Also re-check targets that already reached a verdict (matched/drifted).',
        '--no-email' => 'Record drift alerts but send no email (maintenance windows).',
        '--verbose'  => 'Print one line per device.',
    ];

    public function run(array $params)
    {
        $jobId   = CliOptions::int($params, 'job');
        $limit   = CliOptions::int($params, 'limit', 10);
        $verbose = CliOptions::flag($params, 'verbose');
        $all     = CliOptions::flag($params, 'all');
        $noEmail = CliOptions::flag($params, 'no-email');

        $alerts   = new ConfigAlertService();
        // --no-email suppresses only the sending: the drift is still recorded, still visible, and still
        // owed an email, which the next normal run sends. That is the part that must not be switched
        // off during a maintenance window.
        $alerts->suppressEmail($noEmail);
        $verifier = new ConfigVerificationService(null, null, null, null, null, $alerts);
        $jobModel  = new ConfigJobModel();
        $inventory = new PfInventoryModel();

        $targets = $all ? $this->everyAppliedTarget($jobId, $limit) : $verifier->pending($jobId, $limit);

        if ($targets === []) {
            CLI::write('Nothing to verify: no applied target is waiting for a check.', 'green');

            return EXIT_SUCCESS;
        }

        $counts    = ['matched' => 0, 'drifted' => 0, 'unavailable' => 0, 'not_applicable' => 0];
        $drifted   = [];

        foreach ($targets as $target) {
            $job = $jobModel->find((int) $target['job_id']);
            if (!$job) {
                continue;
            }

            $device = $inventory->find((int) $target['pf_id']) ?? ['id' => (int) $target['pf_id'], 'name' => 'device #' . $target['pf_id']];

            $outcome = $verifier->verifyDevice($target, $job, $device);
            $status  = $outcome['status'];
            $name    = $device['name'] ?? ('device #' . $target['pf_id']);

            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;

            // Drift and "could not confirm" are the two states an operator has to act on, so they are
            // always printed — a silent cron job that only ever says "done" is a cron job nobody reads.
            if ($status === 'drifted') {
                $drifted[] = "{$name}: {$outcome['detail']}";
                CLI::write("[{$name}] DRIFTED — " . $outcome['detail'], 'red');

                // Say what happened to the alert, including when it could not be sent: an operator
                // reading this log is the second line of defence behind the email.
                $alert = $outcome['alert'] ?? null;

                if ($alert !== null) {
                    $colour = ($alert['action'] === 'unchanged') ? 'yellow' : (($alert['notified'] ?? false) ? 'green' : 'yellow');
                    // "REVERT" in the line, not buried in the message: a sweep that finds a change undone
                    // minutes after the push is the event worth reading twice in a log.
                    $kind = ConfigAlertModel::isRevert($alert['kind'] ?? null) ? 'REVERT ' : '';
                    CLI::write('  alert ' . $kind . $alert['action'] . ': ' . $alert['message'], $colour);
                }
            } elseif ($status === 'unavailable') {
                CLI::write("[{$name}] could not confirm — " . $outcome['detail'], 'yellow');
            } elseif ($verbose) {
                CLI::write("[{$name}] {$status} — " . $outcome['detail'], 'green');
            }
        }

        CLI::write(sprintf(
            'Verified %d target(s): matched=%d drifted=%d unavailable=%d n/a=%d',
            count($targets),
            $counts['matched'] ?? 0,
            $counts['drifted'] ?? 0,
            $counts['unavailable'] ?? 0,
            $counts['not_applicable'] ?? 0
        ), ($counts['drifted'] ?? 0) > 0 ? 'red' : 'green');

        if (!$noEmail) {
            $this->flushNotifications($alerts);
        }

        $open = $alerts->openCount();

        if ($open > 0) {
            $unnotified = count($alerts->pendingNotifications(200));
            CLI::write(
                'Open drift alerts: ' . $open . ($unnotified > 0 ? ' (' . $unnotified . ' with an email still owed)' : '') . ' — see the Config Jobs page.',
                'yellow'
            );
        }

        return ($counts['drifted'] ?? 0) > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }

    /**
     * Retries the email for anything that was recorded but never sent — a mail outage when the drift
     * was found, or a sweep that ran with alerts switched off.
     *
     * Bounded by the mailer's attempt cap, so a permanently broken mail server produces a visible
     * "could not send" alert rather than an hourly retry forever.
     */
    private function flushNotifications(ConfigAlertService $alerts): void
    {
        // The retry loop itself lives on the service, shared with the verification window's runner:
        // there is one definition of how many times an email may be retried, not one per command.
        foreach ($alerts->notifyPending(10) as $attempt) {
            CLI::write('[alert #' . ($attempt['alert']['id'] ?? '?') . '] ' . $attempt['message'], $attempt['ok'] ? 'green' : 'yellow');
        }
    }

    /**
     * Every applied target, including ones that already have a verdict — the `--all` re-check.
     *
     * Useful after a fleet-wide maintenance window, when "verify everything applied today" is the
     * question and the sweep's pending-only query would skip exactly the rows already checked.
     *
     * @return list<array>
     */
    private function everyAppliedTarget(?int $jobId, int $limit): array
    {
        try {
            $builder = db_connect()->table('job_targets')
                ->select('job_targets.*')
                ->where('job_targets.status', 'applied')
                ->orderBy('job_targets.verified_at', 'ASC', false)
                ->orderBy('job_targets.id', 'ASC')
                ->limit(max(1, $limit));

            if ($jobId !== null) {
                $builder->where('job_targets.job_id', $jobId);
            }

            return $builder->get()->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Verification sweep (--all) query failed: ' . $e->getMessage());

            return [];
        }
    }
}
