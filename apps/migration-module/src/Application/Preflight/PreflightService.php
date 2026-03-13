<?php

declare(strict_types=1);

namespace MigrationModule\Application\Preflight;

use MigrationModule\Domain\Config\InactiveUserPolicy;
use MigrationModule\Domain\Config\JobSettings;

final class PreflightService
{
    /** @return array{status:string,checks:array<int,array<string,mixed>>} */
    public function run(JobSettings $settings): array
    {
        $checks = [
            ['name' => 'run_mode_supported', 'status' => $settings->mode !== '' ? 'ok' : 'fail'],
            ['name' => 'inactive_user_policy_supported', 'status' => InactiveUserPolicy::isSupported($settings->inactiveUserPolicy) ? 'ok' : 'fail'],
        ];

        if ($settings->inactiveUserPolicy === InactiveUserPolicy::REASSIGN_TO_SYSTEM) {
            $checks[] = [
                'name' => 'system_account_id_required',
                'status' => $settings->systemAccountId !== null && $settings->systemAccountId !== '' ? 'ok' : 'fail',
                'message' => 'System account id must be configured for reassignment.',
            ];
        }

        $status = \in_array('fail', array_column($checks, 'status'), true) ? 'fail' : 'ok';

        return ['status' => $status, 'checks' => $checks];
    }
}
