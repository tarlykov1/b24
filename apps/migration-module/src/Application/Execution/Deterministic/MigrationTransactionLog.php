<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Storage\SqliteStorage;

final class MigrationTransactionLog
{
    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    public function step(array $record): void
    {
        $this->storage->saveExecutionStep($record);
    }

    public function failure(array $record): void
    {
        $this->storage->saveFailureEvent($record);
    }
}
