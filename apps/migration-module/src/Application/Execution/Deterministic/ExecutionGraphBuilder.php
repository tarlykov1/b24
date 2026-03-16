<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

final class ExecutionGraphBuilder
{
    /** @param array<string,mixed> $plan */
    public function build(array $plan): array
    {
        $dependencyRank = [
            'users' => 10,
            'directories' => 20,
            'companies' => 30,
            'contacts' => 40,
            'deals' => 50,
            'tasks' => 60,
            'comments' => 70,
            'files' => 80,
        ];

        $nodes = [];
        foreach ($plan['entities'] as $entityType => $items) {
            foreach ($items as $item) {
                $sourceId = (string) ($item['id'] ?? '');
                $nodes[] = [
                    'entity_type' => $entityType,
                    'source_id' => $sourceId,
                    'dependency_rank' => $dependencyRank[$entityType] ?? 100,
                    'stable_tiebreaker' => sprintf('%s:%s', $entityType, $sourceId),
                ];
            }
        }

        usort($nodes, static function (array $a, array $b): int {
            return [$a['dependency_rank'], $a['entity_type'], (int) $a['source_id'], $a['stable_tiebreaker']]
                <=> [$b['dependency_rank'], $b['entity_type'], (int) $b['source_id'], $b['stable_tiebreaker']];
        });

        return ['nodes' => $nodes, 'phases' => $plan['phases'], 'plan_id' => $plan['plan_id']];
    }
}
