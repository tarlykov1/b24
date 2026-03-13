<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\Contracts;

interface ReconcilerInterface
{
    /** @return array<string,mixed> */
    public function reconcile(string $jobId, bool $deltaOnly): array;
}
