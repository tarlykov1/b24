<?php

declare(strict_types=1);

namespace MigrationModule\Application\Queue;

use MigrationModule\Domain\Queue\QueueItem;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class QueueService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    public function enqueue(string $jobId, QueueItem $item): bool
    {
        return $this->repository->enqueue($jobId, [
            'entity_type' => $item->entityType,
            'operation' => $item->operation,
            'dedupe_key' => $item->dedupeKey,
            'payload' => $item->payload,
        ]);
    }

    /** @return array<int, QueueItem> */
    public function reserve(string $jobId, int $limit): array
    {
        return array_map(static fn (array $row): QueueItem => new QueueItem(
            (string) $row['entity_type'],
            (string) $row['operation'],
            (string) $row['dedupe_key'],
            (array) $row['payload'],
        ), $this->repository->reserveQueue($jobId, $limit));
    }

    public function markDone(string $jobId, string $dedupeKey): void
    {
        $this->repository->completeQueueItem($jobId, $dedupeKey);
    }
}
