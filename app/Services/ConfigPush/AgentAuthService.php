<?php

namespace App\Services\ConfigPush;

use App\Models\DeviceAgentModel;
use App\Models\PfInventoryModel;

/**
 * Mutual HMAC authentication between pfCentral and a branch device.
 *
 * Inbound (device -> pfCentral): the device signs the request with the secret issued at enrolment, so a
 * leaked *key* alone cannot collect jobs — unlike the existing /endpoint telemetry, which trusts
 * the bare device_key in the body and auto-creates an inventory row for unknown keys. A control
 * plane that hands out root-level config changes must not work that way, so:
 *
 *   1. the key must be explicitly enrolled (no auto-enrolment),
 *   2. the request must carry a fresh timestamp and an HMAC over it, and
 *   3. the device must exist in inventory.
 *
 * Outbound (pfCentral -> device): every job payload is signed with the same secret and carries an expiry,
 * so a device can refuse anything it cannot attribute to pfCentral, and a captured response cannot be
 * replayed later.
 *
 * Canonical string (line-based, so no JSON canonicalisation is needed):
 *
 *   METHOD \n path \n device_key \n timestamp \n sha256(raw body)
 *
 * The signature travels in the X-Device-Signature header, NOT in the body: a signature that is
 * part of the body it covers can never be made to agree, because the hash is taken before the
 * signature exists on one side and after it arrives on the other.
 */
class AgentAuthService
{
    /** How far a device clock may drift from pfCentral before a request is refused. */
    public const TIMESTAMP_SKEW = 300;

    /** How long a signed job payload stays valid after it is issued. */
    public const JOB_TTL = 900;

    /** How long after its last poll a device is still considered reachable. */
    public const ONLINE_WINDOW = 300;

    /**
     * The agent version that can merge a section fragment into the live config.
     *
     * This exists because of what an older agent would do with a fragment: its apply step writes the
     * payload straight over /cf/conf/config.xml, so a section would *replace* the whole configuration
     * rather than merge into it. A merge job is therefore never armed for a device below this floor —
     * it falls through to SSH, which merges on pfCentral side — while replace and script jobs keep
     * flowing to older agents untouched. Failing over beats failing open.
     */
    public const MERGE_MIN_VERSION = '2.1';

    /**
     * The agent version that can watch its own configuration.
     *
     * Below this an agent still polls and applies jobs, but it does not understand the watch checks the
     * pfCentral hands it, so a device with no SSH reader must not be given a verification window: the window
     * would exclude the hourly sweep for an hour and produce no reads at all, which is the one outcome
     * worse than not watching. Such a device keeps the sweep, which records "could not confirm" honestly.
     *
     * An unknown version counts as too old, exactly as it does for merge.
     */
    public const WATCH_MIN_VERSION = '2.2';

    protected DeviceAgentModel $agentModel;
    protected PfInventoryModel $inventoryModel;

    public function __construct(?DeviceAgentModel $agentModel = null, ?PfInventoryModel $inventoryModel = null)
    {
        $this->agentModel     = $agentModel ?? new DeviceAgentModel();
        $this->inventoryModel = $inventoryModel ?? new PfInventoryModel();
    }

    /**
     * Verifies an inbound device request.
     *
     * @param array  $body      decoded JSON body (device_key, timestamp, ...)
     * @param string $signature value of the X-Device-Signature header
     *
     * @return array{ok:bool, message:string, device:?array, agent:?array}
     */
    public function authenticate(string $method, string $path, string $rawBody, array $body, string $signature = ''): array
    {
        $deviceKey = trim((string) ($body['device_key'] ?? ''));
        $timestamp = trim((string) ($body['timestamp'] ?? ''));
        $signature = trim($signature);

        if ($deviceKey === '' || $timestamp === '' || $signature === '') {
            return $this->deny('device_key and timestamp in the body, plus an X-Device-Signature header, are all required.');
        }

        if (!ctype_digit($timestamp)) {
            return $this->deny('timestamp must be a Unix timestamp.');
        }

        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_SKEW) {
            return $this->deny('Request timestamp is outside the allowed window; check the device clock.');
        }

        try {
            $agent = $this->agentModel->where('device_key', $deviceKey)->first();
        } catch (\Throwable $e) {
            log_message('error', 'device_agents lookup failed: ' . $e->getMessage());

            return $this->deny('Device enrolment could not be read.');
        }

        // No auto-enrolment: an unenrolled key gets nothing, and the message says how to enrol.
        if (!$agent) {
            return $this->deny('This device is not enrolled for agent-delivered jobs.');
        }

        if (empty($agent['auth_token'])) {
            return $this->deny('This device has no secret; re-enrol it to issue one.');
        }

        $expected = self::sign((string) $agent['auth_token'], self::canonical($method, $path, $deviceKey, $timestamp, $rawBody));

        if (!hash_equals($expected, $signature)) {
            return $this->deny('Signature verification failed.');
        }

        $device = $this->inventoryModel->find($agent['pf_id']);
        if (!$device) {
            return $this->deny('The enrolled device is no longer in inventory.');
        }

