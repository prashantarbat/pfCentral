<?php

namespace App\Services\ConfigPush;

use App\Models\ConfigPushLogModel;

/**
 * Writes the per-device execution record that the deploy history panel and the
 * rollback button read from (Phase 0). Also used by the rollback path itself.
 */
class PushLogWriter
{
    protected ConfigPushLogModel $model;

    public function __construct(?ConfigPushLogModel $model = null)
    {
        $this->model = $model ?? new ConfigPushLogModel();
    }

    /**
     * Opens a log row before work starts. Returns null if the table is unavailable, so a
     * missing migration can never block a deployment.
     */
    public function start(string $batchId, array $device, string $deviceName, ?string $targetType, string $payloadType, ?int $userId, string $message = 'Deployment started.'): ?int
    {
        try {
            $id = $this->model->insert([
                'batch_id'     => $batchId,
                'pf_id'        => $device['id'] ?? null,
                'device_name'  => $deviceName,
                'target_type'  => $targetType,
                'payload_type' => $payloadType,
                'status'       => 'started',
                'message'      => $message,
                'user_id'      => $userId,
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'config_push_logs insert failed: ' . $e->getMessage());
            return null;
        }

        return $id ? (int) $id : null;
    }

    /**
     * Closes a log row with the outcome and captured log lines.
     */
    public function finish(?int $logId, array $data): void
    {
        if (empty($logId)) {
            return;
        }

        if (isset($data['message'])) {
            $data['message'] = mb_substr((string) $data['message'], 0, 500);
        }

        try {
            $this->model->update($logId, $data);
        } catch (\Throwable $e) {
            log_message('error', 'config_push_logs update failed: ' . $e->getMessage());
        }
    }
}
