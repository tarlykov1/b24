<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\Contracts;

interface ControlApiInterface
{
    public function isPauseRequested(string $jobId): bool;

    public function isSafeStopRequested(string $jobId): bool;

    public function hasRunConfirmation(string $jobId): bool;
}
