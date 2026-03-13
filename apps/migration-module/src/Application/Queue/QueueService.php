<?php

declare(strict_types=1);

namespace MigrationModule\Application\Queue;

use MigrationModule\Domain\Queue\QueueItem;

final class QueueService
{
    public function enqueue(QueueItem $item): void
    {
        // TODO: persist queue item with idempotent dedupe key.
    }

    /** @return array<int, QueueItem> */
    public function reserve(string $jobId, int $limit): array
    {
        // TODO: reserve available queue records for worker.
        return [];
    }
}
