<?php

declare(strict_types=1);

namespace MigrationModule\Application\Mapping;

use RuntimeException;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class ReferenceResolverService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    public function resolve(string $jobId, string $entityType, string $sourceId): string
    {
        $mapped = $this->repository->getMapping($jobId, $entityType, $sourceId);
        if ($mapped === null) {
            throw new RuntimeException(sprintf('Missing mapping for %s:%s', $entityType, $sourceId));
        }

        return $mapped;
    }
}
