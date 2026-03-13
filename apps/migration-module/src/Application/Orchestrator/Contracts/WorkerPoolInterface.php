<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\Contracts;

interface WorkerPoolInterface
{
    /**
     * @param array<string,mixed> $task
     * @return array<string,mixed>
     */
    public function execute(array $task): array;
}
