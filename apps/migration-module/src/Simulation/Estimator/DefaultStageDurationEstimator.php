<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class DefaultStageDurationEstimator implements StageDurationEstimatorInterface
{
    public function estimateStageDurations(array $entityCosts, int $workers): array
    {
        $workers = max(1, $workers);
        $degradation = 1.0 + max(0, $workers - 10) * 0.04;
        $durations = [];
        foreach ($entityCosts as $entity => $cost) {
            $durations[$entity] = (($cost['total_ms'] ?? 0.0) / 1000 / 3600 / $workers) * $degradation;
        }

        return $durations;
    }
}
