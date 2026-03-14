<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

final class DeltaSyncEngine
{
    public function __construct(
        private readonly SyncPolicyEngine $policyEngine,
        private readonly ConflictDetectionEngine $conflictEngine,
    ) {
    }

    /** @param array<int,array<string,mixed>> $records */
    public function plan(string $cutoffTime, array $records): array
    {
        $planned = array_values(array_filter($records, static function (array $row) use ($cutoffTime): bool {
            $marker = (string) ($row['updated_at'] ?? $row['modified_at'] ?? $row['created_at'] ?? '');

            return $marker !== '' && strtotime($marker) > strtotime($cutoffTime);
        }));

        return ['cutoff' => $cutoffTime, 'delta_size' => count($planned), 'items' => $planned];
    }

    /** @param array<int,array<string,mixed>> $delta @param array<string,string> $policies */
    public function execute(array $delta, array $policies): array
    {
        $applied = [];
        $conflicts = [];

        foreach ($delta as $row) {
            $entity = (string) ($row['entity_type'] ?? 'generic');
            $policy = $policies[$entity] ?? 'create_or_update';
            $decision = $this->policyEngine->decide($entity, $policy, $row);
            $conflict = $this->conflictEngine->detect($row);

            if ($conflict !== null || $decision['action'] === 'conflict') {
                $conflicts[] = ['entity_id' => (string) $row['id'], 'entity_type' => $entity, 'decision' => $decision, 'conflict' => $conflict];
                continue;
            }

            $applied[] = ['entity_id' => (string) $row['id'], 'entity_type' => $entity, 'action' => $decision['action']];
        }

        return ['applied' => $applied, 'conflicts' => $conflicts];
    }
}
