<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

interface DependencyPenaltyEstimatorInterface
{
    /** @param array<string,list<string>> $dependencyGraph @return array<string,float> */
    public function estimatePenalties(array $dependencyGraph): array;
}
