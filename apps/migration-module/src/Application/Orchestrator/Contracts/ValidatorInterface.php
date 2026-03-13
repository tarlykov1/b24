<?php

declare(strict_types=1);

namespace MigrationModule\Application\Orchestrator\Contracts;

interface ValidatorInterface
{
    /** @param array<string,mixed> $batchResult @return array<string,mixed> */
    public function validateBatch(string $jobId, array $batchResult): array;
}
