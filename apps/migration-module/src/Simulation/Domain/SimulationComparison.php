<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Domain;

final class SimulationComparison
{
    /** @param array<string,mixed> $leaders */
    public function __construct(
        public readonly array $runs,
        public readonly array $leaders,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'runs' => array_map(static fn (SimulationRun $run): array => $run->toArray(), $this->runs),
            'leaders' => $this->leaders,
        ];
    }
}
