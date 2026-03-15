<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

final class RollbackCoordinator
{
    /** @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function decide(array $context): array
    {
        $switched = (bool) ($context['switchCompleted'] ?? false);
        $targetWrites = (int) ($context['targetWritesAfterSwitch'] ?? 0);
        $irreversible = (bool) ($context['irreversibleTransforms'] ?? false);

        $possibility = 'rollback_technically_possible';
        if ($irreversible || $targetWrites > 10000) {
            $possibility = 'rollback_impossible_stabilization_only';
        } elseif ($targetWrites > 1000) {
            $possibility = 'rollback_risky';
        }

        $recommended = 'stop_and_hold_stabilization';
        if (!$switched) {
            $recommended = 're_cutover_after_failed_attempt';
        } elseif (($context['criticalSmokeFailed'] ?? false) === true && $possibility !== 'rollback_impossible_stabilization_only') {
            $recommended = 'full_rollback_to_source_primary';
        } elseif (($context['domainFailures'] ?? 0) > 0 && $possibility !== 'rollback_impossible_stabilization_only') {
            $recommended = 'partial_rollback_selected_domains';
        }

        return [
            'rollbackPossibility' => $possibility,
            'recommendedScenario' => $recommended,
            'fallbackScenario' => 'read_only_degraded_target',
            'reconciliationRequired' => $targetWrites > 0,
        ];
    }
}
