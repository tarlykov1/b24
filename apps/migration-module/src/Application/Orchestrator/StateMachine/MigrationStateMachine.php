<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\StateMachine;

use InvalidArgumentException;
use MigrationModule\Domain\Orchestrator\OrchestratorState;

final class MigrationStateMachine
{
    /** @var array<string,array<int,string>> */
    private const TRANSITIONS = [
        OrchestratorState::INIT => [OrchestratorState::PRECHECK],
        OrchestratorState::PRECHECK => [OrchestratorState::DISCOVERY, OrchestratorState::FAILED],
        OrchestratorState::DISCOVERY => [OrchestratorState::PLAN_GENERATION, OrchestratorState::FAILED],
        OrchestratorState::PLAN_GENERATION => [OrchestratorState::DRY_RUN, OrchestratorState::WAITING_CONFIRMATION, OrchestratorState::FAILED],
        OrchestratorState::DRY_RUN => [OrchestratorState::WAITING_CONFIRMATION, OrchestratorState::FAILED],
        OrchestratorState::WAITING_CONFIRMATION => [OrchestratorState::EXECUTING, OrchestratorState::SAFE_STOPPED],
        OrchestratorState::EXECUTING => [
            OrchestratorState::THROTTLED,
            OrchestratorState::SELF_HEALING,
            OrchestratorState::PAUSED,
            OrchestratorState::DELTA_SYNC,
            OrchestratorState::RECONCILIATION,
            OrchestratorState::PARTIAL_BLOCK,
            OrchestratorState::FAILED,
            OrchestratorState::SAFE_STOPPED,
        ],
        OrchestratorState::THROTTLED => [OrchestratorState::EXECUTING, OrchestratorState::PAUSED, OrchestratorState::FAILED],
        OrchestratorState::PAUSED => [OrchestratorState::EXECUTING, OrchestratorState::SAFE_STOPPED],
        OrchestratorState::SELF_HEALING => [OrchestratorState::EXECUTING, OrchestratorState::PARTIAL_BLOCK, OrchestratorState::FAILED],
        OrchestratorState::PARTIAL_BLOCK => [OrchestratorState::EXECUTING, OrchestratorState::ROLLBACK_PARTIAL, OrchestratorState::FAILED],
        OrchestratorState::DELTA_SYNC => [OrchestratorState::RECONCILIATION, OrchestratorState::FAILED],
        OrchestratorState::RECONCILIATION => [OrchestratorState::COMPLETED, OrchestratorState::COMPLETED_WITH_WARNINGS, OrchestratorState::PARTIAL_BLOCK],
        OrchestratorState::ROLLBACK_PARTIAL => [OrchestratorState::COMPLETED_WITH_WARNINGS, OrchestratorState::FAILED],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(string $from, string $to): string
    {
        if (!$this->canTransition($from, $to)) {
            throw new InvalidArgumentException(sprintf('Invalid migration state transition: %s -> %s', $from, $to));
        }

        return $to;
    }
}
