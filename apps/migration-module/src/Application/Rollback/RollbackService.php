<?php

declare(strict_types=1);

namespace MigrationModule\Application\Rollback;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class RollbackService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    public function run(string $jobId, string $reason, string $stage, string $mode = 'safe', string $reportPath = 'reports/rollback_report.json'): array
    {
        $mappings = $this->repository->mappings($jobId);
        $deleted = 0;
        $notDeleted = 0;

        if ($mode === 'full') {
            foreach ($mappings as $key => $mappedId) {
                if (str_contains($key, 'files')) {
                    $notDeleted++;
                    continue;
                }

                $deleted++;
                $this->repository->appendChangeLog($jobId, ['action' => 'rollback_delete', 'entity' => $key, 'id' => $mappedId]);
            }
            $this->repository->clearMappings($jobId);
        }

        $this->repository->clearCheckpoints($jobId);
        $this->repository->setJobStatus($jobId, 'ROLLED_BACK');

        $report = [
            'reason' => $reason,
            'stage' => $stage,
            'mode' => $mode,
            'entities_deleted' => $deleted,
            'entities_unable_to_delete' => $notDeleted,
            'recommendations' => $mode === 'safe'
                ? ['Run full rollback only after impact review', 'Keep target portal in read-only mode']
                : ['Re-run verification', 'Restore from backup for entities_unable_to_delete'],
        ];

        if (!is_dir(dirname($reportPath))) {
            mkdir(dirname($reportPath), 0777, true);
        }
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $report;
    }
}
