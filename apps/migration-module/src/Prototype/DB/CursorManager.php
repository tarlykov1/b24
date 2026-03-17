<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\DB;

use MigrationModule\Prototype\Storage\MySqlStorage;

final class CursorManager
{
    public function __construct(private readonly MySqlStorage $storage)
    {
    }

    public function load(string $jobId, string $entityType, string $table): ?array
    {
        return $this->storage->cursor($jobId, $entityType, $table);
    }

    public function save(string $jobId, string $entityType, string $table, string $strategy, ?string $lastId, ?string $lastTimestamp, ?string $batchStart, ?string $batchEnd): void
    {
        $this->storage->saveCursor($jobId, $entityType, $table, $strategy, $lastId, $lastTimestamp, $batchStart, $batchEnd);
    }

    /** @return array<int,array<string,mixed>> */
    public function list(string $jobId): array
    {
        return $this->storage->cursors($jobId);
    }
}
