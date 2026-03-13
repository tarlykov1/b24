<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Job;

final class JobLifecycle
{
    public const DRAFT = 'draft';
    public const READY = 'ready';
    public const RUNNING = 'running';
    public const PAUSING = 'pausing';
    public const PAUSED = 'paused';
    public const RESUMING = 'resuming';
    public const STOPPING = 'stopping';
    public const STOPPED = 'stopped';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const VERIFICATION_REQUIRED = 'verification_required';
    public const VERIFIED = 'verified';
}
