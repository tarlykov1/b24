<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Domain;

final class SimulationModel
{
    /**
     * @param array<string,array<string,mixed>> $entityCosts
     * @param array<string,float> $dependencyPenalties
     */
    public function __construct(
        public readonly array $entityCosts,
        public readonly array $dependencyPenalties,
    ) {
    }
}
