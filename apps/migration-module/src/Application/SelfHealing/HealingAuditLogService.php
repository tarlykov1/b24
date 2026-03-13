<?php

declare(strict_types=1);

namespace MigrationModule\Application\SelfHealing;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class HealingAuditLogService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /** @param array<string,mixed> $record */
    public function log(string $jobId, array $record): void
    {
        $record['timestamp'] = $record['timestamp'] ?? gmdate(DATE_ATOM);
        $this->repository->appendHealingAuditLog($jobId, $record);
    }

    /** @return array<string,int> */
    public function stats(string $jobId): array
    {
        $stats = [
            'auto_fixed_count' => 0,
            'retried_successfully' => 0,
            'quarantined' => 0,
            'unresolved' => 0,
            'unsafe_to_heal_cases' => 0,
        ];

        foreach ($this->repository->healingAuditLog($jobId) as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'resolved') {
                $stats['auto_fixed_count']++;
            }
            if ($status === 'retried_successfully') {
                $stats['retried_successfully']++;
            }
            if ($status === 'quarantine') {
                $stats['quarantined']++;
            }
            if ($status === 'unresolved') {
                $stats['unresolved']++;
            }
            if (($row['safe_to_heal'] ?? true) === false) {
                $stats['unsafe_to_heal_cases']++;
            }
        }

        return $stats;
    }
}
