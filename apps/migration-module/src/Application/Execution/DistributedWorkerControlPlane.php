<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution;

use MigrationModule\Application\Throttling\AdaptiveRateLimiter;
use MigrationModule\Prototype\Storage\MySqlStorage;

final class DistributedWorkerControlPlane
{
    private const DEFAULT_LEASE_TTL_SECONDS = 45;
    private const MAX_RETRY_ATTEMPTS = 3;

    public function __construct(
        private readonly MySqlStorage $storage,
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
            'worker_pool' => [],
            'job_queue' => [],
            'entity_queue' => [],
            'dead_letter_queue' => [],
            'worker_leases' => [],
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

            $state['worker_pool'][$workerId] = [
                'status' => 'running',
                'queue' => $assignedQueue,
                'utilization' => 0,
                'last_heartbeat_at' => date(DATE_ATOM),
            ];
        }

        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function enqueueJob(string $jobId, string $queue, array $payload): array
    {
        $state = $this->hydratedState($jobId);
        $state['job_queue'][] = [
            'id' => 'jq_' . bin2hex(random_bytes(4)),
            'queue' => $queue,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => date(DATE_ATOM),
        ];

        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function enqueueEntity(string $jobId, string $entityType, string $sourceId, array $payload): array
    {
        $state = $this->hydratedState($jobId);
        $state['entity_queue'][] = [
            'id' => 'eq_' . bin2hex(random_bytes(4)),
            'entity_type' => $entityType,
            'source_id' => $sourceId,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => date(DATE_ATOM),
        ];

        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function leaseNextEntity(string $jobId, string $workerId, int $leaseTtlSeconds = self::DEFAULT_LEASE_TTL_SECONDS): array
    {
        $state = $this->hydratedState($jobId);
        $state = $this->recoverExpiredLeasesInState($state, $leaseTtlSeconds);

        foreach ($state['entity_queue'] as $index => $item) {
            if (($item['status'] ?? 'pending') !== 'pending') {
                continue;
            }

            if (($state['worker_pool'][$workerId]['status'] ?? 'running') === 'paused') {
                break;
            }

            $leaseId = 'lease_' . bin2hex(random_bytes(4));
            $now = date(DATE_ATOM);
            $expiresAt = date(DATE_ATOM, time() + $leaseTtlSeconds);

            $state['entity_queue'][$index]['status'] = 'leased';
            $state['entity_queue'][$index]['lease_id'] = $leaseId;
            $state['entity_queue'][$index]['leased_to'] = $workerId;
            $state['entity_queue'][$index]['leased_at'] = $now;

            $state['worker_leases'][$leaseId] = [
                'lease_id' => $leaseId,
                'worker_id' => $workerId,
                'entity_id' => $item['id'],
                'expires_at' => $expiresAt,
                'acquired_at' => $now,
            ];

            $state['worker_pool'][$workerId]['utilization'] = 1;
            $state['worker_pool'][$workerId]['last_heartbeat_at'] = $now;
            $this->storage->saveDistributedControlPlaneState($jobId, $state);

            return ['leased' => $state['entity_queue'][$index]] + $this->status($jobId);
        }

        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return ['leased' => null] + $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function completeLease(string $jobId, string $leaseId, bool $success, ?string $error = null): array
    {
        $state = $this->hydratedState($jobId);
        $lease = $state['worker_leases'][$leaseId] ?? null;
        if (!is_array($lease)) {
            return $this->status($jobId);
        }

        foreach ($state['entity_queue'] as $index => $entity) {
            if (($entity['id'] ?? '') !== ($lease['entity_id'] ?? '')) {
                continue;
            }

            $attempts = (int) ($entity['attempts'] ?? 0);

            if ($success) {
                $state['entity_queue'][$index]['status'] = 'done';
            } else {
                $attempts++;
                $state['entity_queue'][$index]['attempts'] = $attempts;
                $state['entity_queue'][$index]['last_error'] = $error;
                $state['entity_queue'][$index]['status'] = $attempts >= self::MAX_RETRY_ATTEMPTS ? 'dead_letter' : 'pending';

                if ($state['entity_queue'][$index]['status'] === 'dead_letter') {
                    $state['dead_letter_queue'][] = $state['entity_queue'][$index];
                }
            }

            $state['entity_queue'][$index]['lease_id'] = null;
            $state['entity_queue'][$index]['leased_to'] = null;
            break;
        }

        $workerId = (string) ($lease['worker_id'] ?? '');
        if ($workerId !== '' && isset($state['worker_pool'][$workerId])) {
            $state['worker_pool'][$workerId]['utilization'] = 0;
        }

        unset($state['worker_leases'][$leaseId]);

        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function pauseWorker(string $jobId, string $workerId): array
    {
        $state = $this->hydratedState($jobId);
        $state['worker_pool'][$workerId]['status'] = 'paused';
        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function resumeWorker(string $jobId, string $workerId): array
    {
        $state = $this->hydratedState($jobId);
        $state['worker_pool'][$workerId]['status'] = 'running';
        $this->storage->saveDistributedControlPlaneState($jobId, $state);

        return $this->status($jobId);
    }

    /** @return array<string,mixed> */
    public function recoverExpiredLeases(string $jobId, int $leaseTtlSeconds = self::DEFAULT_LEASE_TTL_SECONDS): array
    {
        $state = $this->hydratedState($jobId);
        $state = $this->recoverExpiredLeasesInState($state, $leaseTtlSeconds);
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
        $state['worker_pool'][$workerId]['last_heartbeat_at'] = date(DATE_ATOM);

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
            'queue_metrics' => [
                'job_queue_depth' => count(array_filter($state['job_queue'], static fn (array $item): bool => ($item['status'] ?? 'pending') === 'pending')),
                'entity_queue_depth' => count(array_filter($state['entity_queue'], static fn (array $item): bool => in_array(($item['status'] ?? 'pending'), ['pending', 'leased'], true))),
                'dead_letter_depth' => count($state['dead_letter_queue']),
                'worker_utilization' => $this->workerUtilization($state),
            ],
            'throttling' => [
                'source_rpm' => $this->rateLimiter->currentRpm('source'),
                'target_rpm' => $this->rateLimiter->currentRpm('target'),
                'source_sleep_ms' => $this->rateLimiter->recommendedSleepMs('source'),
                'target_sleep_ms' => $this->rateLimiter->recommendedSleepMs('target'),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function hydratedState(string $jobId): array
    {
        return $this->storage->distributedControlPlaneState($jobId) ?? [
            'status' => 'running',
            'paused_reason' => null,
            'queue_retries' => [],
            'assignments' => [],
            'worker_pool' => [],
            'job_queue' => [],
            'entity_queue' => [],
            'dead_letter_queue' => [],
            'worker_leases' => [],
        ];
    }

    /** @param array<string,mixed> $state
     * @return array<string,mixed>
     */
    private function recoverExpiredLeasesInState(array $state, int $leaseTtlSeconds): array
    {
        $threshold = time() - $leaseTtlSeconds;

        foreach ($state['worker_leases'] as $leaseId => $lease) {
            $acquiredAt = strtotime((string) ($lease['acquired_at'] ?? 'now'));
            if ($acquiredAt > $threshold) {
                continue;
            }

            foreach ($state['entity_queue'] as $index => $entity) {
                if (($entity['id'] ?? '') !== ($lease['entity_id'] ?? '')) {
                    continue;
                }

                if (($entity['status'] ?? '') === 'leased') {
                    $state['entity_queue'][$index]['status'] = 'pending';
                    $state['entity_queue'][$index]['lease_id'] = null;
                    $state['entity_queue'][$index]['leased_to'] = null;
                }
            }

            $workerId = (string) ($lease['worker_id'] ?? '');
            if ($workerId !== '' && isset($state['worker_pool'][$workerId])) {
                $state['worker_pool'][$workerId]['utilization'] = 0;
            }
            unset($state['worker_leases'][$leaseId]);
        }

        return $state;
    }

    /** @param array<string,mixed> $state */
    private function workerUtilization(array $state): float
    {
        $pool = (array) ($state['worker_pool'] ?? []);
        if ($pool === []) {
            return 0.0;
        }

        $busy = 0;
        foreach ($pool as $worker) {
            $busy += ((int) ($worker['utilization'] ?? 0)) > 0 ? 1 : 0;
        }

        return round($busy / max(1, count($pool)), 3);
    }
}
