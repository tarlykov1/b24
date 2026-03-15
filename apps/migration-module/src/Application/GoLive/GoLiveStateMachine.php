<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

use RuntimeException;

final class GoLiveStateMachine
{
    /** @var array<string,array<int,string>> */
    private array $transitions = [
        'draft' => ['planned', 'aborted'],
        'planned' => ['awaiting-approvals', 'aborted'],
        'awaiting-approvals' => ['approved', 'aborted'],
        'approved' => ['rehearsal-ready', 'preflight-running', 'aborted'],
        'rehearsal-ready' => ['preflight-running', 'aborted'],
        'preflight-running' => ['preflight-failed', 'freeze-pending'],
        'preflight-failed' => ['preflight-running', 'aborted'],
        'freeze-pending' => ['freeze-active', 'aborted'],
        'freeze-active' => ['delta-sync-running', 'rollback-pending'],
        'delta-sync-running' => ['validation-running', 'rollback-pending'],
        'validation-running' => ['switch-pending', 'rollback-pending'],
        'switch-pending' => ['switching', 'rollback-pending'],
        'switching' => ['smoke-test-running', 'rollback-pending'],
        'smoke-test-running' => ['stabilization', 'rollback-pending'],
        'stabilization' => ['completed', 'completed-with-warnings', 'rollback-pending'],
        'rollback-pending' => ['rolling-back', 'stabilization'],
        'rolling-back' => ['rolled-back', 'aborted'],
        'rolled-back' => [],
        'completed' => [],
        'completed-with-warnings' => [],
        'aborted' => [],
    ];

    /** @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function transition(string $from, string $to, array $context = []): array
    {
        $allowed = $this->transitions[$from] ?? null;
        if ($allowed === null || !in_array($to, $allowed, true)) {
            throw new RuntimeException(sprintf('Transition %s -> %s is not allowed', $from, $to));
        }

        return [
            'from' => $from,
            'to' => $to,
            'entryCriteria' => $context['entryCriteria'] ?? [],
            'exitCriteria' => $context['exitCriteria'] ?? [],
            'timeoutSec' => (int) ($context['timeoutSec'] ?? 1800),
            'manualActions' => $context['manualActions'] ?? [],
            'automaticActions' => $context['automaticActions'] ?? [],
            'notifications' => $context['notifications'] ?? [],
        ];
    }

    /** @return array<string,array<int,string>> */
    public function graph(): array
    {
        return $this->transitions;
    }
}
