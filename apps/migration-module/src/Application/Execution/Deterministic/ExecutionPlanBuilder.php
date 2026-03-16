<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

final class ExecutionPlanBuilder
{
    /** @param array<string,mixed> $config @param array<string,mixed> $sourceIdentity @param array<string,mixed> $targetIdentity */
    public function build(array $config, array $sourceIdentity, array $targetIdentity, array $entitiesByType, string $mode): array
    {
        $phases = ['discovery', 'extract', 'transform', 'precreate_dependencies', 'write_entities', 'attach_relations', 'transfer_files', 'verify', 'reconcile'];
        $scope = array_keys($entitiesByType);
        sort($scope);

        $planInput = [
            'config' => $config,
            'source' => $sourceIdentity,
            'target' => $targetIdentity,
            'scope' => $scope,
            'filters' => $config['filters'] ?? [],
            'mapping_version' => $config['mapping_version'] ?? 'v1',
            'cutoff_rules' => $config['cutoff_policy'] ?? [],
            'mode' => $mode,
        ];

        $planHash = hash('sha256', (string) json_encode($planInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $planId = 'plan_' . substr($planHash, 0, 16);

        return [
            'plan_id' => $planId,
            'plan_hash' => $planHash,
            'phases' => $phases,
            'entities' => $entitiesByType,
            'mode' => $mode,
            'scope' => $scope,
        ];
    }
}
