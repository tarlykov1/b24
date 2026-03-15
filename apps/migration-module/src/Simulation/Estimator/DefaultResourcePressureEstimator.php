<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class DefaultResourcePressureEstimator implements ResourcePressureEstimatorInterface
{
    public function estimateSourcePressure(array $entityCosts, int $workers): array
    {
        $readMs = array_sum(array_map(static fn (array $cost): float => (float) ($cost['read_ms'] ?? 0.0), $entityCosts));
        return [
            'db_reads_per_sec' => round(($readMs / 1000) * max(1, $workers) / 120, 2),
            'fs_scan_pressure' => round($workers * 0.9 + count($entityCosts) * 0.2, 2),
            'api_calls_per_sec' => round(max(1, $workers) * 2.1, 2),
        ];
    }

    public function estimateTargetPressure(array $entityCosts, int $workers): array
    {
        $writeMs = array_sum(array_map(static fn (array $cost): float => (float) ($cost['write_ms'] ?? 0.0), $entityCosts));
        return [
            'writes_per_sec' => round(($writeMs / 1000) * max(1, $workers) / 150, 2),
            'file_upload_bandwidth_mbps' => round(max(1, $workers) * 1.8, 2),
            'queue_depth_peak' => (int) ceil(count($entityCosts) * max(1, $workers) * 1.7),
        ];
    }
}
