<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Adapter\TargetAdapterInterface;

final class RestWriteFacade
{
    public function __construct(private readonly TargetAdapterInterface $target)
    {
    }

    public function upsert(string $entityType, array $payload): array
    {
        return $this->target->upsert($entityType, $payload, false);
    }
}
