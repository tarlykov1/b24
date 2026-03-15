<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Application;

final class FailureSimulationService
{
    /**
     * @param array<string,mixed> $run
     * @param list<string> $failures
     * @return array<string,mixed>
     */
    public function apply(array $run, array $failures): array
    {
        $duration = (float) ($run['estimated_total_duration_hours'] ?? 0.0);
        $risk = (float) (($run['risk_scores']['OverallSimulationRiskScore'] ?? 0.0));

        foreach ($failures as $failure) {
            if (in_array($failure, ['network_instability', 'db_latency_spike', 'queue_retry_storm'], true)) {
                $duration *= 1.25;
                $risk += 8;
            } elseif (in_array($failure, ['worker_crash', 'target_writer_down'], true)) {
                $duration *= 1.15;
                $risk += 6;
            } elseif ($failure === 'source_throttling_trigger') {
                $duration *= 1.35;
                $risk += 10;
            }
        }

        $run['estimated_total_duration_hours'] = round($duration, 2);
        $run['risk_scores']['OverallSimulationRiskScore'] = min(100, round($risk, 2));
        $run['failure_assumptions'] = $failures;

        return $run;
    }
}
