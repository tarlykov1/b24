<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Domain;

final class SimulationScenario
{
    /** @param array<string,mixed> $parameters */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $migrationMode,
        public readonly array $parameters,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'migration_mode' => $this->migrationMode,
            'parameters' => $this->parameters,
        ];
    }
}
