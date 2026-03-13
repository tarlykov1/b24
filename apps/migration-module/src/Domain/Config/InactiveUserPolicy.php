<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Config;

final class InactiveUserPolicy
{
    public const DELETE_TASKS = 'delete_tasks';
    public const REASSIGN_TO_SYSTEM = 'reassign_to_system';
    public const KEEP_USER = 'keep_user';

    public static function isSupported(string $policy): bool
    {
        return \in_array($policy, [self::DELETE_TASKS, self::REASSIGN_TO_SYSTEM, self::KEEP_USER], true);
    }
}
