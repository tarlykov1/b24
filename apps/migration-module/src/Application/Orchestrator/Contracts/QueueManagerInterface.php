<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\Contracts;

interface QueueManagerInterface
{
    /** @param array<string,mixed> $plan */
    public function seed(string $jobId, array $plan): void;

    /** @return array<string,mixed>|null */
    public function pullNext(string $jobId): ?array;

    /** @param array<string,mixed> $task */
    public function ack(string $jobId, array $task): void;

    /** @param array<string,mixed> $task */
    public function deadLetter(string $jobId, array $task, string $reason): void;
}
