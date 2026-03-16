<?php

declare(strict_types=1);

namespace MigrationModule\Preflight\Checks;

use MigrationModule\Preflight\CheckContext;
use MigrationModule\Preflight\CheckInterface;
use MigrationModule\Preflight\CheckResult;
use MigrationModule\Preflight\PreflightHelper;

final class EntityCountDiscoveryCheck implements CheckInterface
{
    public function __construct(private readonly CheckContext $context)
    {
    }

    public function run(): CheckResult
    {
        $users = PreflightHelper::recordsByAdapter($this->context->sourceAdapter, 'users', 100, 3000);
        $tasks = PreflightHelper::recordsByAdapter($this->context->sourceAdapter, 'tasks.task', 100, 3000);
        $files = PreflightHelper::recordsByAdapter($this->context->sourceAdapter, 'files', 100, 3000);
        $crm = PreflightHelper::recordsByAdapter($this->context->sourceAdapter, 'crm.deal', 100, 3000);
        $comments = PreflightHelper::recordsByAdapter($this->context->sourceAdapter, 'task.comments', 100, 3000);

        $userIds = [];
        foreach ($users as $user) {
            $id = (string) ($user['ID'] ?? $user['id'] ?? '');
            if ($id !== '') {
                $userIds[$id] = true;
            }
        }

        $orphanTasks = 0;
        foreach ($tasks as $task) {
            $owner = (string) ($task['responsible_id'] ?? $task['RESPONSIBLE_ID'] ?? '');
            if ($owner !== '' && !isset($userIds[$owner])) {
                $orphanTasks++;
            }
        }

        $corruptedCrm = 0;
        foreach ($crm as $deal) {
            $company = (string) ($deal['COMPANY_ID'] ?? $deal['company_id'] ?? '');
            $contact = (string) ($deal['CONTACT_ID'] ?? $deal['contact_id'] ?? '');
            if ($company === '' && $contact === '') {
                $corruptedCrm++;
            }
        }

        $apiCalls = (int) ceil((count($users) + count($tasks) + count($files) + count($crm) + count($comments)) / 50);
        $runtimeMinutes = max(1, (int) ceil($apiCalls / 30));

        $status = ($orphanTasks + $corruptedCrm) > 0 ? 'warning' : 'ok';

        return new CheckResult('entity_count_discovery', $status, 'Entity volumes estimated from bounded source samples.', [
            'users' => count($users),
            'tasks' => count($tasks),
            'files' => count($files),
            'crm' => count($crm),
            'comments' => count($comments),
            'estimated_runtime' => $runtimeMinutes . 'm',
            'estimated_api_calls' => $apiCalls,
            'estimated_disk_usage' => count($files) * 250000,
            'risks' => [
                'missing_users' => 0,
                'orphan_tasks' => $orphanTasks,
                'corrupted_crm_references' => $corruptedCrm,
            ],
        ]);
    }
}
