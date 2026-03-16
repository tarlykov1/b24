<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

use MigrationModule\Prototype\Storage\SqliteStorage;
use RuntimeException;

final class CutoverFinalizationService
{
    public function __construct(
        private readonly CutoverFinalizationRepository $repo,
        private readonly CutoverFinalizationStateMachine $sm,
        private readonly CutoverReadinessEvaluator $readiness,
        private readonly FreezeWindowManager $freeze,
        private readonly FinalDeltaCollector $delta,
        private readonly CutoverVerificationRunner $verification,
        private readonly GoLiveDecisionEngine $decision,
        private readonly SqliteStorage $storage,
    ) {
    }

    /** @param array<string,mixed> $meta @return array<string,mixed> */
    public function prepare(string $freezeId, string $jobId, string $sourceId, string $targetId, string $actor, array $meta = []): array
    {
        $this->repo->createSession($freezeId, $jobId, $sourceId, $targetId, $actor, 'draft', $meta);
        $this->transition($freezeId, 'draft', 'prepared', $actor, 'cutover_prepared', ['metadata' => $meta]);
        $this->repo->incrementMetric('cutover_sessions_total');

        return $this->status($freezeId);
    }

    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function readiness(string $freezeId, array $signals): array
    {
        $session = $this->mustSession($freezeId);
        $report = $this->readiness->evaluate($signals);
        $this->repo->saveReadinessReport($freezeId, (string) $report['status'], $report);
        $this->repo->patchSession($freezeId, ['verification_summary' => $report]);

        return ['freeze_window_id' => $freezeId, 'state' => $session['current_state'], 'readiness' => $report];
    }

    /** @param list<string> $protectedDomains @return array<string,mixed> */
    public function arm(string $freezeId, string $actor, string $mode, array $protectedDomains): array
    {
        $session = $this->mustSession($freezeId);
        $this->transition($freezeId, (string) $session['current_state'], 'armed', $actor, 'cutover_armed', ['mode' => $mode, 'protected_domains' => $protectedDomains]);
        $this->repo->patchSession($freezeId, ['metadata_json' => ['freeze_mode' => $mode, 'protected_domains' => $protectedDomains]]);

        return $this->status($freezeId);
    }

    /** @param list<array<string,mixed>> $mutations @return array<string,mixed> */
    public function freezeStart(string $freezeId, string $actor, string $mode, array $protectedDomains, array $mutations): array
    {
        $session = $this->mustSession($freezeId);
        $this->transition($freezeId, (string) $session['current_state'], 'freeze_active', $actor, 'freeze_window_started', ['mode' => $mode]);
        foreach ($mutations as $m) {
            $this->repo->saveMutation($freezeId, $m);
        }
        $analysis = $this->freeze->evaluateMutations($mode, $mutations, $protectedDomains);
        $this->repo->patchSession($freezeId, ['actual_freeze_start' => gmdate(DATE_ATOM), 'blocker_count' => (int) $analysis['blocking_mutations']]);
        $this->repo->incrementMetric('cutover_freeze_active', 1, ['freeze_window_id' => $freezeId]);
        $this->repo->incrementMetric('cutover_mutations_detected_total', (float) ($analysis['mutations_detected_total'] ?? 0));
        $this->repo->incrementMetric('cutover_blocking_mutations_total', (float) ($analysis['blocking_mutations'] ?? 0));

        if ((int) $analysis['blocking_mutations'] > 0 && $mode === 'strict_freeze') {
            $this->transition($freezeId, 'freeze_active', 'blocked', $actor, 'freeze_mutation_detected', $analysis);
        }

        return ['freeze_window_id' => $freezeId, 'analysis' => $analysis, 'state' => $this->mustSession($freezeId)['current_state']];
    }

