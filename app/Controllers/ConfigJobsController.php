<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ConfigAlertModel;
use App\Models\ConfigJobModel;
use App\Models\CoreConfigModel;
use App\Models\JobTargetModel;
use App\Models\PfInventoryModel;
use App\Services\ConfigPush\ConfigAlertService;
use App\Services\ConfigPush\ConfigDiffService;
use App\Services\ConfigPush\ConfigJobService;
use App\Services\ConfigPush\ConfigVerificationService;

/**
 * Fleet-wide view of the config job queue.
 *
 * The push page is the place to *create* a change; this is the place to watch, retry and withdraw
 * what is already queued — including jobs nobody is sitting in front of when they fail.
 *
 * Approve and cancel are delegated to ConfigPushController's endpoints (configpush/approve,
 * configpush/cancel) rather than duplicated, so each action has exactly one implementation.
 */
class ConfigJobsController extends BaseController
{
    protected $coreConfigModel;
    protected $jobService;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->coreConfigModel = new CoreConfigModel();
        $this->jobService      = new ConfigJobService();
    }

    public function index()
    {
        $user = auth()->user();
        $data['first_name'] = $user->first_name ?? 'Not Login';

        $appName    = $this->coreConfigModel->where('name', 'app_name')->first()['value'] ?? 'My Application';
        $appVersion = $this->coreConfigModel->where('name', 'app_version')->first()['value'] ?? '1.0.0';
        $appUrl     = $this->coreConfigModel->where('name', 'app_url')->first()['value'] ?? 'http://localhost/pfadminhub/';

        $data['title']       = strip_tags($appName) . ' 🔸 Config Job Dashboard';
        $data['app_name']    = $appName;
        $data['app_version'] = $appVersion;
        $data['app_url']     = $appUrl;

        return view('frontend/header', $data)
            . view('frontend/sidebar', $data)
            . view('config/jobs', $data)
            . view('frontend/footer', $data);
    }

    /**
     * Job list with aggregated per-job progress, plus the counters the tiles need.
     */
    public function rows()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $filters = [
            'status' => (string) ($this->request->getGet('status') ?? $this->request->getPost('status') ?? ''),
            'q'      => (string) ($this->request->getGet('q') ?? $this->request->getPost('q') ?? ''),
        ];
        $page = (int) ($this->request->getGet('page') ?? 1);

        try {
            $result  = $this->jobService->listJobs($filters, $page, 25);
            $summary = $this->jobService->jobSummary();
        } catch (\Throwable $e) {
            log_message('error', 'Config job list failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'The job queue is unavailable.',
                'rows'    => [],
            ]);
        }

        return $this->response->setJSON(array_merge(['status' => 'success'], $result, [
            'summary' => $summary,
            // Lets the table offer Approve only where a second admin is genuinely allowed to act.
            'current_user_id'       => (int) ($user->id ?? 0),
            'self_approval_allowed' => ConfigJobService::selfApprovalAllowed(),
        ]));
    }

    /**
     * One job with its per-device detail.
     */
    public function detail($id = null)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $jobId = (int) $id;
        if ($jobId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No job was specified.']);
        }

        try {
            $detail = $this->jobService->jobDetail($jobId);
        } catch (\Throwable $e) {
            log_message('error', 'Config job detail read failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'Job detail is unavailable.']);
        }

        if ($detail === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => "Job #{$jobId} was not found."]);
        }

        return $this->response->setJSON(array_merge(['status' => 'success'], $detail));
    }

    /**
     * Per-device diff of a job's frozen payload against each target's current config.
     *
     * Reads the payload recorded on the job rather than asking the browser for it, so the diff
     * describes the change that will actually be delivered — including for a job queued hours ago
     * by someone else, which is exactly when a second pair of eyes is worth having.
     */
    public function diff($id = null)
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $jobId = (int) $id;
        if ($jobId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No job was specified.']);
        }

        try {
            $detail = $this->jobService->jobDetail($jobId);
        } catch (\Throwable $e) {
            log_message('error', 'Config job diff read failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'Job detail is unavailable.']);
        }

        if ($detail === null) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => "Job #{$jobId} was not found."]);
        }

        $devices = [];
        foreach ($detail['targets'] as $target) {
            $devices[] = [
                'id'     => (int) $target['pf_id'],
                'name'   => $target['device_name'],
                'man_ip' => $target['man_ip'] ?? null,
            ];
        }

        if ($devices === []) {
            return $this->response->setJSON([
                'status'  => 'success',
                'job_id'  => $jobId,
                'devices' => [],
                'totals'  => ['added' => 0, 'removed' => 0, 'changed' => 0, 'filtered' => 0],
                'tally'   => [],
                'message' => 'This job has no target rows, so there is nothing to compare.',
            ]);
        }

        try {
            $preview = (new ConfigDiffService())->previewForJob($detail['job'], $devices);
            // What this job will do to the device, so the diff is read in the right frame: a merge
            // adds or updates settings, a replace installs the whole document.
            $preview['payload_mode'] = (string) ($detail['job']['payload_mode'] ?? 'replace');
        } catch (\Throwable $e) {
            log_message('error', 'Config job diff failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'Could not build the diff for this job.']);
        }

        // The job's own delivery state is what turns a diff into a decision: "this device already has
        // it" reads very differently from "this device is about to get it".
        $byPfId = [];
        foreach ($detail['targets'] as $target) {
            $byPfId[(int) $target['pf_id']] = $target;
        }

        foreach ($preview['devices'] as $index => $device) {
            $target = $byPfId[$device['device_id']] ?? null;
            $preview['devices'][$index]['target_status'] = $target['status'] ?? null;
        }

        return $this->response->setJSON(array_merge(['status' => 'success'], $preview));
    }

    /**
     * Open drift alerts, for the banner on this page.
     *
     * Read live rather than cached: an alert is the one thing on this page nobody should have to
     * refresh to trust, and it disappears the moment the device matches again.
     */
    public function alerts()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $alerts = new ConfigAlertService();
        // One entry per *story*, not per row: several devices that undid the same change arrive as one
        // pattern alert with its devices inside it. The grouping rule lives in the service, so the banner
        // and the email cannot disagree about what belongs to what.
        $groups = $alerts->openForBanner(50);

        // The rollback that answers a revert alert, read in one query rather than one per row: an alert
        // list is the screen an operator opens *because* something just happened, so it does not get to
        // be slow. The state matters as much as the id — a rollback waiting for approval needs a person,
        // and one already applied does not. A pattern's devices carry their own, so they are collected
        // here too.
        $rollbackIds = [];

        foreach ($groups as $group) {
            foreach (array_merge([$group['alert']], $group['members']) as $row) {
                $id = (int) ($row['rollback_job_id'] ?? 0);

                if ($id > 0) {
                    $rollbackIds[$id] = $id;
                }
            }
        }

        $rollbackStatuses = [];

        if ($rollbackIds !== []) {
            try {
                foreach (db_connect()->table('config_jobs')->select('id, status')->whereIn('id', array_values($rollbackIds))->get()->getResultArray() as $jobRow) {
                    $rollbackStatuses[(int) $jobRow['id']] = (string) $jobRow['status'];
                }
            } catch (\Throwable $e) {
                log_message('error', 'Rollback states could not be read for the alert list: ' . $e->getMessage());
            }
        }

        // The row shape — including the wording of what the rollback is doing — comes from the alert
        // vocabulary rather than being assembled here, so the banner and the email cannot describe the
        // same rollback two different ways. A pattern's devices are shaped the same way as a top-level
        // alert, because they *are* alerts: per-device re-check, repair and resolve all still work.
        $rows = [];

        foreach ($groups as $group) {
            $rows[] = ConfigAlertService::uiRow($group['alert'], $rollbackStatuses) + [
                'members' => array_map(
                    static fn (array $member): array => ConfigAlertService::uiRow($member, $rollbackStatuses),
                    $group['members']
                ),
            ];
        }

        // "Nobody was told" is part of the alert, not a footnote: a drift that could not be emailed has
        // to look different from one that was delivered. Grouped devices are not counted here — they are
        // not owed an email of their own, by design, and their story travels in the row that is.
        $unnotified = 0;

        foreach ($rows as $row) {
            // Owed or failed, not "not sent": a *held* revert is a deliberate five-minute wait by design, and
            // counting it here would report the mechanism that keeps a fleet-wide revert to one message as a
            // mail outage.
            if (in_array($row['notify_state'] ?? '', ['owed', 'failed'], true)) {
                $unnotified++;
            }
        }

        // Two numbers, because they answer different questions and the page needs both: how many alerts to
        // read, and how many *devices* are affected. Grouping must not make the fleet look healthier than
        // it is — six branches down is six branches down, whether it is one alert or six.
        $top = array_column($groups, 'alert');

        return $this->response->setJSON([
            'status'     => 'success',
            'count'      => count($rows),
            'devices'    => ConfigAlertService::deviceCount($top),
            'reverts'    => ConfigAlertService::deviceCount(array_values(array_filter(
                $top,
                static fn (array $alert): bool => ConfigAlertModel::isRevert($alert['kind'] ?? null)
            ))),
            'patterns'   => count(array_filter(
                $top,
                static fn (array $alert): bool => ConfigAlertModel::isPattern($alert['kind'] ?? null)
            )),
            'unnotified' => $unnotified,
            'alerts'     => $rows,
        ]);
    }

    /**
     * An operator resolves a drift alert: they have looked and the difference is intended.
     *
     * Recorded against their name rather than deleted, because "somebody decided this was fine" and
     * "this never happened" are different answers to the question an audit asks.
     */
    public function resolveAlert()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $alertId = (int) $this->request->getPost('alert_id');
        if ($alertId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No alert was specified.']);
        }

        $actor = (string) ($user->username ?? '') !== ''
            ? (string) $user->username
            : ('user #' . (int) ($user->id ?? 0));

        try {
            $result = (new ConfigAlertService())->dismiss($alertId, $actor);
        } catch (\Throwable $e) {
            log_message('error', 'Drift alert could not be resolved: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'The alert could not be resolved.']);
        }

        if (!$result['ok']) {
            return $this->response->setStatusCode(409)->setJSON(['status' => 'error', 'message' => $result['message']]);
        }

        return $this->response->setJSON(['status' => 'success', 'alert_id' => $alertId, 'message' => $result['message']]);
    }

    /**
     * Re-checks ONE device: did the change this job applied actually stick?
     *
     * Deliberately one device per request. Each check opens an SSH connection to a branch, and a
     * request that walked a 39-device fleet would sit there long past any sane PHP timeout — so the
     * button answers for the device in front of the operator, and the `jobs:verify` sweep covers the
     * fleet on cron. A read that cannot be made is recorded as 'unavailable', never as a pass.
     */
    public function verify()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $targetId = (int) $this->request->getPost('target_id');
        if ($targetId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No device target was specified.']);
        }

        $targetModel = new JobTargetModel();
        $target      = $targetModel->find($targetId);

        if (!$target) {
            return $this->response->setStatusCode(404)->setJSON(['status' => 'error', 'message' => "Device target #{$targetId} was not found."]);
        }

        // Only a change that was actually applied has anything to verify; re-checking a failed or
        // still-armed target would answer a question nobody asked.
        if (($target['status'] ?? '') !== JobTargetModel::STATUS_APPLIED) {
            return $this->response->setStatusCode(409)->setJSON([
                'status'  => 'error',
                'message' => "That device is '{$target['status']}', not applied — there is nothing to verify yet.",
            ]);
        }

        $job = (new ConfigJobModel())->find((int) $target['job_id']);
        if (!$job) {
            return $this->response->setStatusCode(409)->setJSON(['status' => 'error', 'message' => 'The job behind that device no longer exists.']);
        }

        $device = (new PfInventoryModel())->find((int) $target['pf_id'])
            ?? ['id' => (int) $target['pf_id'], 'name' => 'device #' . $target['pf_id']];

        try {
            $outcome = (new ConfigVerificationService())->verifyDevice($target, $job, $device);
        } catch (\Throwable $e) {
            log_message('error', 'Manual verification failed for target #' . $targetId . ': ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'The device could not be checked.']);
        }

        return $this->response->setJSON([
            'status'        => 'success',
            'target_id'     => $targetId,
            'verify_status' => $outcome['status'],
            'detail'        => $outcome['detail'],
            'checked_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Re-applies a job's change to the devices that drifted from it.
     *
     * A job id reconciles that one job; no job id reconciles everything currently drifting, one job per
     * source change. Either way a *new* job is created through the normal creation path, so approval and
     * canary apply exactly as they do to a push. A gated change comes back needing its approver, and that
     * is reported rather than quietly dispatched.
     */
    public function reconcile()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $jobId = (int) $this->request->getPost('job_id');
        $actor = (int) ($user->id ?? 0);
        // A drift alert repairs the device it is about, so the repair can be aimed at one branch: a device
        // id narrows the repair, and it is still the service that decides whether that device has drifted.
        $pfId  = (int) ($this->request->getPost('device_id') ?? 0);
        // ...or at a named set of them, which is the fleet-pattern alert's action: it repairs the devices
        // that undid this change while it was watched, and deliberately not the ones that stopped matching
        // it later. Read as a list, because a pattern whose device list grew between the page load and the
        // click must repair what was asked for rather than everything.
        $pfIds = $this->request->getPost('device_ids');

        if (is_string($pfIds)) {
            $pfIds = explode(',', $pfIds);
        }

        try {
            $result = $jobId > 0
                ? $this->jobService->reconcile($jobId, $actor, $pfId > 0 ? $pfId : null, is_array($pfIds) ? $pfIds : null)
                : $this->jobService->reconcileAll($actor);
        } catch (\Throwable $e) {
            log_message('error', 'Config reconcile failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'Could not queue the reconcile.']);
        }

        if (!$result['ok']) {
            return $this->response->setStatusCode(409)->setJSON(['status' => 'error', 'message' => $result['message']]);
        }

        return $this->response->setJSON([
            'status'        => 'success',
            'job_id'        => $result['job_id'] ?? null,
            'devices'       => $result['devices'] ?? 0,
            'approval'      => (bool) ($result['requires_approval'] ?? false),
            'dispatched'    => (bool) ($result['dispatched'] ?? false),
            'message'       => $result['message'],
            // The banner action covers several jobs at once, so its extra detail travels too.
            'queued'        => $result['queued'] ?? [],
            'refusals'      => $result['refusals'] ?? [],
        ]);
    }

    /**
     * Re-queues whatever part of a failed job did not apply, then nudges the runner.
     */
    public function retry()
    {
        $user = auth()->user();
        if (!$user || !$user->can('admin.access')) {
            return $this->response->setStatusCode(403)->setJSON(['status' => 'error', 'message' => 'Unauthorized access.']);
        }

        $jobId = (int) $this->request->getPost('job_id');
        if ($jobId <= 0) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No job was specified.']);
        }

        try {
            $result = $this->jobService->retry($jobId, (int) ($user->id ?? 0));
        } catch (\Throwable $e) {
            log_message('error', 'Config job retry failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON(['status' => 'error', 'message' => 'Could not retry the job.']);
        }

        if (!$result['ok']) {
            return $this->response->setStatusCode(409)->setJSON(['status' => 'error', 'message' => $result['message']]);
        }

        $dispatched = $this->jobService->dispatchRunner($jobId);

        return $this->response->setJSON([
            'status'     => 'success',
            'job_id'     => $jobId,
            'requeued'   => $result['requeued'],
            'dispatched' => $dispatched,
            'message'    => $result['message'] . ($dispatched ? '' : ' Waiting for the `jobs:run` runner (cron).'),
        ]);
    }
}
