<?php

declare(strict_types=1);

namespace MigrationModule\Application\Reconciliation;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class PostMigrationReconciliationService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $source
     * @param array<string, array<int, array<string, mixed>>> $target
     * @return array<string,mixed>
     */
    public function reconcile(string $jobId, array $source, array $target): array
    {
        $groups = ['users', 'crm_companies', 'crm_contacts', 'crm_deals', 'crm_leads', 'tasks', 'comments', 'files', 'custom_fields', 'dictionaries'];
        $result = ['groups' => [], 'unresolved_links' => []];

        foreach ($groups as $group) {
            $sourceRows = $source[$group] ?? [];
            $targetRows = $target[$group] ?? [];
            $targetById = $this->indexById($targetRows);
            $matched = 0;
            $mismatched = 0;
            $missing = 0;
            $conflicts = 0;

            foreach ($sourceRows as $row) {
                $sourceId = (string) ($row['id'] ?? '');
                $mapped = $this->repository->findMappedId($jobId, $this->normalizeEntityType($group), $sourceId) ?? $sourceId;
                $targetRow = $targetById[$mapped] ?? null;

                if ($targetRow === null) {
                    $missing++;
                    continue;
                }

                if ($this->matchesKeyFields($row, $targetRow)) {
                    $matched++;
                } else {
                    $mismatched++;
                    $conflicts++;
                }
            }

            $extra = max(0, count($targetRows) - $matched);
            $result['groups'][$group] = [
                'total_source' => count($sourceRows),
                'total_target' => count($targetRows),
                'matched' => $matched,
                'mismatched' => $mismatched,
                'missing_in_target' => $missing,
                'extra_in_target' => $extra,
                'conflicts' => $conflicts,
            ];
        }

        $result['unresolved_links'] = $this->verifyLinks($source, $target, $jobId);

        return $result;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<string,array<string,mixed>> */
    private function indexById(array $rows): array
    {
        $idx = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $idx[(string) $row['id']] = $row;
            }
        }

        return $idx;
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target */
    private function matchesKeyFields(array $source, array $target): bool
    {
        foreach (['title', 'name', 'email', 'phone', 'status', 'stage_id'] as $field) {
            if (isset($source[$field], $target[$field]) && (string) $source[$field] !== (string) $target[$field]) {
                return false;
            }
        }

        return true;
    }

    private function normalizeEntityType(string $group): string
    {
        return match ($group) {
            'users' => 'user',
            'tasks' => 'task',
            'comments' => 'comment',
            default => rtrim($group, 's'),
        };
    }

    /** @param array<string, array<int, array<string, mixed>>> $source @param array<string, array<int, array<string, mixed>>> $target
     * @return array<int,array<string,mixed>>
     */
    private function verifyLinks(array $source, array $target, string $jobId): array
    {
        $unresolved = [];
        $contacts = $this->indexById($target['crm_contacts'] ?? []);
        $companies = $this->indexById($target['crm_companies'] ?? []);
        $users = $this->indexById($target['users'] ?? []);
        $tasks = $this->indexById($target['tasks'] ?? []);

        foreach (($source['crm_deals'] ?? []) as $deal) {
            if (!empty($deal['contact_id']) && !isset($contacts[(string) $deal['contact_id']])) {
                $unresolved[] = ['entity' => 'crm_deals', 'id' => (string) $deal['id'], 'relation' => 'contact_id', 'status' => 'conflict'];
            }
            if (!empty($deal['company_id']) && !isset($companies[(string) $deal['company_id']])) {
                $unresolved[] = ['entity' => 'crm_deals', 'id' => (string) $deal['id'], 'relation' => 'company_id', 'status' => 'conflict'];
            }
        }

        foreach (($source['tasks'] ?? []) as $task) {
            foreach (['created_by', 'responsible_id'] as $field) {
                if (!empty($task[$field]) && !isset($users[(string) $task[$field]])) {
                    $unresolved[] = ['entity' => 'tasks', 'id' => (string) $task['id'], 'relation' => $field, 'status' => 'warning'];
                }
            }
        }

        foreach (($source['comments'] ?? []) as $comment) {
            if (!empty($comment['task_id']) && !isset($tasks[(string) $comment['task_id']])) {
                $unresolved[] = ['entity' => 'comments', 'id' => (string) $comment['id'], 'relation' => 'task_id', 'status' => 'warning'];
            }
        }

        return $unresolved;
    }
}
