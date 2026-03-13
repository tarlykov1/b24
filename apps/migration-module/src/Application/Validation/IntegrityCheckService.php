<?php

declare(strict_types=1);

namespace MigrationModule\Application\Validation;

final class IntegrityCheckService
{
    /**
     * @param array<string, array<int, array<string, mixed>>> $source
     * @param array<string, array<int, array<string, mixed>>> $target
     * @param array<string, string> $mappings
     * @return array{status:string, checks:array<int, array<string, mixed>>, problems:array<int, array<string, mixed>>}
     */
    public function run(array $source, array $target, array $mappings): array
    {
        $checks = [];
        $problems = [];

        $this->checkUsers($source['users'] ?? [], $target['users'] ?? [], $mappings, $checks, $problems);
        $this->checkTasks($source['tasks'] ?? [], $target['tasks'] ?? [], $mappings, $checks, $problems);
        $this->checkComments($source['comments'] ?? [], $target['comments'] ?? [], $mappings, $checks, $problems);
        $this->checkGenericCount('crm_contacts', $source, $target, $checks, $problems);
        $this->checkGenericCount('crm_companies', $source, $target, $checks, $problems);
        $this->checkGenericCount('crm_deals', $source, $target, $checks, $problems);
        $this->checkFiles($source['files'] ?? [], $target['files'] ?? [], $checks, $problems);
        $this->checkCustomFields($source['custom_fields'] ?? [], $target['custom_fields'] ?? [], $checks, $problems);

        return [
            'status' => $problems === [] ? 'pass' : 'fail',
            'checks' => $checks,
            'problems' => $problems,
        ];
    }

    /** @param array<int, array<string,mixed>> $sourceUsers @param array<int, array<string,mixed>> $targetUsers */
    private function checkUsers(array $sourceUsers, array $targetUsers, array $mappings, array &$checks, array &$problems): void
    {
        $checks[] = $this->countCheck('users.count', count($sourceUsers), count($targetUsers), $problems);

        $targetById = [];
        foreach ($targetUsers as $user) {
            $targetById[(string) $user['id']] = $user;
        }

        foreach ($sourceUsers as $sourceUser) {
            $mappedId = $mappings['user:' . (string) $sourceUser['id']] ?? null;
            if ($mappedId === null || !isset($targetById[$mappedId])) {
                $problems[] = $this->problem('user', (string) $sourceUser['id'], $mappedId, 'missing_mapping');
                continue;
            }

            $targetUser = $targetById[$mappedId];
            if (($targetUser['email'] ?? null) !== ($sourceUser['email'] ?? null)) {
                $problems[] = $this->problem('user', (string) $sourceUser['id'], $mappedId, 'email_mismatch');
            }
            if ((bool) ($targetUser['active'] ?? false) !== (bool) ($sourceUser['active'] ?? false)) {
                $problems[] = $this->problem('user', (string) $sourceUser['id'], $mappedId, 'activity_mismatch');
            }
        }
    }

    private function checkTasks(array $sourceTasks, array $targetTasks, array $mappings, array &$checks, array &$problems): void
    {
        $checks[] = $this->countCheck('tasks.count', count($sourceTasks), count($targetTasks), $problems);
        $targetById = [];
        foreach ($targetTasks as $task) {
            $targetById[(string) $task['id']] = $task;
        }

        foreach ($sourceTasks as $task) {
            $mappedId = $mappings['task:' . (string) $task['id']] ?? null;
            if ($mappedId === null || !isset($targetById[$mappedId])) {
                $problems[] = $this->problem('task', (string) $task['id'], $mappedId, 'missing_mapping');
                continue;
            }
            $targetTask = $targetById[$mappedId];
            foreach (['responsible_id', 'created_by', 'deadline', 'status'] as $field) {
                if (($targetTask[$field] ?? null) !== ($task[$field] ?? null)) {
                    $problems[] = $this->problem('task', (string) $task['id'], $mappedId, $field . '_mismatch');
                }
            }
        }
    }

    private function checkComments(array $sourceComments, array $targetComments, array $mappings, array &$checks, array &$problems): void
    {
        $checks[] = $this->countCheck('comments.count', count($sourceComments), count($targetComments), $problems);
        $targetById = [];
        foreach ($targetComments as $comment) {
            $targetById[(string) $comment['id']] = $comment;
        }

        foreach ($sourceComments as $comment) {
            $mappedId = $mappings['comment:' . (string) $comment['id']] ?? null;
            if ($mappedId === null || !isset($targetById[$mappedId])) {
                $problems[] = $this->problem('comment', (string) $comment['id'], $mappedId, 'missing_mapping');
                continue;
            }
            $targetComment = $targetById[$mappedId];
            foreach (['author', 'task_id', 'created_at'] as $field) {
                if (($targetComment[$field] ?? null) !== ($comment[$field] ?? null)) {
                    $problems[] = $this->problem('comment', (string) $comment['id'], $mappedId, $field . '_mismatch');
                }
            }
        }
    }

    private function checkFiles(array $sourceFiles, array $targetFiles, array &$checks, array &$problems): void
    {
        $checks[] = $this->countCheck('files.count', count($sourceFiles), count($targetFiles), $problems);
        foreach ($targetFiles as $file) {
            if (!isset($file['present']) || $file['present'] !== true) {
                $problems[] = $this->problem('file', (string) ($file['id'] ?? 'unknown'), (string) ($file['id'] ?? 'unknown'), 'missing_file_blob');
            }
            foreach (['owner_id', 'parent_entity'] as $field) {
                if (!isset($file[$field])) {
                    $problems[] = $this->problem('file', (string) ($file['id'] ?? 'unknown'), (string) ($file['id'] ?? 'unknown'), $field . '_missing');
                }
            }
        }
    }

    private function checkCustomFields(array $source, array $target, array &$checks, array &$problems): void
    {
        $checks[] = $this->countCheck('custom_fields.count', count($source), count($target), $problems);

        $targetByCode = [];
        foreach ($target as $field) {
            $targetByCode[(string) $field['code']] = $field;
        }

        foreach ($source as $field) {
            $code = (string) ($field['code'] ?? '');
            if (!isset($targetByCode[$code])) {
                $problems[] = $this->problem('custom_field', $code, null, 'missing_field');
                continue;
            }
            if (($targetByCode[$code]['value'] ?? null) !== ($field['value'] ?? null)) {
                $problems[] = $this->problem('custom_field', $code, $code, 'invalid_value');
            }
        }
    }

    private function checkGenericCount(string $entityType, array $source, array $target, array &$checks, array &$problems): void
    {
        $checks[] = $this->countCheck($entityType . '.count', count($source[$entityType] ?? []), count($target[$entityType] ?? []), $problems);
    }

    private function countCheck(string $name, int $sourceCount, int $targetCount, array &$problems): array
    {
        $ok = $sourceCount === $targetCount;
        if (!$ok) {
            $problems[] = ['type' => $name, 'old_id' => null, 'new_id' => null, 'missing_reference' => 'count_mismatch'];
        }

        return ['name' => $name, 'source' => $sourceCount, 'target' => $targetCount, 'result' => $ok ? 'pass' : 'fail'];
    }

    private function problem(string $entityType, ?string $oldId, ?string $newId, string $missingReference): array
    {
        return [
            'type' => $entityType,
            'old_id' => $oldId,
            'new_id' => $newId,
            'missing_reference' => $missingReference,
        ];
    }
}
