<?php

declare(strict_types=1);

namespace MigrationModule\Application\Queue;

use MigrationModule\Application\Throttling\ThrottlingService;
use MigrationModule\Domain\Queue\QueueItem;
use SplQueue;

final class QueueService
{
    /** @var SplQueue<QueueItem> */
    private SplQueue $queue;

    /** @var array<string,array<string,bool>> */
    private array $done = [];

    public function __construct(private readonly ThrottlingService $throttlingService)
    {
        $this->queue = new SplQueue();
    }

    public function enqueue(QueueItem|string $itemOrJobId, ?QueueItem $item = null): void
    {
        if ($itemOrJobId instanceof QueueItem) {
            $this->queue->enqueue($itemOrJobId);
            return;
        }

        if ($item instanceof QueueItem) {
            $this->queue->enqueue($item);
        }
    }

    /** @return array<int, QueueItem> */
    public function reserve(string $jobId, int $limit): array
    {
        $reserved = [];
        while (!$this->queue->isEmpty() && count($reserved) < $limit) {
            $this->throttlingService->throttle();
            $reserved[] = $this->queue->dequeue();
        }

        return $reserved;
    }

    public function markDone(string $jobId, string $itemId): void
    {
        $this->done[$jobId][$itemId] = true;
    }
}
