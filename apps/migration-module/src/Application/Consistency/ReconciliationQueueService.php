<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

use DateTimeImmutable;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class ReconciliationQueueService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /** @param array<string,mixed> $item */
    public function enqueue(string $jobId, array $item): void
    {
        $item['retry_count'] = (int) ($item['retry_count'] ?? 0);
        $item['last_attempt'] = $item['last_attempt'] ?? null;
        $item['next_scheduled_attempt'] = $item['next_scheduled_attempt'] ?? (new DateTimeImmutable('+1 minute'))->format(DATE_ATOM);
        $item['escalation_state'] = $item['escalation_state'] ?? 'pending';
        $this->repository->enqueueReconciliationItem($jobId, $item);
    }

    /** @return array<int,array<string,mixed>> */
    public function dueItems(string $jobId): array
    {
        $now = new DateTimeImmutable();

        return array_values(array_filter(
            $this->repository->reconciliationQueue($jobId),
            static fn (array $item): bool => new DateTimeImmutable((string) $item['next_scheduled_attempt']) <= $now,
        ));
    }
}
