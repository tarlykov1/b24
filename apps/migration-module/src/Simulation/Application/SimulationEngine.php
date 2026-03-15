<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Application;

use MigrationModule\Simulation\Domain\SimulationComparison;
use MigrationModule\Simulation\Domain\SimulationInput;
use MigrationModule\Simulation\Domain\SimulationModel;
use MigrationModule\Simulation\Domain\SimulationRun;
use MigrationModule\Simulation\Domain\SimulationScenario;
use MigrationModule\Simulation\Estimator\DefaultDependencyPenaltyEstimator;
use MigrationModule\Simulation\Estimator\DefaultResourcePressureEstimator;
use MigrationModule\Simulation\Estimator\DefaultRetryImpactEstimator;
use MigrationModule\Simulation\Estimator\DefaultRiskEstimator;
use MigrationModule\Simulation\Estimator\DefaultStageDurationEstimator;
use MigrationModule\Simulation\Estimator\DefaultWindowFitEstimator;
use MigrationModule\Simulation\Estimator\EntityCostEstimatorInterface;

final class SimulationEngine
{
    /** @param list<EntityCostEstimatorInterface> $entityEstimators */
    public function __construct(
        private readonly array $entityEstimators,
        private readonly DefaultStageDurationEstimator $stageDurationEstimator = new DefaultStageDurationEstimator(),
        private readonly DefaultDependencyPenaltyEstimator $dependencyPenaltyEstimator = new DefaultDependencyPenaltyEstimator(),
        private readonly DefaultRetryImpactEstimator $retryImpactEstimator = new DefaultRetryImpactEstimator(),
        private readonly DefaultResourcePressureEstimator $resourcePressureEstimator = new DefaultResourcePressureEstimator(),
        private readonly DefaultRiskEstimator $riskEstimator = new DefaultRiskEstimator(),
        private readonly DefaultWindowFitEstimator $windowFitEstimator = new DefaultWindowFitEstimator(),
        private readonly RecommendationEngine $recommendationEngine = new RecommendationEngine(),
    ) {
    }

    public function run(SimulationInput $input, SimulationScenario $scenario): SimulationRun
    {
        $model = $this->buildModel($input, $scenario);
        $workers = (int) ($scenario->parameters['worker_count'] ?? 8);
        $stageDurations = $this->stageDurationEstimator->estimateStageDurations($model->entityCosts, $workers);

        foreach ($model->dependencyPenalties as $entity => $penalty) {
            if (isset($stageDurations[$entity])) {
                $stageDurations[$entity] *= $penalty;
            }
        }

        $retryMultiplier = $this->retryImpactEstimator->retryMultiplier(
            (float) ($scenario->parameters['conflict_rate'] ?? 0.04),
            (int) ($scenario->parameters['max_retry'] ?? 3)
        );

        $stageDurations = array_map(static fn (float $hours): float => round($hours * $retryMultiplier, 3), $stageDurations);

        $totalDuration = array_sum($stageDurations);
        $sourcePressure = $this->resourcePressureEstimator->estimateSourcePressure($model->entityCosts, $workers);
        $targetPressure = $this->resourcePressureEstimator->estimateTargetPressure($model->entityCosts, $workers);

        $windowFit = $this->windowFitEstimator->fitProbability($totalDuration, (float) ($scenario->parameters['window_hours'] ?? 8.0));
        $risk = $this->riskEstimator->score([
            'source_load' => $sourcePressure,
            'window_fit_probability' => $windowFit,
            'conflict_rate' => (float) ($scenario->parameters['conflict_rate'] ?? 0.04),
            'custom_fields' => (int) ($input->audit['custom_fields'] ?? 0),
        ]);

        $criticalPath = array_keys($stageDurations);
        usort($criticalPath, static fn (string $a, string $b): int => $stageDurations[$b] <=> $stageDurations[$a]);
        $criticalPath = array_slice($criticalPath, 0, min(4, count($criticalPath)));

        $throughput = $totalDuration > 0.0 ? round(array_sum($input->entityVolumes) / $totalDuration, 2) : 0.0;

        $run = new SimulationRun(
            $scenario->id,
            round($totalDuration, 2),
            $stageDurations,
            $criticalPath,
            $throughput,
            $sourcePressure,
            $targetPressure,
            $risk,
            [],
        );

        $recommendations = $this->recommendationEngine->build($run);

        return new SimulationRun(
            $run->scenarioId,
            $run->estimatedTotalDurationHours,
            $run->stageDurationsHours,
            $run->criticalPath,
            $run->estimatedThroughput,
            $run->sourceLoadProfile,
            $run->targetLoadProfile,
            $run->riskScores,
            $recommendations,
        );
    }

    /** @param list<SimulationScenario> $scenarios */
    public function compare(SimulationInput $input, array $scenarios): SimulationComparison
    {
        $runs = array_map(fn (SimulationScenario $scenario): SimulationRun => $this->run($input, $scenario), $scenarios);

        usort($runs, static fn (SimulationRun $a, SimulationRun $b): int => $a->estimatedTotalDurationHours <=> $b->estimatedTotalDurationHours);
        $fastest = $runs[0];

        usort($runs, static fn (SimulationRun $a, SimulationRun $b): int => ($a->riskScores['OverallSimulationRiskScore'] ?? 100) <=> ($b->riskScores['OverallSimulationRiskScore'] ?? 100));
        $safest = $runs[0];

        usort($runs, static fn (SimulationRun $a, SimulationRun $b): int => ($a->sourceLoadProfile['fs_scan_pressure'] ?? 999) <=> ($b->sourceLoadProfile['fs_scan_pressure'] ?? 999));
        $leastSourcePressure = $runs[0];

        return new SimulationComparison($runs, [
            'fastest' => $fastest->scenarioId,
            'safest' => $safest->scenarioId,
            'least_source_pressure' => $leastSourcePressure->scenarioId,
        ]);
    }

    private function buildModel(SimulationInput $input, SimulationScenario $scenario): SimulationModel
    {
        $costs = [];
        foreach ($input->entityVolumes as $entity => $count) {
            foreach ($this->entityEstimators as $estimator) {
                if ($estimator->supports($entity)) {
                    $costs[$entity] = $estimator->estimate($entity, (int) $count, $input, $scenario);
                    continue 2;
                }
            }

            $costs[$entity] = [
                'entity' => $entity,
                'count' => (int) $count,
                'read_ms' => $count * 2,
                'transform_ms' => $count,
                'write_ms' => $count * 2,
                'verify_ms' => $count,
                'total_ms' => $count * 6,
            ];
        }

        return new SimulationModel($costs, $this->dependencyPenaltyEstimator->estimatePenalties($input->dependencyGraph));
    }
}
