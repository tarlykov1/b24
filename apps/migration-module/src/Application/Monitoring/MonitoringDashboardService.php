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
            'entities_skipped' => $status['skipped'] ?? 0,
            'entities_updated' => $status['updated'] ?? 0,
            'entities_failed' => $status['failed'] ?? 0,
            'api_requests' => $metrics['total_requests'] ?? 0,
            'throughput_per_type' => $metrics['throughput_per_type'] ?? [],
            'average_latency' => $metrics['api_latency_ms'] ?? 0.0,
            'retry_count' => $metrics['retries'] ?? 0,
            'rate_limit_hits' => $metrics['rate_limit_hits'] ?? 0,
            'queue_size' => $status['queue_size'] ?? 0,
            'queue_lag' => $status['queue_lag'] ?? 0,
            'estimated_remaining_time' => $status['eta_seconds'] ?? null,
            'batch_avg_ms' => $metrics['batch_avg_ms'] ?? 0,
            'source_load' => $status['source_load'] ?? 'normal',
            'target_load' => $status['target_load'] ?? 'normal',
            'memory_usage_mb' => $metrics['memory_usage_mb'] ?? 0,
            'worker_usage' => $metrics['worker_usage'] ?? 0,
            'file_transfer_stats' => $metrics['file_transfer_stats'] ?? [],
            'success_ratio' => $metrics['success_ratio'] ?? 0,
            'reconciliation_coverage' => $metrics['reconciliation_coverage'] ?? 0,
            'checkpoint' => $status['checkpoint'] ?? null,
            'warnings' => $status['warnings'] ?? [],
            'errors' => $status['errors'] ?? [],
        ];
    }
}
