<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\Contracts;

interface MapperInterface
{
    public function mapId(string $jobId, string $entityType, int|string $sourceId): int|string;

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function remapReferences(string $jobId, string $entityType, array $payload): array;
}
