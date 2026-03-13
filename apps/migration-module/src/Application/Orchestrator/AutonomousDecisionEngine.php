<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator;

use MigrationModule\Domain\Orchestrator\OrchestratorState;

final class AutonomousDecisionEngine
{
    /**
     * @param array<string,mixed> $context
     * @return array{action:string,next_state:string,reason:string,manual_review:bool}
     */
    public function decide(array $context): array
    {
        $errorRate = (float) ($context['error_rate'] ?? 0.0);
        $rateLimitHits = (int) ($context['rate_limit_hits'] ?? 0);
        $manualQueueSize = (int) ($context['manual_review_queue'] ?? 0);
        $transientError = (bool) ($context['transient_error'] ?? false);
        $safeStopRequested = (bool) ($context['safe_stop_requested'] ?? false);

        if ($safeStopRequested) {
            return ['action' => 'safe_stop', 'next_state' => OrchestratorState::SAFE_STOPPED, 'reason' => 'Operator requested safe stop.', 'manual_review' => false];
        }

        if ($rateLimitHits >= 5 || $errorRate >= 0.25) {
            return ['action' => 'throttle', 'next_state' => OrchestratorState::THROTTLED, 'reason' => 'Source portal degradation detected.', 'manual_review' => false];
        }

        if ($transientError) {
            return ['action' => 'self_heal', 'next_state' => OrchestratorState::SELF_HEALING, 'reason' => 'Transient or partial error can be auto-healed.', 'manual_review' => false];
        }

        if ($manualQueueSize > 100) {
            return ['action' => 'pause_for_review', 'next_state' => OrchestratorState::PARTIAL_BLOCK, 'reason' => 'Manual review queue exceeded threshold.', 'manual_review' => true];
        }

        return ['action' => 'continue', 'next_state' => OrchestratorState::EXECUTING, 'reason' => 'Health metrics are within policy.', 'manual_review' => false];
    }
}
