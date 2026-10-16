<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\ConfigPush\AgentAuthService;
use App\Services\ConfigPush\AgentJobService;

/**
 * The device side of the control plane: branch firewalls collect their own work here.
 *
 * This is what makes a push survive a branch that is unreachable — the device pulls when it can
 * instead of pfCentral pushing and failing — and every payload is HMAC-signed with the secret issued
 * at enrolment.
 *
 * Both endpoints are machine-to-machine: no session, no CSRF token (see Config\Filters). They are
 * therefore excluded from the web filters and authenticate with the device's own HMAC instead.
 */
class ConfigAgentController extends BaseController
{
    protected AgentAuthService $auth;
    protected AgentJobService $jobs;

    public function __construct()
    {
        $this->auth = new AgentAuthService();
        $this->jobs = new AgentJobService();
    }

    /**
     * POST /configpush/pickup — "do you have anything for me?"
     */
    public function pickup()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'error', 'message' => $auth['message']]);
        }

        $device = $auth['device'];
        $body   = $auth['body'];

        try {
            $this->auth->touch((int) $device['id'], isset($body['agent_version']) ? (string) $body['agent_version'] : null);

            // Keep-alive first: tell pfCentral this device is still working before handing out more.
            $inFlight = $this->jobs->inFlight((int) $device['id']);
            $claimed  = $this->jobs->claimForDevice($device, $auth['agent']);
            // Verification-window checks this device owes. Rides along on the poll it was already making:
            // a branch pfCentral cannot open a connection to is watched by the device reading itself, on the
            // schedule that lives here (see ConfigVerificationService::WATCH_SCHEDULE).
            $watch    = $this->jobs->claimWatches($device, $auth['agent']);
        } catch (\Throwable $e) {
            log_message('error', 'Agent pickup failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'The job queue is unavailable.',
            ]);
        }

        return $this->response->setJSON([
            'status'      => 'success',
            'now'         => time(),
            'device'      => ['pf_id' => (int) $device['id'], 'name' => $device['name'] ?? null],
            'jobs'        => $claimed,
            'watch'       => $watch,
            'in_progress' => $inFlight,
        ]);
    }

    /**
     * POST /configpush/watchreport — "here is what my own configuration looks like now."
     *
     * The device half of the verification window. On this channel pfCentral never opens a connection to the
     * branch, so a device that is watched at all is watched by reading itself; this is where that read is
     * recorded. The read is one hash and at most one configuration for the whole batch, because every check
     * in it is looking at the same file.
     */
    public function watchReport()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'error', 'message' => $auth['message']]);
        }

        $device = $auth['device'];
        $body   = $auth['body'];

        try {
            $this->auth->touch((int) $device['id'], isset($body['agent_version']) ? (string) $body['agent_version'] : null);
            $result = $this->jobs->watchReport($device, $body);
        } catch (\Throwable $e) {
            log_message('error', 'Agent watch report failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'The watch report could not be recorded.',
            ]);
        }

        // 409 when nothing was due: the device sent a check pfCentral is not waiting for (already answered,
        // not yet due, or belonging to another device), and it should log that rather than retry blindly.
        if (!$result['ok']) {
            return $this->response->setStatusCode(409)->setJSON([
                'status'  => 'error',
                'message' => $result['message'],
                'results' => $result['results'],
            ]);
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => $result['message'],
            'results' => $result['results'],
        ]);
    }

    /**
     * POST /configpush/jobresult — "here is what happened."
     */
    public function report()
    {
        $auth = $this->authenticate();
        if (!$auth['ok']) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'error', 'message' => $auth['message']]);
        }

        $device = $auth['device'];
        $body   = $auth['body'];

        try {
            $this->auth->touch((int) $device['id'], isset($body['agent_version']) ? (string) $body['agent_version'] : null);
            $result = $this->jobs->report($device, $body);
        } catch (\Throwable $e) {
            log_message('error', 'Agent job report failed: ' . $e->getMessage());

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'The result could not be recorded.',
            ]);
        }

        // 409 rather than 200: the device sent something it was not entitled to record (already
        // reported, reclaimed, or never in flight). The agent should log it and not blindly retry.
        if (!$result['ok']) {
            return $this->response->setStatusCode(409)->setJSON(['status' => 'error', 'message' => $result['message']]);
        }

        return $this->response->setJSON([
            'status'        => 'success',
            'message'       => $result['message'],
            'target_status' => $result['target_status'],
            'job_status'    => $result['job_status'],
            'backup_file'   => $result['backup_file'],
        ]);
    }

    /**
     * Verifies the device's HMAC signature over the exact bytes it sent.
     *
     * @return array{ok:bool, message:string, device:?array, agent:?array, body:array}
     */
    private function authenticate(): array
    {
        $rawBody = (string) $this->request->getBody();
        $path    = '/' . ltrim($this->request->getUri()->getPath(), '/');

        $body = json_decode($rawBody, true);
        if (!is_array($body)) {
            return ['ok' => false, 'message' => 'A JSON body is required.', 'device' => null, 'agent' => null, 'body' => []];
        }

        // The signature is a header, not a body field: it must not be part of the bytes it covers.
        $result = $this->auth->authenticate('POST', $path, $rawBody, $body, $this->request->getHeaderLine('X-Device-Signature'));

        return array_merge($result, ['body' => $body]);
    }
}
