<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\ConfigPush\ConfigJobRunner;
use App\Support\CliOptions;

/**
 * Executes queued config push jobs outside the web request.
 *
 * Cron (every minute) is the reliable path. The limit is read through CliOptions, which understands both
 * `--limit=5` (as documented here) and `--limit 5` — see App\Support\CliOptions for why that matters:
 *   * * * * * cd /path/to/app && php spark jobs:run --limit=5 >> writable/logs/config_jobs.log 2>&1
 *
 * Manual single job:
 *   php spark jobs:run --job=42 --verbose
 */
class RunConfigJobs extends BaseCommand
{
    protected $group       = 'Config Push';
    protected $name        = 'jobs:run';
    protected $description = 'Runs queued config push jobs (config_jobs / job_targets).';
    protected $usage       = 'jobs:run [--job=<id>] [--limit=<n>] [--verbose]';
    protected $options     = [
        '--job'     => 'Run one specific job id instead of sweeping open jobs.',
        '--limit'   => 'How many open jobs to sweep in one pass (default 5).',
        '--verbose' => 'Print every per-device log line.',
    ];

    public function run(array $params)
    {
        // Read through CliOptions: `--job=42` is how the usage string spells it and what an operator
        // types, and a silently-ignored scope means this sweeps five jobs instead of running that one.
        $jobId   = CliOptions::int($params, 'job');
        $limit   = CliOptions::int($params, 'limit', 5);
        $verbose = CliOptions::flag($params, 'verbose');

        $runner = new ConfigJobRunner();

        $emit = function (string $status, string $message) use ($verbose) {
            // Per-device detail is noisy on cron, so only the summary is shown by default.
            $isDetail = str_starts_with($message, '  ');

            if ($isDetail && !$verbose) {
                return;
            }

            $colour = match ($status) {
                'error'   => 'red',
                'success' => 'green',
                default   => 'yellow',
            };

            CLI::write(trim($message), $colour);
        };

        CLI::write('Config job runner started' . ($jobId !== null ? " for job #{$jobId}" : ' (sweep)'), 'white');

        $summary = $runner->run($jobId, $limit, $emit);

        CLI::write(
            sprintf('Done. jobs=%d applied=%d failed=%d', $summary['jobs'], $summary['applied'], $summary['failed']),
            $summary['failed'] > 0 ? 'red' : 'green'
        );

        return $summary['failed'] > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
