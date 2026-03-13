<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\Contracts;

interface StateStoreInterface
{
    /** @param array<string,mixed> $state */
    public function saveGlobalState(string $jobId, array $state): void;

    /** @return array<string,mixed>|null */
    public function loadGlobalState(string $jobId): ?array;

    /** @param array<string,mixed> $decision */
    public function appendDecision(string $jobId, array $decision): void;

    /** @param array<string,mixed> $checkpoint */
    public function checkpoint(string $jobId, array $checkpoint): void;
}
