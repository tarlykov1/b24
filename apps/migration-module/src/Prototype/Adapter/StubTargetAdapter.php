<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter;

final class StubTargetAdapter implements TargetAdapterInterface
{
    /** @var array<string,array<string,array<string,mixed>>> */
    private array $target = [
        'users' => ['1' => ['id' => '1', 'name' => 'Reserved']],
        'crm' => [],
        'tasks' => [],
        'files' => [],
    ];

    public function upsert(string $entityType, array $entity, bool $dryRun): array
    {
        $id = (string) ($entity['id'] ?? '');
        $exists = isset($this->target[$entityType][$id]);

        if (!$dryRun) {
            $this->target[$entityType][$id] = $entity;
        }

        return ['action' => $exists ? 'update' : 'create', 'target_id' => $id];
    }

    public function exists(string $entityType, string $targetId): bool
    {
        return isset($this->target[$entityType][$targetId]);
    }

    public function apply(string $entityType, array $payload): array
    {
        return $this->upsert($entityType, $payload, false);
    }
}
