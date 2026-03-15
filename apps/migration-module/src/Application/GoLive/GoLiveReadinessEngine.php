<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

final class GoLiveReadinessEngine
{
    /** @param array<string,mixed> $signals
     * @return array<string,mixed>
     */
    public function assess(array $signals): array
    {
        $score = 100;
        $hardBlockers = [];
        $softBlockers = [];
        $warnings = [];
        $mustFix = [];

        $score -= $this->penaltyForRemainingQueue((int) ($signals['remainingQueueSize'] ?? 0));
        $score -= min(15, ((int) ($signals['unresolvedIntegrityIssues'] ?? 0)) * 3);
        $score -= min(15, ((int) ($signals['unresolvedMappingConflicts'] ?? 0)) * 5);
        $score -= (int) max(0, 20 - ((float) ($signals['workerHealth'] ?? 1.0) * 20));

        if ((bool) ($signals['retryStormDetected'] ?? false)) {
            $hardBlockers[] = 'retry_storm_detected';
            $score -= 25;
        }
        if (!(bool) ($signals['lastDryRunOk'] ?? false)) {
            $mustFix[] = 'last_dry_run_failed';
            $score -= 20;
        }
        if (!(bool) ($signals['lastVerificationOk'] ?? false)) {
            $mustFix[] = 'last_verification_failed';
            $score -= 20;
        }
        if ((float) ($signals['deltaSyncDurationEstimateMin'] ?? 0.0) > (float) ($signals['maxAllowedDowntimeMin'] ?? 60.0)) {
            $softBlockers[] = 'delta_sync_exceeds_window';
            $score -= 12;
        }
        if ((float) ($signals['sourceLoadEstimate'] ?? 0.0) > (float) ($signals['sourceLoadThreshold'] ?? 0.75)) {
            $warnings[] = 'source_load_risk_during_final_sync';
            $score -= 6;
        }

        foreach (['openManualDecisions', 'pendingApprovals', 'crmParityMissing', 'permissionsParityMissing', 'missingAttachments', 'highRiskEntitiesUnvalidated'] as $key) {
            if ((bool) ($signals[$key] ?? false)) {
                $hardBlockers[] = $key;
                $score -= 8;
            }
        }

        if (((int) ($signals['completedMigrationWaves'] ?? 0)) < ((int) ($signals['requiredMigrationWaves'] ?? 1))) {
            $hardBlockers[] = 'waves_incomplete';
            $score -= 15;
        }

        $score = max(0, min(100, $score));

        $recommendation = 'ready';
        if ($hardBlockers !== [] || $score < 60) {
            $recommendation = 'not_ready';
        } elseif ($softBlockers !== [] || $warnings !== []) {
            $recommendation = $score < 75 ? 'ready_only_for_phased_cutover' : 'ready_with_warnings';
        }

        $acceptable = array_values(array_filter((array) ($signals['knownIssues'] ?? []), static fn (array $issue): bool => ($issue['severity'] ?? 'minor') === 'minor'));

        return [
            'readinessScore' => $score,
            'hardBlockers' => array_values(array_unique($hardBlockers)),
            'softBlockers' => array_values(array_unique($softBlockers)),
            'warnings' => array_values(array_unique($warnings)),
            'mustFixBeforeCutover' => array_values(array_unique($mustFix)),
            'acceptableKnownIssues' => $acceptable,
            'recommendation' => $recommendation,
        ];
    }

    private function penaltyForRemainingQueue(int $remainingQueue): int
    {
        if ($remainingQueue <= 0) {
            return 0;
        }

        if ($remainingQueue < 100) {
            return 4;
        }

        if ($remainingQueue < 500) {
            return 10;
        }

        return 18;
    }
}
