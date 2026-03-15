<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Scenario;

use MigrationModule\Simulation\Domain\SimulationInput;
use MigrationModule\Simulation\Domain\SimulationScenario;

final class ScenarioBuilder
{
    /** @return list<SimulationScenario> */
    public function buildCandidates(SimulationInput $input): array
    {
        return [
            $this->preset('SafeSource', 'low-impact background sync', ['worker_count' => 4, 'verify_depth' => 'full', 'file_strategy' => 'files-last', 'strictness' => 'high', 'window_hours' => 10.0]),
            $this->preset('Balanced', 'phased migration', ['worker_count' => 8, 'verify_depth' => 'normal', 'file_strategy' => 'parallel', 'strictness' => 'medium', 'window_hours' => 8.0]),
            $this->preset('FastCutover', 'weekend cutover', ['worker_count' => 14, 'verify_depth' => 'sampled', 'file_strategy' => 'aggressive', 'strictness' => 'medium', 'window_hours' => 8.0]),
            $this->preset('LowNightImpact', 'overnight limited window', ['worker_count' => 6, 'verify_depth' => 'normal', 'file_strategy' => 'files-last', 'strictness' => 'high', 'window_hours' => 6.0]),
            $this->preset('WeekendFull', 'full initial migration', ['worker_count' => 12, 'verify_depth' => 'normal', 'file_strategy' => 'parallel', 'strictness' => 'medium', 'window_hours' => 16.0]),
            $this->preset('IncrementalCatchUp', 'catch-up sync before cutover', ['worker_count' => 5, 'verify_depth' => 'delta', 'file_strategy' => 'delta-only', 'strictness' => 'high', 'window_hours' => 4.0]),
            $this->preset('HighIntegrity', 'verify-only pass', ['worker_count' => 6, 'verify_depth' => 'full', 'file_strategy' => 'conservative', 'strictness' => 'strict', 'window_hours' => 12.0]),
            $this->preset('FilesLast', 'entity-grouped migration', ['worker_count' => 7, 'verify_depth' => 'normal', 'file_strategy' => 'files-last', 'strictness' => 'medium', 'window_hours' => 8.0]),
            $this->preset('CRMFirst', 'phased migration', ['worker_count' => 8, 'verify_depth' => 'normal', 'file_strategy' => 'parallel', 'strictness' => 'medium', 'window_hours' => 8.0, 'crm_priority' => true]),
        ];
    }

    /** @param array<string,mixed> $overrides */
    public function custom(string $name, string $mode, array $overrides): SimulationScenario
    {
        return $this->preset($name, $mode, $overrides);
    }

    /** @param array<string,mixed> $params */
    private function preset(string $name, string $mode, array $params): SimulationScenario
    {
        return new SimulationScenario(
            strtolower($name) . '_' . substr(sha1(json_encode($params)), 0, 8),
            $name,
            $mode,
            array_merge([
                'throttle_qps' => 80,
                'batch_size' => 100,
                'max_retry' => 3,
                'conflict_rate' => 0.04,
            ], $params),
        );
    }
}
