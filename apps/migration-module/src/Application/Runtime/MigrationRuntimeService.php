<?php

declare(strict_types=1);

namespace MigrationModule\Application\Runtime;

use MigrationModule\Application\Consistency\EntityStateMachine;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class MigrationRuntimeService
{
    public function __construct(
        private readonly MigrationRepository $repository,
        private readonly ?EntityStateMachine $stateMachine = null,
    ) {
    }

    /** @param array<int, array<string, mixed>> $entities */
    public function migrateWithCheckpoint(string $jobId, string $entityType, array $entities, int $batchSize = 2, ?int $crashAfter = null): int
    {
        $checkpoint = $this->repository->latestCheckpoint($jobId);
        $offset = (int) ($checkpoint['offset'] ?? 0);
        $processed = 0;

        for ($i = $offset; $i < count($entities); $i += $batchSize) {
            $batch = array_slice($entities, $i, $batchSize);
            foreach ($batch as $entity) {
                $entityId = (string) $entity['id'];
                $stateMachine = $this->stateMachine ?? new EntityStateMachine();
                $currentState = (string) (($this->repository->entityState($jobId, $entityType, $entityId)['state'] ?? 'discovered'));
                if ($stateMachine->canTransition($currentState, 'queued')) {
                    $this->repository->saveEntityState($jobId, $entityType, $entityId, ['state' => 'queued']);
                }
                $this->repository->saveEntityState($jobId, $entityType, $entityId, ['state' => 'extracted']);
                $this->repository->saveEntityState($jobId, $entityType, $entityId, ['state' => 'transformed']);
                $this->repository->saveMapping($jobId, $entityType, $entityId, $entityId);
                $this->repository->saveEntityState($jobId, $entityType, $entityId, ['state' => 'created']);
                $processed++;
                if ($crashAfter !== null && $processed >= $crashAfter) {
                    $this->repository->saveCheckpoint($jobId, ['entity' => $entityType, 'offset' => $i + 1]);
                    throw new \RuntimeException('Simulated crash');
                }
            }
            $this->repository->saveCheckpoint($jobId, ['entity' => $entityType, 'offset' => min($i + $batchSize, count($entities))]);
        }

        return $processed;
    }
}
