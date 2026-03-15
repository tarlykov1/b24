<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class DefaultRetryImpactEstimator implements RetryImpactEstimatorInterface
{
    public function retryMultiplier(float $baseConflictRate, int $maxRetry): float
    {
        return 1.0 + ($baseConflictRate * min(6, max(1, $maxRetry)) * 0.5);
    }
}
