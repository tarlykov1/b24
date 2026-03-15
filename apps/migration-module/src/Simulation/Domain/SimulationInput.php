<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Domain;

final class SimulationInput
{
    /**
     * @param array<string,int> $entityVolumes
     * @param array<string,mixed> $audit
     * @param array<string,list<string>> $dependencyGraph
     * @param array<string,mixed> $capabilities
     * @param array<string,mixed> $policies
     * @param array<string,mixed> $runtimeMetrics
     */
    public function __construct(
        public readonly array $entityVolumes,
        public readonly array $audit,
        public readonly array $dependencyGraph,
        public readonly array $capabilities,
        public readonly array $policies,
        public readonly array $runtimeMetrics = [],
    ) {
    }
}
