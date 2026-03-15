<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

interface ResourcePressureEstimatorInterface
{
    /** @param array<string,array<string,mixed>> $entityCosts @return array<string,mixed> */
    public function estimateSourcePressure(array $entityCosts, int $workers): array;

    /** @param array<string,array<string,mixed>> $entityCosts @return array<string,mixed> */
    public function estimateTargetPressure(array $entityCosts, int $workers): array;
}
