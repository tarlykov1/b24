<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Storage\MySqlStorage;

final class ReplayProtectionService
{
    public function __construct(private readonly MySqlStorage $storage)
    {
    }

    public function key(string $planId, string $phase, string $entityType, string $sourceId, string $payloadHash, string $relationContext = ''): string
    {
        return hash('sha256', implode('|', [$planId, $phase, $entityType, $sourceId, $payloadHash, $relationContext]));
    }

    public function alreadySuccessful(string $idempotencyKey): bool
    {
        return $this->storage->replayGuardStatus($idempotencyKey) === 'success';
    }

    public function remember(string $idempotencyKey, string $jobId, string $planId, string $phase, string $entityType, string $sourceId, string $payloadHash, string $status): void
    {
        $this->storage->saveReplayGuard($idempotencyKey, $jobId, $planId, $phase, $entityType, $sourceId, $payloadHash, $status);
    }
}
