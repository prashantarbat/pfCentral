<?php

namespace App\Commands;

use App\Services\Availability\AvailabilityService;
use App\Support\CliOptions;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Watches the branches: is each site's link up, and when did that change?
 *
 * Run it every few minutes — the interval decides how precisely an outage can be dated:
 *
 *   *\/5 * * * * cd /path/to/app && php spark availability:probe >> writable/logs/branch_availability.log 2>&1
 *
 * It only writes when something *changes*: a run that finds every branch up records a state row per branch
 * and no events, so the history stays a list of outages rather than a list of observations nobody reads.
 *
 * Manual runs:
 *
 *   php spark availability:probe --verbose              # what every branch answered
 *   php spark availability:probe --device=yavatmal      # one branch
 *   php spark availability:probe --dry                  # probe, decide, write nothing
 */
class ProbeAvailability extends BaseCommand
{
    protected $group       = 'Config';
    protected $name        = 'availability:probe';
    protected $description = 'Probes every branch and records availability outages (branch_availability_*).';
    protected $usage       = 'availability:probe [--device=<id|name>] [--limit=<n>] [--timeout=<seconds>] [--dry] [--verbose]';
    protected $options     = [
        '--device'  => 'Probe one branch, by id or by (part of) its name.',
        '--limit'   => 'Stop after this many branches have been probed.',
        '--timeout' => 'Seconds to wait for each address (default ' . AvailabilityService::PROBE_TIMEOUT . ').',
        '--dry'     => 'Probe and report, but change nothing.',
        '--verbose' => 'Explain every branch, including the ones that answered normally.',
        '--prune'   => 'Days of closed outages to retain (default ' . AvailabilityService::RETENTION_DAYS . '). Older events are pruned.',
    ];

    public function run(array $params)
    {
        $verbose = CliOptions::flag($params, 'verbose');
        $dry     = CliOptions::flag($params, 'dry');

        $service = new AvailabilityService();

        $options = [
            'limit'   => CliOptions::int($params, 'limit'),
            'device'  => CliOptions::value($params, 'device'),
            'timeout' => CliOptions::int($params, 'timeout'),
            'dry'     => $dry,
        ];

        CLI::write('Branch availability probe' . ($dry ? ' (dry run: nothing will be written)' : ''), 'white');

        $summary = $service->sweep($options);

        foreach ($summary['results'] as $result) {
            if (!$verbose && $result['ok']) {
                continue;
            }

            CLI::write(
                sprintf('   %s %s%s', $result['ok'] ? 'UP  ' : 'DOWN', $result['device'], $verbose ? ' — ' . $result['target'] . ' in ' . $result['ms'] . ' ms' : ''),
                $result['ok'] ? 'green' : 'red'
            );
        }

        foreach ($summary['silent'] as $silence) {
            CLI::write('   SILENT ' . $silence['device'] . ($verbose ? ' — ' . $silence['evidence'] : ''), 'yellow');
        }

        foreach ($summary['transitions'] as $transition) {
            $message = sprintf(
                '   %s %s: %s → %s',
                $transition['closed'] ? 'RECOVERED' : 'OUTAGE   ',
                $transition['device'],
                $transition['from'],
                $transition['to']
            );

            CLI::write($message, $transition['closed'] ? 'green' : 'red');
        }

        $pruneParam = CliOptions::value($params, 'prune');
        $pruneDays  = $pruneParam !== null ? (int) $pruneParam : null;
        if ($pruneDays !== null && $pruneDays > 0 && !$dry) {
            $pruned = $service->pruneEvents($pruneDays);
            if ($pruned > 0) {
                CLI::write(sprintf('   Pruned %d closed events older than %d days.', $pruned, $pruneDays), 'cyan');
            }
        }

        CLI::write(
            sprintf(
                'Done. branches=%d probed=%d up=%d down=%d no-address=%d changes=%d',
                $summary['branches'],
                $summary['probed'],
                $summary['up'],
                $summary['down'],
                $summary['skipped'],
                count($summary['transitions'])
            ),
            $summary['down'] > 0 ? 'yellow' : 'green'
        );

        // A branch that is down is not a failed *command*: the run did exactly what it was asked to, and
        // exiting non-zero would make cron mail somebody about a real outage as if the job were broken.
        return EXIT_SUCCESS;
    }
}
