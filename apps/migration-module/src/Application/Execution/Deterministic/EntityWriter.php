<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

final class EntityWriter
{
    public function __construct(private readonly RestWriteFacade $restWrite)
    {
    }

    public function write(string $entityType, array $entity, string $targetId): array
    {
        $entity['id'] = $targetId;

        return $this->restWrite->upsert($entityType, $entity);
    }
}
