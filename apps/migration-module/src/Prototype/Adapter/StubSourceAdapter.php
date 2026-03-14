<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter;

final class StubSourceAdapter implements SourceAdapterInterface
{
    /** @var array<string,list<array<string,mixed>>> */
    private array $data;

    public function __construct()
    {
        $this->data = [
            'users' => [
                ['id' => '1', 'name' => 'Alice', 'active' => false, 'updated_at' => '2024-01-01T00:00:00+00:00'],
                ['id' => '2', 'name' => 'Bob', 'active' => true, 'updated_at' => '2025-01-01T00:00:00+00:00'],
            ],
            'crm' => [['id' => '10', 'title' => 'Deal A', 'updated_at' => '2025-02-10T00:00:00+00:00']],
            'tasks' => [
                ['id' => '20', 'title' => 'Task A', 'responsible_id' => '1', 'updated_at' => '2025-02-11T00:00:00+00:00'],
                ['id' => '21', 'title' => 'Task B', 'responsible_id' => '2', 'simulate_error' => 'transient', 'updated_at' => '2025-02-12T00:00:00+00:00'],
            ],
            'files' => [['id' => '30', 'name' => 'spec.pdf', 'updated_at' => '2025-02-13T00:00:00+00:00']],
        ];
    }

    public function fetch(string $entityType, int $offset, int $limit): array
    {
        return array_slice($this->data[$entityType] ?? [], $offset, $limit);
    }

    public function entityTypes(): array
    {
        return array_keys($this->data);
    }
}
