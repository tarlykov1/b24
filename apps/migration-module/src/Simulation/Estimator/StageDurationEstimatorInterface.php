<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

interface StageDurationEstimatorInterface
{
    /** @param array<string,array<string,mixed>> $entityCosts @return array<string,float> */
    public function estimateStageDurations(array $entityCosts, int $workers): array;
}
