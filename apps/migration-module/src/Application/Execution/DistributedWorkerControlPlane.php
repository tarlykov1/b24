<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution;

use MigrationModule\Application\Throttling\AdaptiveRateLimiter;
use MigrationModule\Prototype\Storage\SqliteStorage;

final class DistributedWorkerControlPlane
{
    public function __construct(
        private readonly SqliteStorage $storage,
        private readonly AdaptiveRateLimiter $rateLimiter,
    ) {
    }

    /**
     * @param array<int,string> $queues
     * @param array<int,string> $workerIds
     * @return array<string,mixed>
     */
    public function bootstrap(string $jobId, array $queues, array $workerIds): array
    {
        $state = $this->storage->distributedControlPlaneState($jobId) ?? [
            'status' => 'running',
            'paused_reason' => null,
            'queue_retries' => [],
            'assignments' => [],
        ];

        foreach ($workerIds as $index => $workerId) {
            $assignedQueue = $queues[$index % max(1, count($queues))] ?? null;
            if ($assignedQueue === null) {
                continue;
            }

            $state['assignments'][$workerId] = [
                'queue' => $assignedQueue,
                'lease_status' => 'active',
                'last_heartbeat_at' => date(DATE_ATOM),
            ];
        }

        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function pause(string $jobId, string $reason = 'operator_request'): array
    {
        $state = $this->storage->distributedControlPlaneState($jobId) ?? [];
        $state['status'] = 'paused';
        $state['paused_reason'] = $reason;
        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function resume(string $jobId): array
    {
        $state = $this->storage->distributedControlPlaneState($jobId) ?? [];
        $state['status'] = 'running';
        $state['paused_reason'] = null;
        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function retryQueue(string $jobId, string $queueName): array
    {
        $state = $this->storage->distributedControlPlaneState($jobId) ?? [
            'status' => 'running',
            'paused_reason' => null,
            'queue_retries' => [],
            'assignments' => [],
        ];

        $current = (int) ($state['queue_retries'][$queueName] ?? 0);
        $state['queue_retries'][$queueName] = $current + 1;

        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function heartbeat(string $jobId, string $workerId, bool $success, int $statusCode = 0): array
    {
        $state = $this->storage->distributedControlPlaneState($jobId) ?? null;
        if ($state === null || ($state['status'] ?? 'running') === 'paused') {
            return $this->status($jobId);
        }

        if (!isset($state['assignments'][$workerId])) {
            $state['assignments'][$workerId] = [
                'queue' => 'unassigned',
                'lease_status' => 'active',
                'last_heartbeat_at' => date(DATE_ATOM),
            ];
        }

        $state['assignments'][$workerId]['last_heartbeat_at'] = date(DATE_ATOM);

        if ($success) {
            $this->rateLimiter->registerSuccess('source');
            $this->rateLimiter->registerSuccess('target');
        } else {
            $this->rateLimiter->registerFailure('source', $statusCode);
            $this->rateLimiter->registerFailure('target', $statusCode);
        }

        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function status(string $jobId): array
    {
        $state = $this->storage->distributedControlPlaneState($jobId) ?? [
            'status' => 'not_initialized',
            'paused_reason' => null,
            'queue_retries' => [],
            'assignments' => [],
        ];

        return [
            'job_id' => $jobId,
            'control_plane' => $state,
            'throttling' => [
                'source_rpm' => $this->rateLimiter->currentRpm('source'),
                'target_rpm' => $this->rateLimiter->currentRpm('target'),
                'source_sleep_ms' => $this->rateLimiter->recommendedSleepMs('source'),
                'target_sleep_ms' => $this->rateLimiter->recommendedSleepMs('target'),
            ],
        ];
    }
}
