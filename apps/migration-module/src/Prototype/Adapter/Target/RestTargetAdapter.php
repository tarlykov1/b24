<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter\Target;

interface RestTargetAdapter
{
    /** @param array<string,mixed> $entity */
    public function upsert(string $entityType, array $entity, bool $dryRun): array;

    public function exists(string $entityType, string $targetId): bool;
}
