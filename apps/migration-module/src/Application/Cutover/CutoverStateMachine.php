<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

use InvalidArgumentException;

final class CutoverStateMachine
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'draft' => ['planned', 'aborted'],
        'planned' => ['awaiting_approval', 'preflight_running', 'aborted'],
        'awaiting_approval' => ['approved', 'aborted'],
        'approved' => ['preflight_running', 'go_live_in_progress', 'aborted'],
        'preflight_running' => ['preflight_failed', 'ready_for_go_live', 'failed'],
        'preflight_failed' => ['preflight_running', 'aborted'],
        'ready_for_go_live' => ['go_live_in_progress', 'aborted'],
        'go_live_in_progress' => ['freeze_activated', 'failed', 'aborted'],
        'freeze_activated' => ['final_delta_running', 'failed', 'aborted'],
        'final_delta_running' => ['final_reconciliation_running', 'failed', 'aborted'],
        'final_reconciliation_running' => ['switching', 'rollback_recommended', 'failed', 'aborted'],
        'switching' => ['post_switch_validation', 'failed', 'rollback_recommended'],
        'post_switch_validation' => ['live', 'rollback_recommended', 'failed'],
        'live' => ['rollback_in_progress', 'rollback_recommended'],
        'rollback_recommended' => ['rollback_in_progress', 'failed'],
        'rollback_in_progress' => ['rolled_back', 'failed'],
        'rolled_back' => [],
        'failed' => ['preflight_running', 'go_live_in_progress', 'rollback_in_progress', 'aborted'],
        'aborted' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function assertTransition(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        if (!$this->canTransition($from, $to)) {
            throw new InvalidArgumentException(sprintf('Invalid cutover transition: %s -> %s', $from, $to));
        }
    }

    /** @return list<string> */
    public function statuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }
}
