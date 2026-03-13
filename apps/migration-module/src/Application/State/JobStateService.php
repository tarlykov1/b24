<?php

declare(strict_types=1);

namespace MigrationModule\Application\State;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class JobStateService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    public function checkpoint(string $jobId, string $scope, string $value, array $meta = []): void
    {
        $this->repository->saveCheckpoint($jobId, $scope, $value, $meta);
    }

    /** @return array{value:string,meta:array<string,mixed>}|null */
    public function getCheckpoint(string $jobId, string $scope): ?array
    {
        return $this->repository->getCheckpoint($jobId, $scope);
    }

    /** @return array<string,int|float> */
    public function metrics(string $jobId): array
    {
        return $this->repository->metrics($jobId);
    }

    public function incrementMetric(string $jobId, string $metric, int|float $value = 1): void
    {
        $this->repository->incrementMetric($jobId, $metric, $value);
    }
}