        return ['ok' => true, 'message' => 'Authenticated.', 'device' => $device, 'agent' => $agent];
    }

    /**
     * @return array{ok:bool, message:string, device:?array, agent:?array}
     */
    private function deny(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'device' => null, 'agent' => null];
    }

    public function canonical(string $method, string $path, string $deviceKey, string $timestamp, string $rawBody): string
    {
        return strtoupper($method) . "\n"
            . $path . "\n"
            . $deviceKey . "\n"
            . $timestamp . "\n"
            . hash('sha256', $rawBody);
    }

    public static function sign(string $secret, string $canonical): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }

    /**
     * Wraps a job payload with an expiry and a signature the device verifies before applying it.
     *
     * The device id is inside the signed string, so a payload signed for one firewall cannot be
     * replayed onto another.
     *
     * @param array $agent the device_agents row (its secret)
     * @param array $job   job_id, target_id, payload_type, payload
     *
     * @return array{signature:string, expires_at:int, payload_sha256:string}
     */
    public function sealJob(array $agent, array $job, ?int $ttl = null): array
    {
        $payloadSha = hash('sha256', (string) ($job['payload'] ?? ''));
        $expiresAt  = time() + ($ttl ?? self::JOB_TTL);

        $canonical = self::jobCanonical(
            (int) $agent['pf_id'],
            (int) $job['target_id'],
            (int) $job['job_id'],
            $payloadSha,
            $expiresAt
        );

        return [
            'signature'      => self::sign((string) $agent['auth_token'], $canonical),
            'expires_at'     => $expiresAt,
            'payload_sha256' => $payloadSha,
        ];
    }

    public static function jobCanonical(int $pfId, int $targetId, int $jobId, string $payloadSha, int $expiresAt): string
    {
        return implode(':', [$pfId, $targetId, $jobId, $payloadSha, $expiresAt]);
    }

    /**
     * Records that the device checked in, which is also how the runner decides whether to arm a
     * job for pickup instead of falling back to SSH.
     */
    public function touch(int $pfId, ?string $agentVersion = null): void
    {
        $data = ['last_seen_at' => date('Y-m-d H:i:s')];

        if ($agentVersion !== null && $agentVersion !== '') {
            $data['agent_version'] = mb_substr($agentVersion, 0, 20);
        }

        try {
            $this->agentModel->update($pfId, $data);
        } catch (\Throwable $e) {
            log_message('error', 'device_agents touch failed: ' . $e->getMessage());
        }
    }

    /**
     * The enrolment row for a device, or null.
     */
    public function agentFor(int $pfId): ?array
    {
        try {
            return $this->agentModel->find($pfId) ?: null;
        } catch (\Throwable $e) {
            log_message('error', 'device_agents lookup failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Whether this device's agent is new enough to merge a fragment on-device.
     *
     * An unknown version counts as "too old": a device that has never reported one cannot be assumed
     * to run software that does not exist yet, and the cost of being wrong in this direction is a
     * fallback to SSH rather than a wiped configuration.
     */
    public function agentCanMerge(?array $agent): bool
    {
        $version = trim((string) ($agent['agent_version'] ?? ''));

        if ($version === '') {
            return false;
        }

        return version_compare($version, self::MERGE_MIN_VERSION, '>=');
    }

    /**
     * Whether this device's agent is new enough to read its own configuration on a schedule.
     *
     * Same direction as `agentCanMerge`: an unknown version is "too old", because a device that has never
     * reported one cannot be assumed to run software that does not exist yet.
     */
    public function agentCanWatch(?array $agent): bool
    {
        $version = trim((string) ($agent['agent_version'] ?? ''));

        if ($version === '') {
            return false;
        }

        return version_compare($version, self::WATCH_MIN_VERSION, '>=');
    }

    /**
     * Signs one verification-window check so a device can attribute it to pfCentral.
     *
     * A check changes nothing, so this is not the same protection a job payload needs. It is here because
     * the direction of trust on this channel has one rule — a device acts on nothing it cannot attribute
     * to pfCentral — and an exception for "harmless" instructions is how that rule stops being true.
     *
     * @return array{checks:list<array>} checks with a signature added to each
     */
    public function sealWatches(array $agent, array $checks): array
    {
        $pfId   = (int) $agent['pf_id'];
        $sealed = [];

        foreach ($checks as $check) {
            $canonical = self::watchCanonical($pfId, (int) $check['target_id'], (int) $check['slot']);

            $check['signature'] = self::sign((string) $agent['auth_token'], $canonical);

            $sealed[] = $check;
        }

        return ['checks' => $sealed];
    }

    public static function watchCanonical(int $pfId, int $targetId, int $slot): string
    {
        return implode(':', ['watch', $pfId, $targetId, $slot]);
    }

    public function isOnline(?array $agent, ?int $now = null): bool
    {
        if (!$agent || empty($agent['last_seen_at'])) {
            return false;
        }

        $seen = strtotime((string) $agent['last_seen_at']);
        if ($seen === false) {
            return false;
        }

        return (($now ?? time()) - $seen) <= self::ONLINE_WINDOW;
    }
}
