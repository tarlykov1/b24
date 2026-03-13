<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence;

final class MigrationRepository
{
    public function beginJob(string $mode): string
    {
        // TODO: insert migration_job row and return job id.
        return 'todo-job-id';
    }
}
