<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class DefaultDependencyPenaltyEstimator implements DependencyPenaltyEstimatorInterface
{
    public function estimatePenalties(array $dependencyGraph): array
    {
        $penalties = [];
        foreach ($dependencyGraph as $node => $deps) {
            $penalties[$node] = 1.0 + (count($deps) * 0.08);
        }
        return $penalties;
    }
}
