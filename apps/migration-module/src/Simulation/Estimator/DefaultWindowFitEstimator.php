<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class DefaultWindowFitEstimator implements WindowFitEstimatorInterface
{
    public function fitProbability(float $estimatedHours, float $windowHours): float
    {
        if ($windowHours <= 0.0) {
            return 0.0;
        }

        if ($estimatedHours <= $windowHours) {
            return max(0.5, 1.0 - (($windowHours - $estimatedHours) / max($windowHours, 1.0) * 0.2));
        }

        return max(0.0, 1.0 - (($estimatedHours - $windowHours) / $windowHours));
    }
}
