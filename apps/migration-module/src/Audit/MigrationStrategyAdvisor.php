<?php

declare(strict_types=1);

namespace MigrationModule\Audit;

final class MigrationStrategyAdvisor
{
    /** @param array<string,array<string,mixed>> $entities @param array<string,array<string,mixed>> $conflictProbability @return array<string,mixed> */
    public function advise(array $entities, array $conflictProbability): array
    {
        $totalHourly = 0.0;
        $highestPeak = 0;
        $highRiskEntities = 0;

        foreach ($entities as $entity => $metrics) {
            $totalHourly += (float) ($metrics['avg_changes_per_hour'] ?? 0.0);
            $highestPeak = max($highestPeak, (int) ($metrics['peak_changes_per_hour'] ?? 0));
            if (($conflictProbability[$entity]['risk_level'] ?? 'low') === 'high') {
                $highRiskEntities++;
            }
        }

        $workers = max(4, (int) ceil($totalHourly / 30));
        $cutoverMinutes = max(15, (int) ceil($highestPeak / max($workers * 20, 1)) * 10);
        $deltaSyncMinutes = $highRiskEntities > 2 ? 2 : ($totalHourly > 500 ? 5 : 10);

        return [
            'recommended_strategy' => $highRiskEntities > 2 ? 'phased_migration' : 'background_initial_plus_delta',
            'initial_migration' => 'background',
            'delta_sync_interval_minutes' => $deltaSyncMinutes,
            'final_cutover_window_minutes' => $cutoverMinutes,
            'recommended_workers' => $workers,
            'recommended_rate_limit_rps' => max(10, min(60, $workers * 3)),
            'real_time_migration_feasible' => $highRiskEntities === 0,
        ];
    }
}
