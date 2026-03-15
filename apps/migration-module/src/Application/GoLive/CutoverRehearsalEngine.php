<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

final class CutoverRehearsalEngine
{
    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function simulate(array $input): array
    {
        $volume = (int) ($input['entityVolume'] ?? 10000);
        $throughput = max(1, (int) ($input['avgWorkerThroughput'] ?? 120));
        $workers = max(1, (int) ($input['workers'] ?? 8));
        $errorRate = (float) ($input['errorRate'] ?? 0.03);
        $dependencyPenalty = (float) ($input['dependencyPenalty'] ?? 1.1);

        $syncMin = (int) ceil(($volume / ($throughput * $workers)) * 60 * $dependencyPenalty);
        $validationMin = (int) max(15, ceil($syncMin * 0.2));
        $switchMin = (int) max(10, ceil($syncMin * 0.1));
        $stabilizationMin = (int) max(30, ceil($syncMin * 0.35));
        $totalMin = $syncMin + $validationMin + $switchMin + $stabilizationMin;

        $confidence = max(0.1, min(0.99, 1 - ($errorRate * 2.5) - (($dependencyPenalty - 1) * 0.2)));

        return [
            'simulatedTimelineMin' => [
                'final_delta_sync' => $syncMin,
                'validation' => $validationMin,
                'switch' => $switchMin,
                'stabilization' => $stabilizationMin,
            ],
            'predictedDurationMin' => $totalMin,
            'predictedBottlenecks' => $syncMin > 120 ? ['final_delta_sync'] : ['smoke_test'],
            'probableBlockers' => $errorRate > 0.06 ? ['high_error_pattern'] : [],
            'estimatedSourceLoad' => min(1.0, ($workers * 0.06) + ($errorRate * 1.5)),
            'estimatedTargetLoad' => min(1.0, ($workers * 0.08) + ($dependencyPenalty * 0.2)),
            'confidenceScore' => round($confidence, 2),
            'whatIf' => $this->whatIf($input),
        ];
    }

    /** @param array<string,mixed> $input
     * @return array<int,array<string,mixed>>
     */
    private function whatIf(array $input): array
    {
        return [
            ['scenario' => 'window_4h', 'fit' => ((float) ($input['windowHours'] ?? 8)) >= 4],
            ['scenario' => 'workers_x2', 'impact' => 'duration_down'],
            ['scenario' => 'files_separate_cutover', 'impact' => 'lower_switch_risk'],
            ['scenario' => 'phased_departments', 'impact' => 'longer_total_lower_peak'],
            ['scenario' => 'dual_run_2_days', 'impact' => 'higher_reconciliation_cost_lower_business_risk'],
        ];
    }
}
