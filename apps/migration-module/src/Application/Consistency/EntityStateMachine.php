<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

final class EntityStateMachine
{
    private const ALLOWED = [
        'discovered' => ['queued', 'skipped', 'failed'],
        'queued' => ['extracted', 'dependency_blocked', 'failed'],
        'extracted' => ['transformed', 'failed'],
        'transformed' => ['created', 'updated', 'dependency_blocked', 'failed'],
        'dependency_blocked' => ['queued', 'requires_manual_review'],
        'created' => ['linked', 'files_pending', 'verified'],
        'updated' => ['linked', 'files_pending', 'verified'],
        'linked' => ['files_pending', 'verified', 'reconciled'],
        'files_pending' => ['reconciled', 'verified', 'failed'],
        'reconciled' => ['verified'],
        'verified' => [],
        'conflicted' => ['requires_manual_review', 'reconciled'],
        'requires_manual_review' => ['reconciled', 'skipped'],
        'skipped' => [],
        'failed' => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::ALLOWED[$from] ?? [], true);
    }
}
