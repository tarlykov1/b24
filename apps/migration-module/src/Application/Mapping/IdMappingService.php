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
        $this->repository->saveIdentityMapping(
            $jobId,
            $entityType,
            $sourceId,
            $targetId,
            $sourceId === $targetId ? 'exact_id' : 'id_remap',
            'v2-scalable',
            null,
            date(DATE_ATOM),
        );
    }

    /** @param array<string,mixed> $source */
    public function mapWithMetadata(string $jobId, string $entityType, array $source, int|string $targetId, string $method): void
    {
        $sourceId = (string) $source['id'];
        $signature = hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->repository->saveMapping($jobId, $entityType, $sourceId, $targetId);
        $this->repository->saveIdentityMapping(
            $jobId,
            $entityType,
            $sourceId,
            $targetId,
            $method,
            'v2-scalable',
            $signature,
            date(DATE_ATOM),
        );
    }

    public function resolve(string $jobId, string $entityType, int|string $sourceId): ?string
    {
        return $this->repository->findMappedId($jobId, $entityType, $sourceId);
    }

    /** @return array<string,mixed>|null */
    public function resolveIdentity(string $jobId, string $entityType, int|string $sourceId): ?array
    {
        return $this->repository->findIdentityMapping($jobId, $entityType, $sourceId);
    }
}
