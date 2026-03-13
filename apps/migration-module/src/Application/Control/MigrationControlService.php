<?php

declare(strict_types=1);

namespace MigrationModule\Application\Control;

use MigrationModule\Application\State\JobStateService;
use MigrationModule\Domain\Job\JobLifecycle;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class MigrationControlService
{
    public function __construct(
        private readonly MigrationRepository $repository,
        private readonly JobStateService $stateService,
    ) {
    }

    public function start(string $jobId): void
    {
        $this->repository->updateJobStatus($jobId, JobLifecycle::RUNNING);
        $this->stateService->checkpoint($jobId, 'lifecycle', JobLifecycle::RUNNING);
    }

    public function pause(string $jobId): void
    {
        $this->repository->updateJobStatus($jobId, JobLifecycle::PAUSED);
        $this->stateService->checkpoint($jobId, 'lifecycle', JobLifecycle::PAUSED, ['soft' => true]);
    }

    public function resume(string $jobId): void
    {
        $this->repository->updateJobStatus($jobId, JobLifecycle::RUNNING);
        $this->stateService->checkpoint($jobId, 'lifecycle', JobLifecycle::RUNNING, ['resumed' => true]);
    }

    public function softStop(string $jobId): void
    {
        $this->repository->updateJobStatus($jobId, JobLifecycle::STOPPING);
        $this->stateService->checkpoint($jobId, 'lifecycle', JobLifecycle::STOPPING);
    }

    public function cancel(string $jobId): void
    {
        $this->repository->updateJobStatus($jobId, JobLifecycle::CANCELLED);
        $this->stateService->checkpoint($jobId, 'lifecycle', JobLifecycle::CANCELLED);
    }
}
