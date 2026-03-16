<?php

declare(strict_types=1);

namespace MigrationModule\Application\Lifecycle;

use DomainException;
use MigrationModule\Domain\Job\JobLifecycle;
use MigrationModule\Prototype\Storage\SqliteStorage;

final class LifecycleTransitionGuard
{
    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    public function currentState(string $jobId): string
    {
        return $this->storage->jobStatus($jobId) ?? JobLifecycle::CREATED;
    }

    public function assertTransition(string $jobId, string $toState, string $command): void
    {
        $from = $this->currentState($jobId);
        if (JobLifecycle::canTransition($from, $toState)) {
            return;
        }

        throw new DomainException((string) json_encode([
            'error' => 'invalid_state_transition',
            'command' => $command,
            'job_id' => $jobId,
            'from' => $from,
            'to' => $toState,
            'resumable' => in_array($from, [JobLifecycle::PAUSED, JobLifecycle::FAILED], true),
            'terminal' => in_array($from, JobLifecycle::terminal(), true),
        ], JSON_UNESCAPED_UNICODE));
    }
}
