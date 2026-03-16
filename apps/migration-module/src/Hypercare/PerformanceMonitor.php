<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class PerformanceMonitor
{
    public function monitor(array $metrics, array $thresholds = []): array
    {
        $thresholds = array_merge([
            'rest_latency_ms' => 1000,
            'db_query_latency_ms' => 500,
            'file_download_latency_ms' => 2000,
            'worker_queue_delay_ms' => 1500,
            'api_rate_limit_utilization_pct' => 90,
        ], $thresholds);

        $stored = [];
        $alerts = [];
        foreach ($metrics as $metricName => $value) {
            $stored[] = ['metric_name' => $metricName, 'metric_value' => $value, 'captured_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM)];
            if (isset($thresholds[$metricName]) && $value > $thresholds[$metricName]) {
                $alerts[] = [
                    'metric_name' => $metricName,
                    'threshold' => $thresholds[$metricName],
                    'value' => $value,
                    'severity' => $value > ($thresholds[$metricName] * 1.5) ? 'critical' : 'warning',
                ];
            }
        }

        return ['performance_metrics' => $stored, 'alerts' => $alerts];
    }
}
