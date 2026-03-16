<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Storage\SqliteStorage;

final class CheckpointManager
{
    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    public function save(string $jobId, string $planId, string $phase, ?string $cursor, array $payload = []): void
    {
        $this->storage->saveCheckpointState($jobId, $planId, $phase, $cursor, $payload);
    }

    public function list(string $jobId): array
    {
        return $this->storage->listCheckpointState($jobId);
    }
}
