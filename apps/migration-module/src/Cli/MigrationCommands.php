<?php

declare(strict_types=1);

namespace MigrationModule\Cli;

use MigrationModule\Application\Control\MigrationControlService;
use MigrationModule\Application\Preflight\PreflightService;
use MigrationModule\Domain\Config\JobSettings;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class MigrationCommands
{
    public function __construct(
        private readonly MigrationRepository $repository,
        private readonly PreflightService $preflight,
        private readonly MigrationControlService $control,
    ) {
    }

    public function preflight(JobSettings $settings): int
    {
        $result = $this->preflight->run($settings);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        return $result['status'] === 'ok' ? 0 : 1;
    }

    public function createJob(JobSettings $settings): string
    {
        return $this->repository->beginJob($settings->mode, $settings->toArray());
    }

    public function startJob(string $jobId): int
    {
        $this->control->start($jobId);
        return 0;
    }

    public function pauseJob(string $jobId): int
    {
        $this->control->pause($jobId);
        return 0;
    }

    public function resumeJob(string $jobId): int
    {
        $this->control->resume($jobId);
        return 0;
    }

    public function stopJob(string $jobId): int
    {
        $this->control->cancel($jobId);
        return 0;
    }
}
