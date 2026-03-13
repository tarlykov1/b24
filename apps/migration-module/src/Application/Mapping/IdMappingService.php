<?php

declare(strict_types=1);

namespace MigrationModule\Application\Mapping;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class IdMappingService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    public function map(string $jobId, string $entityType, int|string $sourceId, int|string $targetId): void
    {
        $this->repository->saveMapping($jobId, $entityType, $sourceId, $targetId);
    }

    public function resolve(string $jobId, string $entityType, int|string $sourceId): ?string
    {
        return $this->repository->findMappedId($jobId, $entityType, $sourceId);
    }
}
