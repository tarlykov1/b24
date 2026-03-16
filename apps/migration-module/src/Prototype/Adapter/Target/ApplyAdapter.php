<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter\Target;

interface ApplyAdapter
{
    /** @param array<string,mixed> $payload */
    public function apply(string $entityType, array $payload): array;
}
