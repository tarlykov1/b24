<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

interface WindowFitEstimatorInterface
{
    public function fitProbability(float $estimatedHours, float $windowHours): float;
}
