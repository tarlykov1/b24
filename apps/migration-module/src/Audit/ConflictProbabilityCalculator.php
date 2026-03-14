<?php

declare(strict_types=1);

namespace MigrationModule\Audit;

final class ConflictProbabilityCalculator
{
    /** @param array<string,array<string,mixed>> $entities @return array<string,array<string,mixed>> */
    public function calculate(array $entities, float $migrationDurationHours): array
    {
        $result = [];
        foreach ($entities as $entity => $metrics) {
            $ratePerHour = (float) ($metrics['avg_changes_per_hour'] ?? 0.0);
            $conflictRisk = $ratePerHour * $migrationDurationHours;
            $probability = min(1.0, $conflictRisk / 100.0);

            $result[$entity] = [
                'rate_per_hour' => round($ratePerHour, 2),
                'migration_duration_hours' => $migrationDurationHours,
                'conflict_risk' => round($conflictRisk, 2),
                'probability' => round($probability, 4),
                'risk_level' => $this->riskLevel($probability),
            ];
        }

        return $result;
    }

    private function riskLevel(float $probability): string
    {
        return match (true) {
            $probability >= 0.6 => 'high',
            $probability >= 0.25 => 'medium',
            default => 'low',
        };
    }
}
