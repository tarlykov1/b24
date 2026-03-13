<?php

declare(strict_types=1);

namespace MigrationModule\Application\Sync;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class ConflictResolutionService
{
    public function __construct(private readonly ?MigrationRepository $repository = null)
    {
    }

    /** @param array<string, mixed> $source @param array<string, mixed> $target @return array<string, mixed> */
    public function resolve(array $source, array $target, string $strategy = 'update_target'): array
    {
        return match ($strategy) {
            'skip', 'keep_target_unchanged' => $target,
            'create_with_new_id' => array_merge($source, ['id' => (string) ($source['id'] ?? '') . '-remap']),
            'relink' => array_merge($target, ['linked_source_id' => $source['id'] ?? null]),
            'manual_review' => ['status' => 'manual_review', 'source' => $source, 'target' => $target],
            default => array_merge($target, $source),
        };
    }

    /** @param array<string,mixed> $conflict */
    public function saveDecision(string $jobId, array $conflict, string $strategy): void
    {
        if ($this->repository === null) {
            return;
        }

        $this->repository->saveOperatorDecision($jobId, [
            'type' => (string) ($conflict['type'] ?? 'unknown'),
            'entity' => (string) ($conflict['entity'] ?? 'unknown'),
            'source_id' => (string) ($conflict['source_id'] ?? ''),
            'candidate_target_id' => isset($conflict['candidate_target_id']) ? (string) $conflict['candidate_target_id'] : null,
            'description' => (string) ($conflict['description'] ?? ''),
            'recommended_action' => (string) ($conflict['recommended_action'] ?? 'manual_review'),
            'strategy' => $strategy,
        ]);
    }
}
