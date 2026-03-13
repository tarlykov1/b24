<?php

declare(strict_types=1);

namespace MigrationModule\Application\Verification;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class VerificationService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /** @return array<string,mixed> */
    public function verify(string $jobId): array
    {
        $metrics = $this->repository->metrics($jobId);
        $diffs = $this->repository->diffsByJob($jobId);

        return [
            'job_id' => $jobId,
            'metrics' => $metrics,
            'open_diff_items' => count($diffs),
            'integrity_ok' => !isset($metrics['integrity_failures']) || $metrics['integrity_failures'] === 0,
        ];
    }
}
