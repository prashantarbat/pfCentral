<?php

namespace App\Controllers;

use App\Models\CoreConfigModel;
use App\Services\Availability\AvailabilityExportService;
use App\Services\Availability\AvailabilityService;

/**
 * Per-branch availability: what every site's link is doing, and every time it stopped.
 *
 * This page is deliberately not part of the change pipeline. The job dashboard answers "what did we
 * change and did it stick"; this answers "was the branch reachable, and when was it not" — a question
 * that has to be answerable *without* a change having happened, because the interesting outages are
 * usually the ones nobody caused.
 *
 * Two things the page has to be honest about, and both are visible on it:
 *
 *  - **uptime is computed only over the time a branch was watched.** A window that reaches back before
 *    monitoring began shows the observed span, not a flattering 100%.
 *  - **an inference is labelled as one.** An outage found by a failed probe is a measurement; one inferred
 *    from a device that stopped checking in and cannot be probed is labelled `inferred from silence`.
 *
 * The CSV export runs the identical filters through the identical service as the page, so a file handed to
 * somebody cannot disagree with the screen it was taken from.
 */
class AvailabilityController extends BaseController
{
    protected $coreConfigModel;
    protected AvailabilityService $service;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->coreConfigModel = new CoreConfigModel();
        $this->service         = new AvailabilityService();
    }

    public function index()
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        $appName    = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl     = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';

        $data['title']       = strip_tags($appName) . ' 🔸 Branch Availability';
        $data['app_name']    = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url']     = $appUrl;

        // Server-rendered so the group filter is usable on the first paint, and so the page can say
        // whether anything is being watched at all before the first fetch comes back.
        $initial = $this->service->report(AvailabilityService::normalizeFilters([]), 1);

        $data['groups']         = $initial['groups'];
        $data['total_branches'] = count($initial['branches']);
        $data['watched']        = count(array_filter($initial['branches'], static fn (array $branch): bool => !empty($branch['monitored_since'])));

        $data['default_days']    = AvailabilityService::DEFAULT_WINDOW_DAYS;
        $data['max_days']        = AvailabilityService::MAX_WINDOW_DAYS;
        $data['silent_minutes']  = AvailabilityService::SILENT_AFTER_MINUTES;
        $data['correlate_minutes'] = AvailabilityService::CORRELATE_WINDOW_MINUTES;
        $data['poor_uptime']     = AvailabilityService::POOR_UPTIME;
        $data['retention_days']  = AvailabilityService::RETENTION_DAYS;
        $data['db_stats']        = $this->service->dbStats();

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('availability/index', $data)
            . view('frontend/footer', $data);
    }

    /** The tiles, the branch table and one page of outages for the filters the browser sent. */
    public function rows()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $filters = AvailabilityService::normalizeFilters($this->input());
        $page    = (int) ($this->request->getGet('page') ?? $this->request->getPost('page') ?? 1);

        try {
            $result = $this->service->report($filters, $page);
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability report failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'The availability report is unavailable.',
                'branches' => [],
                'outages'  => [],
            ]);
        }

        return $this->response->setJSON(array_merge(['status' => 'success'], $result, [
            'silent_minutes'    => AvailabilityService::SILENT_AFTER_MINUTES,
            'correlate_minutes' => AvailabilityService::CORRELATE_WINDOW_MINUTES,
            'poor_uptime'       => AvailabilityService::POOR_UPTIME,
            'retention_days'    => AvailabilityService::RETENTION_DAYS,
            'db_stats'          => $this->service->dbStats(),
        ]));
    }

    /**
     * The outages as a CSV file, same filters.
     *
     * A GET so the slice can be linked: "everything that went down in the Akola group last month" is a URL
     * somebody can put in an incident report.
     */
    public function export()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setBody('Unauthorized access.');
        }

        $filters = AvailabilityService::normalizeFilters($this->request->getGet());

        try {
            $export = $this->service->exportRows($filters);
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability export failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setBody('The export could not be built.');
        }

        log_message('info', sprintf(
            'Branch availability exported by %s: %d outage row(s), branch=%s state=%s kind=%s group=%s from=%s to=%s',
            (string) ($user->username ?? ('user #' . (int) ($user->id ?? 0))),
            count($export['rows']),
            $filters['branch'] !== '' ? $filters['branch'] : '-',
            $filters['state'] !== '' ? $filters['state'] : '-',
            $filters['kind'] !== '' ? $filters['kind'] : '-',
            $filters['group'] !== '' ? $filters['group'] : '-',
            $filters['from'] !== '' ? $filters['from'] : '-',
            $filters['to'] !== '' ? $filters['to'] : '-'
        ));

        return $this->response
            ->download(AvailabilityService::filename(), AvailabilityService::csv($export['rows']))
            ->setContentType('text/csv; charset=utf-8');
    }

    /**
     * Complete multi-sheet XLSX export containing both Branch Multi-WAN Availability and Outage Logs.
     */
    public function exportXlsx()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setBody('Unauthorized access.');
        }

        $filters = AvailabilityService::normalizeFilters($this->request->getGet());

        try {
            $report = $this->service->report($filters, 1);
            $export = $this->service->exportRows($filters);
            $xlsxContent = AvailabilityExportService::buildFleetXlsx($report, $export);
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability XLSX export failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setBody('The XLSX export could not be built.');
        }

        return $this->response
            ->download(AvailabilityService::xlsxFilename(), $xlsxContent)
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * Executive printable / exportable PDF report for fleet availability.
     */
    public function exportPdf()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return redirect()->to('/loggedlive')->with('error', 'Administrator access required.');
        }

        $filters = AvailabilityService::normalizeFilters($this->request->getGet());

        try {
            $report = $this->service->report($filters, 1);
            $export = $this->service->exportRows($filters);
        } catch (\Throwable $e) {
            log_message('error', 'Branch availability PDF generation failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setBody('The PDF report could not be generated.');
        }

        $appName    = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'PF-CENTRAL';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';

        $data = [
            'branches'    => $report['branches'],
            'outages'     => $export['rows'],
            'tiles'       => $report['tiles'],
            'window'      => $report['window'],
            'filters'     => $filters,
            'user'        => $user,
            'app_name'    => $appName,
            'app_version' => $appVersion,
            'auto_print'  => !empty($this->request->getGet('print')),
        ];

        return view('availability/fleet_report_pdf', $data);
    }

    /**
     * Per-device comprehensive availability, multi-WAN link status & outage PDF dossier.
     */
    public function deviceReport($id)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return redirect()->to('/loggedlive')->with('error', 'Administrator access required.');
        }

        $days = (int) ($this->request->getGet('days') ?? AvailabilityService::DEFAULT_WINDOW_DAYS);
        $detail = $this->service->getDeviceAvailabilityDetail((int) $id, $days);

        if (!$detail) {
            return $this->response->setStatusCode(404)->setBody('Branch device not found.');
        }

        $branchCode = '';
        if (preg_match('/^(\d+)/', $detail['device']['name'] ?? '', $m)) {
            $branchCode = $m[1];
        }
        $auditRef = 'PFC-AVAIL-' . date('Y') . '-' . ($branchCode ?: sprintf('%04d', (int) $id)) . '-' . (int) $id;

        $appName    = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'PF-CENTRAL';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';

        $data = [
            'device'      => $detail['device'],
            'status'      => $detail['status'],
            'location'    => $detail['location'],
            'links'       => $detail['links'],
            'outages'     => $detail['outages'],
            'window'      => $detail['window'],
            'audit_ref'   => $auditRef,
            'user'        => $user,
            'app_name'    => $appName,
            'app_version' => $appVersion,
            'auto_print'  => !empty($this->request->getGet('print')),
        ];

        return view('availability/device_report_pdf', $data);
    }

    /**
     * Per-device XLSX export with full telemetry and incident history.
     */
    public function deviceXlsx($id)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setBody('Unauthorized access.');
        }

        $days = (int) ($this->request->getGet('days') ?? AvailabilityService::DEFAULT_WINDOW_DAYS);
        $detail = $this->service->getDeviceAvailabilityDetail((int) $id, $days);

        if (!$detail) {
            return $this->response->setStatusCode(404)->setBody('Branch device not found.');
        }

        try {
            $xlsxContent = AvailabilityExportService::buildDeviceXlsx($detail);
        } catch (\Throwable $e) {
            log_message('error', 'Branch device XLSX export failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setBody('The XLSX export could not be built.');
        }

        return $this->response
            ->download(AvailabilityService::xlsxFilename($detail['device']['name']), $xlsxContent)
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * Filters from the query string or the POST body, whichever the caller used.
     *
     * @return array<string, mixed>
     */
    private function input(): array
    {
        $data = [];

        foreach (['branch', 'state', 'kind', 'group', 'from', 'to'] as $key) {
            $value = $this->request->getGet($key) ?? $this->request->getPost($key);

            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Trigger fleet-wide probe sweep via AJAX.
     */
    public function probeAll()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        try {
            $timeout = (int) ($this->request->getPost('timeout') ?? AvailabilityService::PROBE_TIMEOUT);
            $summary = $this->service->sweep(['timeout' => max(1, min(10, $timeout))]);

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => sprintf('Probed %d branches: %d UP, %d DOWN.', $summary['probed'], $summary['up'], $summary['down']),
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Manual fleet sweep failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Fleet probe failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Trigger single-branch probe via AJAX.
     */
    public function probeDevice($pfId = null)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $pfId = (int) ($pfId ?? $this->request->getPost('pf_id') ?? 0);
        if ($pfId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['status' => 'error', 'message' => 'Invalid device ID.']);
        }

        try {
            $summary = $this->service->sweep([
                'device_id' => $pfId,
                'device'    => (string) $pfId,
                'timeout'   => AvailabilityService::PROBE_TIMEOUT
            ]);
            $result  = $summary['results'][0] ?? null;

            $message = 'Probe completed.';
            if ($result) {
                $message = sprintf('%s: %s (%d ms)', $result['device'], $result['ok'] ? 'UP' : 'DOWN', $result['ms']);
            } elseif (!empty($summary['silent'])) {
                $message = sprintf('%s: SILENT (inferred, no socket to probe)', $summary['silent'][0]['device']);
            } elseif (!empty($summary['skipped'])) {
                $message = 'Branch has no socket address configured to probe (observed via check-in).';
            }

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => $message,
                'result'  => $result,
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Single device probe failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Probe failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Manually prune aged closed outage records.
     */
    public function prune()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $days = (int) ($this->request->getPost('days') ?? AvailabilityService::RETENTION_DAYS);

        try {
            $deleted = $this->service->pruneEvents($days);
            $stats   = $this->service->dbStats();

            return $this->response->setJSON([
                'status'  => 'success',
                'message' => sprintf('Pruned %d events older than %d days.', $deleted, $days),
                'deleted' => $deleted,
                'stats'   => $stats,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Manual availability prune failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Prune failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Storage footprint diagnostics.
     */
    public function dbStats()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'stats'  => $this->service->dbStats(),
        ]);
    }
}
