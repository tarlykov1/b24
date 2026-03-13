<?php

declare(strict_types=1);

namespace MigrationModule\Application\Diff;

use MigrationModule\Domain\Diff\DiffCategory;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class DiffAnalysisService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /**
     * @param array<int,array<string,mixed>> $sourceSnapshot
     * @param array<int,array<string,mixed>> $targetSnapshot
     * @return array<string,mixed>
     */
    public function analyze(string $jobId, array $sourceSnapshot = [], array $targetSnapshot = []): array
    {
        $grouped = [
            DiffCategory::NEW_ENTITIES => [],
            DiffCategory::CHANGED_ENTITIES => [],
            'missing_relations' => [],
            'id_conflicts' => [],
        ];

        $targetByLegacy = [];
        foreach ($targetSnapshot as $row) {
            $targetByLegacy[(string) ($row['legacy_id'] ?? $row['id'])] = $row;
        }

        foreach ($sourceSnapshot as $source) {
            $legacyId = (string) $source['id'];
            if (!isset($targetByLegacy[$legacyId])) {
                $grouped[DiffCategory::NEW_ENTITIES][] = $source;
                continue;
            }

            $target = $targetByLegacy[$legacyId];
            if (($target['checksum'] ?? null) !== ($source['checksum'] ?? null)) {
                $grouped[DiffCategory::CHANGED_ENTITIES][] = ['source' => $source, 'target' => $target];
            }

            if (($target['id'] ?? '') !== $legacyId) {
                $grouped['id_conflicts'][] = ['source_id' => $legacyId, 'target_id' => (string) $target['id']];
            }
        }

        foreach ($grouped as $category => $items) {
            foreach ($items as $item) {
                $this->repository->saveDiff($jobId, ['category' => $category, 'payload' => $item]);
            }
        }

        return [
            'summary' => array_map('count', $grouped),
            'groups' => $grouped,
        ];
    }
}
