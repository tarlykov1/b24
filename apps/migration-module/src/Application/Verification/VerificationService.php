<?php

declare(strict_types=1);

namespace MigrationModule\Application\Verification;

final class VerificationService
{
    /** @return array<string, mixed> */
    public function verify(string $jobId): array
    {
        // TODO: validate counts, mapping completeness, and relation integrity.
        return [];
    }
}
