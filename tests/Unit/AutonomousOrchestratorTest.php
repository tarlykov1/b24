<?php

declare(strict_types=1);

use MigrationModule\Application\Orchestrator\AutonomousDecisionEngine;
use MigrationModule\Application\Orchestrator\AutonomousMigrationOrchestrator;
use MigrationModule\Application\Orchestrator\Contracts\ControlApiInterface;
use MigrationModule\Application\Orchestrator\Contracts\PlannerInterface;
use MigrationModule\Application\Orchestrator\Contracts\QueueManagerInterface;
use MigrationModule\Application\Orchestrator\Contracts\ReconcilerInterface;
use MigrationModule\Application\Orchestrator\Contracts\StateStoreInterface;
use MigrationModule\Application\Orchestrator\Contracts\ValidatorInterface;
use MigrationModule\Application\Orchestrator\Contracts\WorkerPoolInterface;
use MigrationModule\Application\Orchestrator\SelfHealingLayer;
use MigrationModule\Application\Orchestrator\StateMachine\MigrationStateMachine;
use MigrationModule\Domain\Orchestrator\OrchestratorState;
use PHPUnit\Framework\TestCase;

final class AutonomousOrchestratorTest extends TestCase
{
    public function testStateMachineRejectsInvalidTransition(): void
    {
        $sm = new MigrationStateMachine();
        $this->expectException(InvalidArgumentException::class);
        $sm->transition(OrchestratorState::INIT, OrchestratorState::EXECUTING);
    }

    public function testAutonomousFlowCompletesWithWarningsOnRerun(): void
    {
        $queue = new class () implements QueueManagerInterface {
            /** @var array<int,array<string,mixed>> */
            private array $tasks = [];
            public function seed(string $jobId, array $plan): void { $this->tasks = $plan['tasks']; }
            public function pullNext(string $jobId): ?array { return array_shift($this->tasks); }
            public function ack(string $jobId, array $task): void {}
            public function deadLetter(string $jobId, array $task, string $reason): void {}
        };

        $store = new class () implements StateStoreInterface {
            /** @var array<string,mixed> */
            public array $state = [];
            public function saveGlobalState(string $jobId, array $state): void { $this->state = $state; }
            public function loadGlobalState(string $jobId): ?array { return $this->state; }
            public function appendDecision(string $jobId, array $decision): void {}
            public function checkpoint(string $jobId, array $checkpoint): void {}
        };

        $orchestrator = new AutonomousMigrationOrchestrator(
            new MigrationStateMachine(),
            new class () implements PlannerInterface {
                public function buildPlan(array $context): array { return ['tasks' => [['id' => 't1'], ['id' => 't2']]]; }
            },
            $queue,
            new class () implements WorkerPoolInterface {
                public function execute(array $task): array { return ['ok' => true, 'task' => $task['id']]; }
            },
            new class () implements ValidatorInterface {
                public function validateBatch(string $jobId, array $batchResult): array { return ['ok' => true]; }
            },
            new class () implements ReconcilerInterface {
                public function reconcile(string $jobId, bool $deltaOnly): array { return ['warnings' => $deltaOnly ? 1 : 0]; }
            },
            $store,
            new class () implements ControlApiInterface {
                public function isPauseRequested(string $jobId): bool { return false; }
                public function isSafeStopRequested(string $jobId): bool { return false; }
                public function hasRunConfirmation(string $jobId): bool { return true; }
            },
            new AutonomousDecisionEngine(),
            new SelfHealingLayer(),
        );

        $result = $orchestrator->run('job-1', ['rerun' => true]);

        self::assertSame(2, $result['processed']);
        self::assertSame(OrchestratorState::COMPLETED_WITH_WARNINGS, $result['state']);
    }
}
