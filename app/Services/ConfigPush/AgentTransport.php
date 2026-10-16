<?php

namespace App\Services\ConfigPush;

use App\Models\JobTargetModel;

/**
 * Device-initiated transport: pfCentral never connects to the branch.
 *
 * This is the answer to the one problem SSH cannot solve — a branch whose WAN tunnels are down is
 * unreachable exactly when you most need to fix it. The device collects its own work over the
 * WireGuard tunnel when it can, so a job simply waits instead of failing.
 *
 * "Delivering" here means *arming*: the target row is moved to `delivered` and the device's next
 * poll (POST /configpush/pickup) claims it. The runner therefore treats this transport as
 * asynchronous (status `pending`) rather than success — the target stays open and the job stays
 * `running` until the device reports its result.
 *
 * Policy stays entirely in the runner: because only the runner arms jobs, approval gating and the
 * canary ordering apply to device-delivered changes exactly as they do to SSH pushes. The pickup
 * endpoint is a courier and makes no scheduling decisions of its own.
 */
class AgentTransport implements ConfigTransportInterface
{
    protected AgentAuthService $auth;
    protected JobTargetModel $targetModel;

    public function __construct(?AgentAuthService $auth = null, ?JobTargetModel $targetModel = null)
    {
        $this->auth        = $auth ?? new AgentAuthService();
        $this->targetModel = $targetModel ?? new JobTargetModel();
    }

    public function name(): string
    {
        return 'agent';
    }

    /**
     * Only for enrolled devices that have polled recently. A silent device returns false, which is
     * what lets the runner fall back to SSH — the tier order is "agent, then SSH", and a device
     * that has gone quiet is exactly when the fallback is needed.
     *
     * The same fallback covers a merge job aimed at an agent too old to merge: an older agent writes
     * the payload straight over config.xml, so handing it a fragment would replace the device's whole
     * configuration with one section. Refusing here sends that job to SSH instead, and leaves replace
     * and script jobs unaffected — a fleet can be updated at its own pace instead of all at once.
     */
    public function supports(array $device, array $job = []): bool
    {
        $pfId = (int) ($device['id'] ?? 0);
        if ($pfId <= 0) {
            return false;
        }

        $agent = $this->auth->agentFor($pfId);

        if (!$this->auth->isOnline($agent)) {
            return false;
        }

        return $this->canCarry($agent, $job);
    }

    /**
     * Whether this agent can carry this job. With no job given, only reachability was being asked.
     */
    public function canCarry(?array $agent, array $job): bool
    {
        if ($job === [] || !$this->isMergeJob($job)) {
            return true;
        }

        return $this->auth->agentCanMerge($agent);
    }

    /**
     * Whether this job is a merge, derived from the payload it will carry — so it cannot disagree
     * with what the device would receive.
     */
    private function isMergeJob(array $job): bool
    {
        return ConfigFragmentMerger::isMergeJob($job);
    }

    /**
     * Arms the job for the device to collect. Returns 'pending', not 'success': nothing has been
     * applied yet, and reporting success here would close the target and mark the job complete
     * before the device had done anything.
     */
    public function deliver(array $device, array $job, callable $emit): array
    {
        $targetId = (int) ($job['target_id'] ?? 0);

        if ($targetId <= 0) {
            return ['status' => 'failed', 'message' => 'Agent delivery needs a target id.', 'ip' => $device['man_ip'] ?? null];
        }

        try {
            $this->targetModel->update($targetId, [
                'status'       => JobTargetModel::STATUS_DELIVERED,
                'delivered_at' => date('Y-m-d H:i:s'),
                'error'        => null,
            ]);
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'message' => 'Could not arm the job for pickup: ' . $e->getMessage(), 'ip' => null];
        }

        $emit('info', 'Armed for the device agent; it collects this on its next poll and reports back.');

        return [
            'status'  => 'pending',
            'message' => 'Armed for pickup by the device agent.',
            'ip'      => $device['man_ip'] ?? null,
        ];
    }

    /**
     * Cannot read a device's configuration, and says so.
     *
     * The agent channel is device-initiated: pfCentral never opens a connection to the branch, which is
     * the entire reason it exists. Verification of an agent-delivered change can therefore only come
     * from the device's own report — and when a device sent none, the honest answer is 'unavailable',
     * not a cheerful 'verified' that nobody actually checked.
     */
    public function readConfig(array $device): array
    {
        return [
            'ok'      => false,
            'contents' => null,
            'message' => 'The agent channel is device-initiated, so pfCentral cannot read this device\'s configuration; '
                . 'only the device\'s own report can confirm what it now runs.',
        ];
    }

    /**
     * Rollback over the agent channel is not implemented: the report endpoint is inbound-only, so
     * pfCentral has no way to hand a restore *to* a device that is currently running the config it
     * needs to revert. Rather than fail quietly, say so precisely — the honest gap beats a
     * rollback button that appears to work.
     */
    public function restore(array $device, array $backup, callable $emit): array
    {
        $message = 'Agent-delivered devices cannot be rolled back over the agent channel yet '
            . '(it is inbound-only); restore this device over SSH or from its own config history.';

        $emit('error', $message);

        return ['status' => 'failed', 'message' => $message, 'ip' => $device['man_ip'] ?? null];
    }
}
