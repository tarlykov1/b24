<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

use MigrationModule\Simulation\Domain\SimulationInput;
use MigrationModule\Simulation\Domain\SimulationScenario;

abstract class AbstractEntityEstimator implements EntityCostEstimatorInterface
{
    /** @return array<string,float> */
    abstract protected function baseline(): array;

    /** @return list<string> */
    abstract protected function supportedEntities(): array;

    public function supports(string $entityType): bool
    {
        return in_array($entityType, $this->supportedEntities(), true);
    }

    public function estimate(string $entityType, int $count, SimulationInput $input, SimulationScenario $scenario): array
    {
        $base = $this->baseline();
        $customFieldFactor = 1.0 + (((int) ($input->audit['custom_fields'] ?? 0)) / 1000);
        $workerPressure = 1.0 + max(0, ((int) ($scenario->parameters['worker_count'] ?? 4)) - 8) * 0.03;
        $fileTail = 1.0 + (((float) ($input->audit['large_file_ratio'] ?? 0.0)) * 0.4);

        $read = $count * $base['read_ms'] * $customFieldFactor;
        $transform = $count * $base['transform_ms'] * $customFieldFactor;
        $write = $count * $base['write_ms'] * $workerPressure;
        $verify = $count * $base['verify_ms'] * $fileTail;

        return [
            'entity' => $entityType,
            'count' => $count,
            'read_ms' => $read,
            'transform_ms' => $transform,
            'write_ms' => $write,
            'verify_ms' => $verify,
            'total_ms' => $read + $transform + $write + $verify,
        ];
    }
}
