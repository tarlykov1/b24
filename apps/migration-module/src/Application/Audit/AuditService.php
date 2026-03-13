<?php

declare(strict_types=1);

namespace MigrationModule\Application\Audit;

final class AuditService
{
    /** @param array<string,int> $counts */
    public function collect(array $counts = []): array
    {
        return [
            'entity_counts' => $counts,
            'captured_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
