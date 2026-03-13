<?php

declare(strict_types=1);

namespace MigrationModule\Application\Safeguard;

use LogicException;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class DataSafeguardService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    public function confirmDestructiveOperation(string $jobId): void
    {
        $this->repository->markDestructiveConfirmed($jobId);
    }

    /** @param array<string, mixed> $context */
    public function assertDestructiveAllowed(string $jobId, array $context): void
    {
        if (!$this->repository->isDestructiveConfirmed($jobId)) {
            throw new LogicException('Destructive operation denied: explicit confirmation required.');
        }

        $this->repository->appendChangeLog($jobId, [
            'ts' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'type' => 'destructive_operation',
            'context' => $context,
        ]);
    }

    /** @param array<string, mixed> $context */
    public function logMutation(string $jobId, string $entityType, string $action, array $context = []): void
    {
        $this->repository->appendChangeLog($jobId, [
            'ts' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'entity_type' => $entityType,
            'action' => $action,
            'context' => $context,
        ]);
    }
}
