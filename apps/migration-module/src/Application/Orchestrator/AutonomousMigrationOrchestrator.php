<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator;

use MigrationModule\Application\Orchestrator\Contracts\ControlApiInterface;
use MigrationModule\Application\Orchestrator\Contracts\PlannerInterface;
use MigrationModule\Application\Orchestrator\Contracts\QueueManagerInterface;
use MigrationModule\Application\Orchestrator\Contracts\ReconcilerInterface;
use MigrationModule\Application\Orchestrator\Contracts\StateStoreInterface;
use MigrationModule\Application\Orchestrator\Contracts\ValidatorInterface;
use MigrationModule\Application\Orchestrator\Contracts\WorkerPoolInterface;
use MigrationModule\Application\Orchestrator\StateMachine\MigrationStateMachine;
use MigrationModule\Domain\Orchestrator\OrchestratorState;

final class AutonomousMigrationOrchestrator
{
    public function __construct(
        private readonly MigrationStateMachine $stateMachine,
        private readonly PlannerInterface $planner,
        private readonly QueueManagerInterface $queue,
        private readonly WorkerPoolInterface $workers,
        private readonly ValidatorInterface $validator,
        private readonly ReconcilerInterface $reconciler,
        private readonly StateStoreInterface $store,
        private readonly ControlApiInterface $control,
        private readonly AutonomousDecisionEngine $decisionEngine,
        private readonly SelfHealingLayer $selfHealing,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function run(string $jobId, array $context): array
    {
        $state = OrchestratorState::INIT;
        $state = $this->stateMachine->transition($state, OrchestratorState::PRECHECK);
        $state = $this->stateMachine->transition($state, OrchestratorState::DISCOVERY);
        $state = $this->stateMachine->transition($state, OrchestratorState::PLAN_GENERATION);

        $plan = $this->planner->buildPlan($context);
        $this->queue->seed($jobId, $plan);

        if (($context['dry_run'] ?? false) === true) {
            $state = $this->stateMachine->transition($state, OrchestratorState::DRY_RUN);
        }

        $state = $this->stateMachine->transition($state, OrchestratorState::WAITING_CONFIRMATION);

        if (!$this->control->hasRunConfirmation($jobId)) {
            return ['job_id' => $jobId, 'status' => OrchestratorState::SAFE_STOPPED, 'reason' => 'Run confirmation is not granted.'];
        }

        $state = $this->stateMachine->transition($state, OrchestratorState::EXECUTING);
        $processed = 0;
        $errors = 0;

        while (($task = $this->queue->pullNext($jobId)) !== null) {
            if ($this->control->isPauseRequested($jobId)) {
                $state = $this->stateMachine->transition($state, OrchestratorState::PAUSED);
                break;
            }

            $decision = $this->decisionEngine->decide([
                'safe_stop_requested' => $this->control->isSafeStopRequested($jobId),
                'error_rate' => $processed === 0 ? 0.0 : $errors / $processed,
                'manual_review_queue' => 0,
                'rate_limit_hits' => 0,
            ]);

            $this->store->appendDecision($jobId, $decision);

            if ($decision['action'] === 'safe_stop') {
                $state = $decision['next_state'];
                break;
            }

            if ($decision['action'] === 'throttle') {
                $state = $this->stateMachine->transition($state, OrchestratorState::THROTTLED);
                $state = $this->stateMachine->transition($state, OrchestratorState::EXECUTING);
            }

            $result = $this->workers->execute($task);
            $validation = $this->validator->validateBatch($jobId, $result);

            if (($validation['ok'] ?? false) === true) {
                $processed++;
                $this->queue->ack($jobId, $task);
                $this->store->checkpoint($jobId, ['task' => $task['id'] ?? null, 'status' => 'done']);
                continue;
            }

            $errors++;
            $state = $this->stateMachine->transition($state, OrchestratorState::SELF_HEALING);
            $healing = $this->selfHealing->heal([
                'code' => (string) ($validation['error_code'] ?? 'unknown'),
                'attempt' => (int) ($task['attempt'] ?? 1),
            ]);

            if ($healing['quarantine'] === true) {
                $this->queue->deadLetter($jobId, $task, $healing['strategy']);
                $state = $this->stateMachine->transition($state, OrchestratorState::PARTIAL_BLOCK);
                break;
            }

            $state = $this->stateMachine->transition($state, OrchestratorState::EXECUTING);
        }

        if ($state === OrchestratorState::PAUSED || $state === OrchestratorState::SAFE_STOPPED || $state === OrchestratorState::PARTIAL_BLOCK) {
            $this->store->saveGlobalState($jobId, ['state' => $state, 'processed' => $processed, 'errors' => $errors]);

            return ['job_id' => $jobId, 'status' => $state, 'processed' => $processed, 'errors' => $errors];
        }

        $state = $this->stateMachine->transition($state, OrchestratorState::RECONCILIATION);
        $reconciliation = $this->reconciler->reconcile($jobId, (bool) ($context['rerun'] ?? false));
        $state = ($reconciliation['warnings'] ?? 0) > 0
            ? $this->stateMachine->transition($state, OrchestratorState::COMPLETED_WITH_WARNINGS)
            : $this->stateMachine->transition($state, OrchestratorState::COMPLETED);

        $final = ['state' => $state, 'processed' => $processed, 'errors' => $errors, 'reconciliation' => $reconciliation];
        $this->store->saveGlobalState($jobId, $final);

        return ['job_id' => $jobId] + $final;
    }
}
