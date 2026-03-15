<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Job;

final class JobLifecycle
{
    public const CREATED = 'created';
    public const VALIDATED = 'validated';
    public const PLANNED = 'planned';
    public const DRY_RUN_COMPLETED = 'dry_run_completed';
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const PAUSED = 'paused';
    public const STOPPING = 'stopping';
    public const CANCELLED = 'cancelled';
    public const COMPLETED = 'completed';
    public const VERIFIED = 'verified';
    public const RECONCILED = 'reconciled';
    public const FAILED = 'failed';

    /** @return array<int, string> */
    public static function terminal(): array
    {
        return [self::CANCELLED, self::COMPLETED, self::FAILED];
    }

    /** @return array<string, array<int, string>> */
    public static function transitions(): array
    {
        return [
            self::CREATED => [self::VALIDATED, self::PLANNED, self::FAILED, self::CANCELLED],
            self::VALIDATED => [self::PLANNED, self::FAILED, self::CANCELLED],
            self::PLANNED => [self::DRY_RUN_COMPLETED, self::RUNNING, self::FAILED, self::CANCELLED],
            self::DRY_RUN_COMPLETED => [self::RUNNING, self::FAILED, self::CANCELLED],
            self::QUEUED => [self::RUNNING, self::FAILED, self::CANCELLED],
            self::RUNNING => [self::PAUSED, self::STOPPING, self::FAILED, self::COMPLETED],
            self::PAUSED => [self::RUNNING, self::CANCELLED, self::FAILED],
            self::STOPPING => [self::COMPLETED, self::FAILED, self::CANCELLED],
            self::COMPLETED => [self::VERIFIED],
            self::VERIFIED => [self::RECONCILED],
            self::RECONCILED => [],
            self::FAILED => [],
            self::CANCELLED => [],
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::transitions()[$from] ?? [], true);
    }
}