    /** @param array<string,mixed> $baseline */
    public function finalDelta(string $freezeId, string $actor, array $baseline): array
    {
        $session = $this->mustSession($freezeId);
        $state = (string) $session['current_state'];
        if (!in_array($state, ['freeze_active', 'delta_capture_running'], true)) {
            throw new RuntimeException('delta_not_allowed_in_state:' . $state);
        }
        if ($state === 'freeze_active') {
            $this->transition($freezeId, 'freeze_active', 'delta_capture_running', $actor, 'final_delta_started', ['baseline' => $baseline]);
        }

        $delta = $this->delta->collect((string) $session['job_id'], $baseline);
        $this->repo->savePhaseCheckpoint($freezeId, 'delta_capture_running', 'delta:' . (string) $delta['next_offset'], $delta);

        if ((bool) $delta['complete']) {
            $this->transition($freezeId, 'delta_capture_running', 'final_sync_running', $actor, 'final_delta_completed', $delta);
        }
        $this->repo->incrementMetric('cutover_final_delta_entities_total', (float) ($delta['processed_chunk'] ?? 0));
        $failures = 0;
        foreach (($delta['domains'] ?? []) as $domain) {
            $failures += (int) ($domain['failed_count'] ?? 0);
        }
        $this->repo->incrementMetric('cutover_final_delta_failures_total', (float) $failures);

        return ['freeze_window_id' => $freezeId, 'delta' => $delta, 'state' => $this->mustSession($freezeId)['current_state']];
    }

    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function verify(string $freezeId, string $actor, array $signals): array
    {
        $session = $this->mustSession($freezeId);
        $state = (string) $session['current_state'];
        if ($state === 'final_sync_running') {
            $this->transition($freezeId, 'final_sync_running', 'verification_running', $actor, 'cutover_verification_started', []);
        }
        if ((string) $this->mustSession($freezeId)['current_state'] !== 'verification_running') {
            throw new RuntimeException('verification_not_allowed');
        }
        $result = $this->verification->run($signals);
        $this->repo->saveVerification($freezeId, (string) $result['color'], $result);
        $this->repo->incrementMetric('cutover_verification_runs_total');

        if ($result['color'] === 'red') {
            $this->transition($freezeId, 'verification_running', 'blocked', $actor, 'cutover_verification_failed', $result);
        } else {
            $this->transition($freezeId, 'verification_running', 'ready_for_go_live', $actor, 'cutover_verification_completed', $result);
        }

        return ['freeze_window_id' => $freezeId, 'verification' => $result, 'state' => $this->mustSession($freezeId)['current_state']];
    }

    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function verdict(string $freezeId, array $context): array
    {
        $session = $this->mustSession($freezeId);
        $decision = $this->decision->decide($context);
        $this->repo->saveVerdict($freezeId, (string) $decision['verdict'], $decision, (bool) $decision['override_allowed'], (string) $decision['override_risk_level']);
        $this->repo->patchSession($freezeId, ['verdict' => $decision['verdict']]);
        if ($decision['verdict'] === 'go_live_approved') {
            $this->repo->incrementMetric('cutover_go_live_approved_total');
        }
        if ($decision['verdict'] === 'go_live_blocked') {
            $this->repo->incrementMetric('cutover_go_live_blocked_total');
        }

        return ['freeze_window_id' => $freezeId, 'state' => $session['current_state'], 'rationale' => $decision];
    }

    /** @param array<string,mixed> $overridePayload @return array<string,mixed> */
    public function complete(string $freezeId, string $actor, bool $override = false, array $overridePayload = []): array
    {
        $session = $this->mustSession($freezeId);
        $state = (string) $session['current_state'];
        if ($state !== 'ready_for_go_live') {
            throw new RuntimeException('complete_not_allowed');
        }

        if ($override) {
            $this->repo->saveOverride($freezeId, $actor, (string) ($overridePayload['reason'] ?? 'operator_override'), $overridePayload);
            $this->repo->incrementMetric('cutover_operator_overrides_total');
        }

        $this->transition($freezeId, 'ready_for_go_live', 'completed', $actor, 'go_live_approved', ['override' => $override]);
        $this->repo->patchSession($freezeId, ['actual_freeze_end' => gmdate(DATE_ATOM)]);
        return $this->status($freezeId);
    }

    public function abort(string $freezeId, string $actor, string $reason): array
    {
        $session = $this->mustSession($freezeId);
        $this->transition($freezeId, (string) $session['current_state'], 'aborted', $actor, 'cutover_aborted', ['reason' => $reason]);
        $this->repo->patchSession($freezeId, ['abort_reason' => $reason, 'actual_freeze_end' => gmdate(DATE_ATOM)]);

        return $this->status($freezeId);
    }

    public function resume(string $freezeId, string $actor): array
    {
        $session = $this->mustSession($freezeId);
        if ((string) $session['current_state'] !== 'blocked') {
            throw new RuntimeException('resume_allowed_only_from_blocked');
        }
        $this->transition($freezeId, 'blocked', 'armed', $actor, 'cutover_resumed', []);

        return $this->status($freezeId);
    }

    /** @return array<string,mixed> */
    public function status(string $freezeId): array
    {
        $s = $this->mustSession($freezeId);
        $mutations = $this->repo->mutations($freezeId);
        return [
            'freeze_window_id' => $freezeId,
            'job_id' => $s['job_id'],
            'state' => $s['current_state'],
            'initiated_by' => $s['initiated_by'],
            'expected_freeze_start' => $s['expected_freeze_start'],
            'expected_freeze_end' => $s['expected_freeze_end'],
            'actual_freeze_start' => $s['actual_freeze_start'],
            'actual_freeze_end' => $s['actual_freeze_end'],
            'blocker_count' => (int) ($s['blocker_count'] ?? 0),
            'resumable' => (bool) ($s['resumable_flag'] ?? 0),
            'verdict' => $s['verdict'] ?? null,
            'mutation_summary' => [
                'total' => count($mutations),
                'blocking' => count(array_filter($mutations, static fn (array $m): bool => (string) $m['policy_impact'] === 'blocking')),
            ],
            'honesty_note' => 'Freeze mode enforcement is policy-driven mutation detection; global source write lock is not guaranteed by this service.',
        ];
    }

    /** @return array<string,mixed> */
    private function mustSession(string $freezeId): array
    {
        $s = $this->repo->session($freezeId);
        if ($s === null) {
            throw new RuntimeException('freeze_session_not_found');
        }

        return $s;
    }

    /** @param array<string,mixed> $payload */
    private function transition(string $freezeId, string $from, string $to, string $actor, string $eventName, array $payload): void
    {
        $this->sm->assertTransition($from, $to);
        $this->storage->pdo()->beginTransaction();
        try {
            $this->repo->patchSession($freezeId, ['current_state' => $to]);
            $this->repo->saveTransition($freezeId, $from, $to, $actor, $eventName, $payload);
            $this->storage->pdo()->commit();
        } catch (\Throwable $e) {
            $this->storage->pdo()->rollBack();
            throw $e;
        }
    }
}
