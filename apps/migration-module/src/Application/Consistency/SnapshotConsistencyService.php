<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

use DateTimeImmutable;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class SnapshotConsistencyService
{
    private const TRACKED_ENTITIES = ['users', 'leads', 'contacts', 'companies', 'deals', 'tasks', 'comments', 'files', 'crm_activities', 'custom_fields', 'stages'];

    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /** @param array<string,mixed> $sourceMarkers */
    public function createSnapshot(string $jobId, array $sourceMarkers = []): array
    {
        $snapshotId = sprintf('snap-%s', bin2hex(random_bytes(4)));
        $startedAt = new DateTimeImmutable();
        $cutoff = $startedAt->format(DATE_ATOM);

        $watermarks = [];
        foreach (self::TRACKED_ENTITIES as $entityType) {
            $marker = $sourceMarkers[$entityType] ?? ['type' => 'timestamp', 'value' => $cutoff];
            $watermarks[$entityType] = [
                'last_extracted_source_marker' => $marker,
                'last_reconciled_source_marker' => null,
                'last_verified_source_marker' => null,
                'last_target_sync_marker' => null,
            ];
        }

        $snapshot = [
            'snapshot_id' => $snapshotId,
            'snapshot_started_at' => $startedAt->format(DATE_ATOM),
            'source_cutoff_time' => $cutoff,
            'per_entity_watermark' => $watermarks,
            'per_module_cursor' => [],
            'snapshot_status' => 'created',
        ];

        $this->repository->saveSnapshot($jobId, $snapshot);

        return $snapshot;
    }

    /** @param array<string,mixed> $marker */
    public function advanceWatermark(string $jobId, string $entityType, string $field, array $marker): void
    {
        $snapshot = $this->repository->snapshot($jobId);
        if ($snapshot === null) {
            return;
        }

        $snapshot['per_entity_watermark'][$entityType][$field] = $marker;
        $this->repository->saveSnapshot($jobId, $snapshot);
    }
}
