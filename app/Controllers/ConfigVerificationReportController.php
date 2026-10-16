<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CoreConfigModel;
use App\Services\ConfigPush\ConfigVerificationReportService;

/**
 * Fleet-wide verification report: what changed, when it was last confirmed, what drifted.
 *
 * Separate from the job dashboard on purpose. That page is organised by job (what is queued, what
 * failed, what needs approving) and is about the *queue*; this is organised by device and change over
 * time and is about the *fleet's state*, which is what an audit or a handover actually needs to read.
 * Keeping them apart also keeps the two queries honest — neither has to be bent to serve the other.
 *
 * The CSV export runs the identical filters through the identical service as the page, so the file
 * somebody is handed cannot disagree with the screen it was taken from.
 */
class ConfigVerificationReportController extends BaseController
{
    protected $coreConfigModel;
    protected ConfigVerificationReportService $reports;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->coreConfigModel = new CoreConfigModel();
        $this->reports         = new ConfigVerificationReportService();
    }

    public function index()
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        $appName    = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl     = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';

        $data['title']       = strip_tags($appName) . ' 🔸 Verification Report';
        $data['app_name']    = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url']     = $appUrl;

        // Rendered server-side so the group filter is usable on the first paint rather than after a
        // round trip that the operator has already clicked past.
        $data['groups']     = $this->reports->filterOptions();
        $data['stale_days'] = ConfigVerificationReportService::STALE_DAYS;
        $data['max_export'] = ConfigVerificationReportService::MAX_EXPORT;

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('config/verification', $data)
            . view('frontend/footer', $data);
    }

    /**
     * One page of the report, plus the tiles, for the filters the browser sent.
     */
    public function rows()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $filters = ConfigVerificationReportService::normalizeFilters($this->input());
        $page    = (int) ($this->request->getGet('page') ?? $this->request->getPost('page') ?? 1);

        try {
            $result = $this->reports->page($filters, $page);
        } catch (\Throwable $e) {
            log_message('error', 'Verification report failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'The verification report is unavailable.',
                'rows'    => [],
            ]);
        }

        return $this->response->setJSON(array_merge(['status' => 'success'], $result, [
            'stale_days' => ConfigVerificationReportService::STALE_DAYS,
            'max_export' => ConfigVerificationReportService::MAX_EXPORT,
        ]));
    }

    /**
     * The same report as a CSV file, same filters.
     *
     * A plain GET download rather than a POST: it is a read, the filters live in the URL, and an
     * operator can bookmark or share "everything applied to the Indore group last week" as a link.
     * It is also the one route here that can be handed to a colleague or a script.
     */
    public function export()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setBody('Unauthorized access.');
        }

        $filters = ConfigVerificationReportService::normalizeFilters($this->request->getGet());

        try {
            $export = $this->reports->exportRows($filters);
        } catch (\Throwable $e) {
            log_message('error', 'Verification report export failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setBody('The export could not be built.');
        }

        // Who pulled a copy of the fleet's change history, and for what slice of it, is itself worth
        // recording: this file is exactly the thing that leaves the building.
        log_message('info', sprintf(
            'Verification report exported by %s: %d row(s) of %d (scope=%s state=%s alert=%s device=%s group=%s job=%s from=%s to=%s)',
            (string) ($user->username ?? ('user #' . (int) ($user->id ?? 0))),
            count($export['rows']),
            $export['total'],
            $filters['scope'],
            $filters['state'] !== '' ? $filters['state'] : '-',
            $filters['alert'] !== '' ? $filters['alert'] : '-',
            $filters['device'] !== '' ? $filters['device'] : '-',
            $filters['group'] !== '' ? $filters['group'] : '-',
            $filters['job'] !== '' ? $filters['job'] : '-',
            $filters['from'] !== '' ? $filters['from'] : '-',
            $filters['to'] !== '' ? $filters['to'] : '-'
        ));

        return $this->response
            ->download(ConfigVerificationReportService::filename(), ConfigVerificationReportService::csv($export['rows']))
            ->setContentType('text/csv; charset=utf-8');
    }

    /**
     * Filters from the query string or the POST body, whichever the caller used.
     *
     * @return array<string, mixed>
     */
    private function input(): array
    {
        $keys = ['scope', 'device', 'group', 'job', 'state', 'alert', 'from', 'to'];
        $data = [];

        foreach ($keys as $key) {
            $value = $this->request->getGet($key) ?? $this->request->getPost($key);

            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
