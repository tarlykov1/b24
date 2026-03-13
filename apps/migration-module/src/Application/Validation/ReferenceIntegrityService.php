<?php

declare(strict_types=1);

namespace MigrationModule\Application\Validation;

final class ReferenceIntegrityService
{
    /**
     * @param array<string, array<int, array<string, mixed>>> $snapshot
     * @return array<int, array<string, string|null>>
     */
    public function validate(array $snapshot): array
    {
        $errors = [];

        $users = $this->indexById($snapshot['users'] ?? []);
        $tasks = $this->indexById($snapshot['tasks'] ?? []);
        $comments = $snapshot['comments'] ?? [];
        $deals = $snapshot['crm_deals'] ?? [];
        $companies = $this->indexById($snapshot['crm_companies'] ?? []);
        $contacts = $this->indexById($snapshot['crm_contacts'] ?? []);

        foreach ($snapshot['tasks'] ?? [] as $task) {
            $this->missingRef($users, 'task', (string) $task['id'], (string) $task['id'], 'responsible_id:' . (string) $task['responsible_id'], (string) $task['responsible_id'], $errors);
            $this->missingRef($users, 'task', (string) $task['id'], (string) $task['id'], 'created_by:' . (string) $task['created_by'], (string) $task['created_by'], $errors);
        }

        foreach ($comments as $comment) {
            $this->missingRef($users, 'comment', (string) $comment['id'], (string) $comment['id'], 'author:' . (string) $comment['author'], (string) $comment['author'], $errors);
            $this->missingRef($tasks, 'comment', (string) $comment['id'], (string) $comment['id'], 'task_id:' . (string) $comment['task_id'], (string) $comment['task_id'], $errors);
        }

        foreach ($deals as $deal) {
            $this->missingRef($companies, 'crm_deal', (string) $deal['id'], (string) $deal['id'], 'company_id:' . (string) $deal['company_id'], (string) $deal['company_id'], $errors);
            $this->missingRef($contacts, 'crm_deal', (string) $deal['id'], (string) $deal['id'], 'contact_id:' . (string) $deal['contact_id'], (string) $deal['contact_id'], $errors);
        }

        foreach ($snapshot['files'] ?? [] as $file) {
            $entityType = (string) ($file['parent_entity_type'] ?? '');
            $entityId = (string) ($file['parent_entity_id'] ?? '');
            $index = $this->indexById($snapshot[$entityType] ?? []);
            $this->missingRef($index, 'file', (string) $file['id'], (string) $file['id'], 'owner:' . $entityType . ':' . $entityId, $entityId, $errors);
        }

        return $errors;
    }

    private function missingRef(array $index, string $type, string $oldId, string $newId, string $label, string $key, array &$errors): void
    {
        if (!isset($index[$key])) {
            $errors[] = [
                'type' => $type,
                'old_id' => $oldId,
                'new_id' => $newId,
                'missing_reference' => $label,
            ];
        }
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, array<string, mixed>> */
    private function indexById(array $records): array
    {
        $index = [];
        foreach ($records as $record) {
            $index[(string) $record['id']] = $record;
        }

        return $index;
    }
}
