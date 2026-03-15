<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Domain;

final class SimulationRun
{
    /**
     * @param array<string,float> $stageDurationsHours
     * @param list<string> $criticalPath
     * @param array<string,mixed> $sourceLoadProfile
     * @param array<string,mixed> $targetLoadProfile
     * @param array<string,mixed> $riskScores
     * @param array<string,mixed> $recommendations
     */
    public function __construct(
        public readonly string $scenarioId,
        public readonly float $estimatedTotalDurationHours,
        public readonly array $stageDurationsHours,
        public readonly array $criticalPath,
        public readonly float $estimatedThroughput,
        public readonly array $sourceLoadProfile,
        public readonly array $targetLoadProfile,
        public readonly array $riskScores,
        public readonly array $recommendations,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'scenario_id' => $this->scenarioId,
            'estimated_total_duration_hours' => $this->estimatedTotalDurationHours,
            'stage_durations_hours' => $this->stageDurationsHours,
            'critical_path' => $this->criticalPath,
            'estimated_throughput_entities_per_hour' => $this->estimatedThroughput,
            'source_load_profile' => $this->sourceLoadProfile,
            'target_load_profile' => $this->targetLoadProfile,
            'risk_scores' => $this->riskScores,
            'recommendations' => $this->recommendations,
        ];
    }
}
