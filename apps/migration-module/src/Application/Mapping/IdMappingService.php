<?php

declare(strict_types=1);

namespace MigrationModule\Application\Mapping;

use MigrationModule\Domain\Mapping\MappingResult;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class IdMappingService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /** @param callable(string):bool $targetIdIsFree */
    public function map(string $jobId, string $entityType, string $sourceId, callable $targetIdIsFree): MappingResult
    {
        $existing = $this->repository->getMapping($jobId, $entityType, $sourceId);
        if ($existing !== null) {
            return new MappingResult($sourceId, $existing, $existing === $sourceId, null);
        }

        $targetId = $sourceId;
        $preserved = $targetIdIsFree($sourceId);
        if (!$preserved) {
            $targetId = $sourceId . '-remap-' . substr(sha1($jobId . ':' . $entityType . ':' . $sourceId), 0, 6);
        }

        $this->repository->putMapping($jobId, $entityType, $sourceId, $targetId);

        return new MappingResult($sourceId, $targetId, $preserved, $preserved ? null : 'target_id_conflict');
    }
}
