<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Estimator;

final class DefaultRiskEstimator implements RiskEstimatorInterface
{
    public function score(array $context): array
    {
        $sourceFs = (float) ($context['source_load']['fs_scan_pressure'] ?? 0.0);
        $windowFit = (float) ($context['window_fit_probability'] ?? 1.0);
        $conflictRate = (float) ($context['conflict_rate'] ?? 0.05);
        $customFields = (int) ($context['custom_fields'] ?? 0);

        $durationRisk = max(0.0, min(100.0, (1 - $windowFit) * 100));
        $sourceRisk = max(0.0, min(100.0, $sourceFs * 7));
        $integrityRisk = max(0.0, min(100.0, $conflictRate * 180 + ($customFields / 80)));

        $overall = round(($durationRisk * 0.24) + ($sourceRisk * 0.2) + ($integrityRisk * 0.26) + 20, 2);

        return [
            'DurationRiskScore' => round($durationRisk, 2),
            'SourceImpactRiskScore' => round($sourceRisk, 2),
            'IntegrityRiskScore' => round($integrityRisk, 2),
            'ConflictRiskScore' => round(min(100, $conflictRate * 220), 2),
            'OperationalComplexityScore' => round(min(100, 25 + ($customFields / 30)), 2),
            'RecoveryDifficultyScore' => round(min(100, 30 + ($conflictRate * 120)), 2),
            'CutoverWindowRiskScore' => round($durationRisk, 2),
            'OverallSimulationRiskScore' => min(100, $overall),
        ];
    }
}
