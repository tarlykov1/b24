<?php

declare(strict_types=1);

namespace MigrationModule\Cli;

use MigrationModule\Application\Verification\VerificationService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class MigrationCommands
{
    public function __construct(
        private readonly MigrationRepository $repository,
        private readonly VerificationService $verificationService,
    ) {
    }

    public function preflight(): int { return 0; }

    public function audit(): int { return 0; }

    public function createJob(string $mode = 'initial'): string
    {
        return $this->repository->beginJob($mode);
    }

    public function startJob(): int { return 0; }

    public function pauseJob(): int { return 0; }

    public function resumeJob(): int { return 0; }

    public function stopJob(): int { return 0; }

    public function diff(): int { return 0; }

    /** @return array<string, mixed> */
    public function verify(string $jobId, bool $validationOnly = false): array
    {
        return $this->verificationService->verify($jobId, $validationOnly);
    }
}
