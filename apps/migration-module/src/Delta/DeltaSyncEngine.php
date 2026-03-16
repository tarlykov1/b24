<?php

declare(strict_types=1);

namespace MigrationModule\Delta;

use MigrationModule\Prototype\Adapter\SourceAdapterInterface;
use MigrationModule\Prototype\Adapter\TargetAdapterInterface;
use MigrationModule\Prototype\Storage\SqliteStorage;

final class DeltaSyncEngine
{
    public function __construct(
        private readonly SqliteStorage $storage,
        private readonly SourceAdapterInterface $source,
        private readonly TargetAdapterInterface $target,
    ) {
    }

    public function run(string $jobId, string $entity, string $since, int $batchSize, int $rateLimit, bool $dryRun): array
    {
        $offset = 0;
        $scanned = 0;
        $enqueued = 0;
        $applied = 0;
        $latestTimestamp = $since;

        while (true) {
            $batch = $this->source->fetch($entity, $offset, $batchSize);
            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $scanned++;
                $id = (string) ($row['id'] ?? '');
                if ($id === '') {
                    continue;
                }

                $updatedAt = (string) ($row['updated_at'] ?? $row['date_modify'] ?? $row['activity_timestamp'] ?? $since);
                $latestTimestamp = max($latestTimestamp, $updatedAt);
                if ($updatedAt < $since) {
                    continue;
                }

                $mapping = $this->storage->findMapping($entity, $id);
                $changeType = $mapping === null ? 'new' : 'updated';
                if (isset($row['deleted']) && (bool) $row['deleted'] === true) {
                    $changeType = 'deleted';
                }

                $this->storage->enqueueDelta($jobId, $entity, $id, $changeType, $row);
                $enqueued++;
                if (!$dryRun && in_array($changeType, ['new', 'updated'], true)) {
                    $this->target->upsert($entity, $row, false);
                    $this->storage->saveMapping($jobId, $entity, $id, $id, sha1((string) json_encode($row)), 'delta_synced', 0);
                    $applied++;
                }

                if (!$dryRun && $changeType === 'deleted') {
                    $applied++;
                }

                $this->throttle($rateLimit);
            }

            $offset += count($batch);
        }

        $queuePending = count($this->storage->pendingDeltaQueue($jobId, $entity, 1000000));

        return [
            'job_id' => $jobId,
            'entity' => $entity,
            'since' => $since,
            'scanned' => $scanned,
            'enqueued' => $enqueued,
            'applied' => $applied,
            'pending_delta_queue' => $queuePending,
            'last_sync_timestamp' => $latestTimestamp,
            'dry_run' => $dryRun,
        ];
    }

    private function throttle(int $rateLimit): void
    {
        if ($rateLimit <= 0) {
            return;
        }

        usleep((int) floor(1_000_000 / $rateLimit));
    }
}
