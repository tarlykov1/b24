<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Storage\MySqlStorage;

final class CheckpointManager
{
    public function __construct(private readonly MySqlStorage $storage)
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
