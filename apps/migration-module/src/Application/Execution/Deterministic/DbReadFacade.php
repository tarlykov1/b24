<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Adapter\SourceAdapterInterface;

final class DbReadFacade
{
    public function __construct(private readonly SourceAdapterInterface $source)
    {
    }

    public function discover(string $entityType, int $offset, int $limit): array
    {
        return $this->source->fetch($entityType, $offset, $limit);
    }
}
