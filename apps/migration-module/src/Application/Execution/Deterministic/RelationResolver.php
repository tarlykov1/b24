<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Storage\SqliteStorage;

final class RelationResolver
{
    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    public function resolveOrQueue(string $planId, string $relationKey, string $ownerEntityType, string $ownerSourceId, string $targetEntityType, string $targetSourceId): array
    {
        $targetMap = $this->storage->findMapping($targetEntityType, $targetSourceId);
        $status = $targetMap === null ? 'unresolved' : 'resolved';
        $resolvedId = $targetMap['target_id'] ?? null;
        $this->storage->saveRelationMap($planId, $relationKey, $ownerEntityType, $ownerSourceId, $targetEntityType, $targetSourceId, $resolvedId, $status, $status === 'resolved' ? 'ok' : 'dependency_missing');

        return ['status' => $status, 'target_id' => $resolvedId];
    }
}
