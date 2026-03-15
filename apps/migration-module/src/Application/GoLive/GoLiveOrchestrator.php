<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

final class GoLiveOrchestrator
{
    public function __construct(
        private readonly GoLiveStateMachine $stateMachine,
        private readonly GoLiveReadinessEngine $readinessEngine,
        private readonly PreflightCheckRunner $preflightCheckRunner,
        private readonly FinalDeltaSyncOrchestrator $deltaSyncOrchestrator,
        private readonly SmokeTestRunner $smokeTestRunner,
        private readonly RollbackCoordinator $rollbackCoordinator,
        private readonly CutoverAuditTrail $auditTrail,
    ) {
    }

    /** @param array<string,mixed> $signals @param array<int,array{name:string,severity:string,result:bool,message:string}> $preflightChecks @param array<string,mixed> $deltaInput @param array<int,array{name:string,severity:string,pass:bool}> $smokeChecks
     * @return array<string,mixed>
     */
    public function run(string $actorId, array $signals, array $preflightChecks, array $deltaInput, array $smokeChecks): array
    {
        $readiness = $this->readinessEngine->assess($signals);
        $this->auditTrail->append($actorId, 'readiness_assessed', $readiness);

        $preflight = $this->preflightCheckRunner->evaluate($preflightChecks);
        $this->auditTrail->append($actorId, 'preflight_evaluated', ['status' => $preflight['status']]);

        if ($preflight['status'] === 'FAIL') {
            $this->auditTrail->append($actorId, 'cutover_aborted', ['reason' => 'preflight_fail']);

            return ['status' => 'preflight-failed', 'readiness' => $readiness, 'preflight' => $preflight, 'audit' => $this->auditTrail->all()];
        }

        $transition = $this->stateMachine->transition('freeze-active', 'delta-sync-running', ['automaticActions' => ['final_delta_sync']]);
        $delta = $this->deltaSyncOrchestrator->run('actual_final_delta_execution', $deltaInput);
        $smoke = $this->smokeTestRunner->evaluate($smokeChecks);
        $rollback = $this->rollbackCoordinator->decide([
            'switchCompleted' => true,
            'criticalSmokeFailed' => $smoke['criticalFails'] > 0,
            'domainFailures' => $smoke['majorFails'],
            'targetWritesAfterSwitch' => (int) ($deltaInput['targetWritesAfterSwitch'] ?? 0),
        ]);

        $this->auditTrail->append($actorId, 'delta_sync_completed', ['etaMin' => $delta['etaMin']]);
        $this->auditTrail->append($actorId, 'smoke_tests_completed', $smoke);

        return [
            'status' => $smoke['decision'] === 'rollback_candidate' ? 'rollback-pending' : 'stabilization',
            'transition' => $transition,
            'readiness' => $readiness,
            'preflight' => $preflight,
            'delta' => $delta,
            'smoke' => $smoke,
            'rollback' => $rollback,
            'audit' => $this->auditTrail->all(),
        ];
    }
}
