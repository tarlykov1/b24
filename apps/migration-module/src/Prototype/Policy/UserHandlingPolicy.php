<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Policy;

final class UserHandlingPolicy
{
    /** @param array<string,mixed> $user @param array<string,mixed> $config */
    public function apply(array $user, array $config): array
    {
        $cutoff = (string) ($config['cutoff_date'] ?? '2024-01-01T00:00:00+00:00');
        $strategy = (string) ($config['inactive_strategy'] ?? 'preserve_without_activation');

        $inactive = ($user['active'] ?? true) === false && (($user['updated_at'] ?? $cutoff) <= $cutoff);
        if (!$inactive) {
            return ['decision' => 'keep_user', 'user' => $user];
        }

        return match ($strategy) {
            'skip_user' => ['decision' => 'skip_user', 'user' => $user],
            'transfer_tasks_to_system_user' => ['decision' => 'transfer_tasks_to_system_user', 'user' => $user],
            'keep_user' => ['decision' => 'keep_user', 'user' => $user],
            default => ['decision' => 'preserve_without_activation', 'user' => $user],
        };
    }
}
