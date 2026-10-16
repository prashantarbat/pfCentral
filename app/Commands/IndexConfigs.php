<?php

namespace App\Commands;

use App\Models\ConfigIndexStateModel;
use App\Services\ConfigSearch\ConfigIndexer;
use App\Support\CliOptions;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Builds the fleet configuration search index from the stored snapshots.
 *
 * Run it after the nightly backup sweep, so a search is answered from this morning's configuration
 * rather than from whatever a device happened to look like when the index was last touched:
 *
 *   30 9 * * * cd /path/to/app && php spark config:index >> writable/logs/config_index.log 2>&1
 *
 * Re-indexing is idempotent — a device's facts are replaced wholesale, never merged — so it is safe to
 * run as often as you like, and safe to run for a single device after a push:
 *
 *   php spark config:index --device=25
 *   php spark config:index --status
 */
class IndexConfigs extends BaseCommand
{
    protected $group       = 'Config';
    protected $name        = 'config:index';
    protected $description = 'Extracts searchable facts from stored device configurations into config_index.';
    protected $usage       = 'config:index [--device=<id|name>] [--limit=<n>] [--status] [--verbose]';
    protected $options     = [
        '--device'  => 'Index one device, by id or by (part of) its name.',
        '--limit'   => 'Stop after this many devices have been indexed successfully.',
        '--status'  => 'Print what the index currently covers and change nothing.',
        '--verbose' => 'Print every extracted device, including the ones that could not be indexed.',
    ];

    public function run(array $params)
    {
        $indexer = new ConfigIndexer();

        if (CliOptions::flag($params, 'status')) {
            $this->report($indexer->coverage());

            return EXIT_SUCCESS;
        }

        $verbose = CliOptions::flag($params, 'verbose');
        $limit   = CliOptions::int($params, 'limit');
        $target  = CliOptions::value($params, 'device');

        if ($target !== null && $target !== '') {
            return $this->indexOne($indexer, $target);
        }

        $devices = $indexer->devicesToIndex();

        CLI::write('Config index: ' . count($devices) . ' device(s) to consider', 'white');

        $summary = $indexer->indexAll(function (array $result) use ($verbose) {
            if ($result['status'] === ConfigIndexStateModel::STATUS_INDEXED) {
                if ($verbose) {
                    CLI::write('   ' . $result['device'] . ' — ' . $result['facts'] . ' fact(s) from ' . basename((string) ($result['message'])), 'green');
                }

                return;
            }

            // A device that could not be indexed is never silent: an index that quietly covers fewer
            // devices than the operator thinks it does is worse than no index at all.
            CLI::write('   ' . $result['device'] . ' — ' . ConfigIndexStateModel::statusLabel($result['status']) . ': ' . $result['message'], 'yellow');
        }, $limit);

        CLI::write(
            sprintf('Done. indexed=%d facts=%d not-indexed=%d', $summary['indexed'], $summary['facts'], count($summary['failed'])),
            $summary['failed'] === [] ? 'green' : 'yellow'
        );

        $this->report($indexer->coverage());

        return EXIT_SUCCESS;
    }

    private function indexOne(ConfigIndexer $indexer, string $target): int
    {
        $device = null;

        foreach ($indexer->devicesToIndex() as $candidate) {
            if ((string) $candidate['id'] === $target || stripos((string) $candidate['name'], $target) !== false) {
                $device = $candidate;

                break;
            }
        }

        if ($device === null) {
            CLI::error('No device matches "' . $target . '".');

            return EXIT_ERROR;
        }

        $result = $indexer->indexDevice($device);
        $colour = $result['status'] === ConfigIndexStateModel::STATUS_INDEXED ? 'green' : 'yellow';

        CLI::write($result['device'] . ': ' . ConfigIndexStateModel::statusLabel($result['status']) . ' — ' . $result['message'], $colour);

        return $result['status'] === ConfigIndexStateModel::STATUS_INDEXED ? EXIT_SUCCESS : EXIT_ERROR;
    }

    /** @param array<string, mixed> $coverage */
    private function report(array $coverage): void
    {
        CLI::newLine();
        CLI::write(
            sprintf(
                'Index covers %d of %d device(s), %d fact(s)%s',
                (int) $coverage['indexed'],
                (int) $coverage['total'],
                (int) $coverage['facts'],
                $coverage['last_indexed'] !== null ? ' — newest index ' . $coverage['last_indexed'] : ''
            ),
            $coverage['missing'] > 0 ? 'yellow' : 'green'
        );

        foreach ($coverage['problems'] as $problem) {
            CLI::write('   ' . $problem['device'] . ' — ' . $problem['label'] . ($problem['reason'] !== null ? ': ' . $problem['reason'] : ''), 'yellow');
        }

        if ($coverage['stale']) {
            CLI::write('   The index is more than ' . ConfigIndexer::STALE_AFTER_HOURS . ' hours old; run config:index again.', 'red');
        }
    }
}
