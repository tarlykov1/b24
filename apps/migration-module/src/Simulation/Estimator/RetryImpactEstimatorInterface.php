<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

interface RetryImpactEstimatorInterface
{
    public function retryMultiplier(float $baseConflictRate, int $maxRetry): float;
}
