<?php

declare(strict_types=1);

namespace MigrationModule\Application\Diff;

final class DiffAnalysisService
{
    /** @return array<string, mixed> */
    public function analyze(string $jobId): array
    {
        // TODO: compare source changes since checkpoint and categorize diffs.
        return [];
    }
}
