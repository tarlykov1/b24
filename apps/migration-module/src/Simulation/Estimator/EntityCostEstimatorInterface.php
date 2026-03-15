<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

use MigrationModule\Simulation\Domain\SimulationInput;
use MigrationModule\Simulation\Domain\SimulationScenario;

interface EntityCostEstimatorInterface
{
    public function supports(string $entityType): bool;

    /** @return array<string,mixed> */
    public function estimate(string $entityType, int $count, SimulationInput $input, SimulationScenario $scenario): array;
}
