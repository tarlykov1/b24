<?php

declare(strict_types=1);

namespace MigrationModule\Application\Audit;

use DateTimeImmutable;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class AuditService
{
    public function __construct(private readonly ?MigrationRepository $repository = null)
    {
    }

    /** @param array<string,int> $counts */
    public function collect(array $counts = []): array
    {
        return [
            'entity_counts' => $counts,
            'captured_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<string, array<int, array<string,mixed>>> $target
     * @return array<string, int|string>
     */
    public function snapshot(array $target): array
    {
        return [
            'users_count' => count($target['users'] ?? []),
            'tasks_count' => count($target['tasks'] ?? []),
            'comments_count' => count($target['comments'] ?? []),
            'groups_count' => count($target['groups'] ?? []),
            'migration_timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }

    /** @param array<string,mixed> $report */
    public function finalizeMigration(string $jobId, array $target, array $report): array
    {
        $snapshot = $this->snapshot($target);
        $finalReport = array_merge($report, ['final_snapshot' => $snapshot]);

        if ($this->repository !== null) {
            $this->repository->saveReport($jobId, $finalReport);
        }

        return [
            'status' => 'finalized',
            'snapshot' => $snapshot,
            'report' => $finalReport,
        ];
    }
}
