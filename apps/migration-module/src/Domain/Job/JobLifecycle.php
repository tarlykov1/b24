<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Job;

final class JobLifecycle
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const PAUSED = 'paused';
    public const STOPPING = 'stopping';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    /** @return array<int, string> */
    public static function terminal(): array
    {
        return [self::CANCELLED, self::COMPLETED, self::FAILED];
    }
}
