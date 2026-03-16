<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

use InvalidArgumentException;

final class CutoverFinalizationStateMachine
{
    /** @var array<string,list<string>> */
    private const TRANSITIONS = [
        'draft' => ['prepared', 'aborted'],
        'prepared' => ['armed', 'aborted'],
        'armed' => ['freeze_active', 'aborted'],
        'freeze_active' => ['delta_capture_running', 'blocked', 'aborted'],
        'delta_capture_running' => ['final_sync_running', 'blocked', 'aborted'],
        'final_sync_running' => ['verification_running', 'blocked', 'aborted'],
        'verification_running' => ['ready_for_go_live', 'blocked', 'aborted'],
        'ready_for_go_live' => ['completed', 'aborted'],
        'blocked' => ['armed', 'aborted'],
        'aborted' => ['draft'],
        'completed' => [],
    ];

    public function assertTransition(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new InvalidArgumentException(sprintf('invalid_freeze_transition:%s->%s', $from, $to));
        }
    }

    /** @return list<string> */
    public function statuses(): array
    {
        return array_keys(self::TRANSITIONS);
    }
}
