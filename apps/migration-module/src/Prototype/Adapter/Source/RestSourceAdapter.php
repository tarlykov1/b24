<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Adapter\Source;

interface RestSourceAdapter
{
    /** @return array<int,array<string,mixed>> */
    public function fetch(string $entityType, int $offset, int $limit): array;

    /** @return list<string> */
    public function entityTypes(): array;
}
