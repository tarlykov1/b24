<?php

declare(strict_types=1);

namespace MigrationModule\Application\Freeze;

final class FreezeModeService
{
    /** @param array<string,mixed> $capabilities */
    public function activate(array $capabilities): array
    {
        $freezePossible = (bool) ($capabilities['freeze_supported'] ?? false);
        if (!$freezePossible) {
            return [
                'enabled' => false,
                'warning' => 'Freeze mode unavailable. Switching to continuous delta sync.',
                'fallback' => 'continuous_delta_sync',
                'actions' => ['notify_operator'],
            ];
        }

        return [
            'enabled' => true,
            'actions' => [
                'warn_users',
                'disable_automations',
                'notify_admins',
                'log_changes_during_freeze',
            ],
        ];
    }
}
