<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Http;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class AdminController
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /** @return array<string,mixed> */
    public function dashboard(string $jobId): array
    {
        return [
            'job' => $this->repository->getJob($jobId),
            'metrics' => $this->repository->metrics($jobId),
            'diff' => $this->repository->diffsByJob($jobId),
            'logs' => $this->repository->logsByJob($jobId),
        ];
    }

    public function index(): string
    {
        return 'Migration admin UI ready';
    }
}
