<?php

namespace App\Controllers;

use App\Models\CoreConfigModel;
use App\Services\ConfigSearch\ConfigSearchService;

/**
 * Fleet-wide configuration search: one box over every device's stored configuration.
 *
 * Two things shape the page rather than the query.
 *
 * First, an answer comes from a **snapshot**, so it says which one and when it was taken. A search result
 * presented as live truth would be read as "the device is like this right now", which the index cannot
 * know — a push an hour ago is invisible until the next index run.
 *
 * Second, the page always reports **what it could not look at**. The coverage strip names the devices
 * whose configuration is missing from disk, unreadable, or not XML, because "no device matches" and "we
 * could not read eleven devices" are different answers, and only one of them is safe to act on.
 *
 * Reads are open to anybody who can see the fleet (`admin.access`, like the other configuration pages).
 * Revealing a credential is a separate, explicit act, and it is logged: a search that surfaces a
 * pre-shared key is exactly the kind of read an audit wants to be able to reconstruct.
 */
class ConfigSearchController extends BaseController
{
    protected $coreConfigModel;
    protected ConfigSearchService $search;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->coreConfigModel = new CoreConfigModel();
        $this->search          = new ConfigSearchService();
    }

    public function index()
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        $appName    = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl     = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';

        $data['title']       = strip_tags($appName) . ' 🔸 Configuration Search';
        $data['app_name']    = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url']     = $appUrl;

        // Server-rendered so the section filter and the honesty strip are right on the first paint.
        $data['sections'] = $this->search->sectionOptions();
        $data['coverage'] = $this->search->coverage();
        $data['examples'] = ConfigSearchService::examples();

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('config/search', $data)
            . view('frontend/footer', $data);
    }

    /** The matches for the filters the browser sent, grouped by device. */
    public function rows()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $filters = ConfigSearchService::normalizeFilters($this->input());
        $page    = (int) ($this->request->getGet('page') ?? $this->request->getPost('page') ?? 1);

        if ($filters['reveal']) {
            $this->logRead($user, $filters, 'page');
        }

        try {
            $result = $this->search->search($filters, $page);
        } catch (\Throwable $e) {
            log_message('error', 'Configuration search failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'The search is unavailable.',
                'groups'  => [],
            ]);
        }

        return $this->response->setJSON(array_merge(['status' => 'success'], $result));
    }

    /**
     * The same matches as a CSV file, same filters.
     *
     * A GET so the slice can be linked to a colleague — and so that "everything mentioning 10.20.0"
     * is a URL somebody can paste into an incident channel.
     */
    public function export()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setBody('Unauthorized access.');
        }

        $filters = ConfigSearchService::normalizeFilters($this->request->getGet());

        if ($filters['q'] === '') {
            return $this->response->setStatusCode(400)->setBody('A search term is required before exporting.');
        }

        try {
            $export = $this->search->exportRows($filters);
        } catch (\Throwable $e) {
            log_message('error', 'Configuration search export failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setBody('The export could not be built.');
        }

        $this->logRead($user, $filters, 'export', count($export['rows']), $export['capped']);

        return $this->response
            ->download(ConfigSearchService::filename(), ConfigSearchService::csv($export['rows'], $filters['reveal']))
            ->setContentType('text/csv; charset=utf-8');
    }

    /**
     * Filters from the query string or the POST body, whichever the caller used.
     *
     * @return array<string, mixed>
     */
    private function input(): array
    {
        $data = [];

        foreach (['q', 'section', 'device', 'exact', 'reveal'] as $key) {
            $value = $this->request->getGet($key) ?? $this->request->getPost($key);

            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Who searched the fleet's configurations, for what, and whether they asked to see credentials.
     *
     * @param array{q:string, section:string, device:string, exact:bool, reveal:bool} $filters
     */
    private function logRead(object $user, array $filters, string $how, ?int $rows = null, bool $capped = false): void
    {
        log_message('info', sprintf(
            'Configuration search %s by %s: q="%s" section=%s device=%s exact=%s%s%s%s',
            $how,
            (string) ($user->username ?? ('user #' . (int) ($user->id ?? 0))),
            $filters['q'],
            $filters['section'] !== '' ? $filters['section'] : '-',
            $filters['device'] !== '' ? $filters['device'] : '-',
            $filters['exact'] ? 'yes' : 'no',
            $filters['reveal'] ? ' REVEALED CREDENTIALS' : '',
            $rows !== null ? ' rows=' . $rows : '',
            $capped ? ' (capped)' : ''
        ));
    }
}
