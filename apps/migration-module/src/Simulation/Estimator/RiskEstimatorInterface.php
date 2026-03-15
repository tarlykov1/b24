<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

interface RiskEstimatorInterface
{
    /** @param array<string,mixed> $context @return array<string,float> */
    public function score(array $context): array;
}
