<?php

declare(strict_types=1);

namespace MigrationModule\Application\Monitoring;

final class MonitoringDashboardService
{
    /** @param array<string,mixed> $status @param array<string,float|int> $metrics */
    public function build(array $status, array $metrics): array
    {
        return [
            'current_stage' => $status['stage'] ?? 'unknown',
            'migration_progress' => $status['progress'] ?? 0,
            'entities_processed' => $status['processed'] ?? 0,
            'entities_failed' => $status['failed'] ?? 0,
            'api_requests' => $metrics['total_requests'] ?? 0,
            'average_latency' => $metrics['api_latency_ms'] ?? 0.0,
            'retry_count' => $metrics['retries'] ?? 0,
            'queue_size' => $status['queue_size'] ?? 0,
            'estimated_remaining_time' => $status['eta_seconds'] ?? null,
            'warnings' => $status['warnings'] ?? [],
            'errors' => $status['errors'] ?? [],
        ];
    }
}
