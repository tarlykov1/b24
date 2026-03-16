<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;
use MigrationModule\Preflight\PreflightHelper;

final class TargetPortalCleanlinessCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $counts = [
            'users' => count(PreflightHelper::recordsByAdapter($this->context->targetAdapter, 'users', 50, 400)),
            'tasks' => count(PreflightHelper::recordsByAdapter($this->context->targetAdapter, 'tasks.task', 50, 400)),
            'crm' => count(PreflightHelper::recordsByAdapter($this->context->targetAdapter, 'crm.deal', 50, 400)),
            'files' => count(PreflightHelper::recordsByAdapter($this->context->targetAdapter, 'files', 50, 400)),
        ];

        $status = 'ok';
        $details = 'Target portal appears clean for migration.';
        if ($counts['users'] > 20 || $counts['crm'] > 50 || $counts['tasks'] > 50) {
            $status = 'warning';
            $details = 'Target portal has existing business data above recommended thresholds.';
        }
        if ($counts['users'] > 300 || $counts['crm'] > 500 || $counts['tasks'] > 500) {
            $status = 'blocked';
            $details = 'Target looks like a production portal; migration is blocked to prevent overwrite risk.';
        }

        return new CheckResult('target_cleanliness', $status, $details, $counts, [
            'users_threshold' => '<=20',
            'crm_threshold' => '<=50',
            'tasks_threshold' => '<=50',
        ]);
    }
}
