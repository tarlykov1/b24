<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Report;

use MigrationModule\Simulation\Domain\SimulationRun;

final class SimulationReportBuilder
{
    /** @return array<string,mixed> */
    public function toJson(SimulationRun $run): array
    {
        return [
            'summary' => [
                'duration_hours' => $run->estimatedTotalDurationHours,
                'throughput' => $run->estimatedThroughput,
                'overall_risk' => $run->riskScores['OverallSimulationRiskScore'] ?? null,
            ],
            'scenario_id' => $run->scenarioId,
            'stage_breakdown' => $run->stageDurationsHours,
            'critical_path' => $run->criticalPath,
            'source_impact_profile' => $run->sourceLoadProfile,
            'target_impact_profile' => $run->targetLoadProfile,
            'recommendations' => $run->recommendations,
            'risk' => $run->riskScores,
        ];
    }

    public function toMarkdown(SimulationRun $run): string
    {
        $lines = [
            '# Simulation Report',
            '',
            '- Scenario: `' . $run->scenarioId . '`',
            '- Estimated duration (h): **' . $run->estimatedTotalDurationHours . '**',
            '- Throughput (entities/h): **' . $run->estimatedThroughput . '**',
            '- Overall risk: **' . ($run->riskScores['OverallSimulationRiskScore'] ?? 'n/a') . '**',
            '',
            '## Stage breakdown',
        ];

        foreach ($run->stageDurationsHours as $stage => $hours) {
            $lines[] = '- ' . $stage . ': ' . $hours . 'h';
        }

        $lines[] = '';
        $lines[] = '## Recommendations';
        foreach (($run->recommendations['human'] ?? []) as $text) {
            $lines[] = '- ' . $text;
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
