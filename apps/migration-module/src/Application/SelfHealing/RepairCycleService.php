<?php

declare(strict_types=1);

namespace MigrationModule\Application\SelfHealing;

use MigrationModule\Domain\SelfHealing\HealingPolicy;

final class RepairCycleService
{
    public function __construct(
        private readonly SelfHealingEngine $healing,
        private readonly ErrorTaxonomy $taxonomy,
    ) {
    }

    /** @param array<string,mixed> $reconciliation @param array<string,array<string,mixed>> $entitiesByKey @return array<string,mixed> */
    public function run(string $jobId, array $reconciliation, array $entitiesByKey, HealingPolicy $policy = HealingPolicy::STANDARD): array
    {
        $repairJobs = [];
        foreach (($reconciliation['unresolved_links'] ?? []) as $link) {
            $key = sprintf('%s:%s', (string) $link['entity'], (string) $link['id']);
            $entity = $entitiesByKey[$key] ?? ['id' => (string) $link['id'], 'type' => (string) $link['entity']];
            $error = [
                'message' => 'reconciliation mismatch: missing relation',
                'category' => 'reconciliation_mismatch',
                'relation' => $link['relation'] ?? 'unknown',
            ];
            $repairJobs[] = $this->healing->healEntity($jobId, $entity, [$error], $policy);
        }

        return [
            'repair_jobs' => $repairJobs,
            'residual_issues' => $this->buildResidualIssues($reconciliation),
        ];
    }

    /** @param array<string,mixed> $reconciliation @return array<int,array<string,mixed>> */
    private function buildResidualIssues(array $reconciliation): array
    {
        $residual = [];
        foreach (($reconciliation['groups'] ?? []) as $group => $stats) {
            if (($stats['mismatched'] ?? 0) > 0 || ($stats['missing_in_target'] ?? 0) > 0) {
                $residual[] = [
                    'group' => $group,
                    'issue' => 'requires_manual_review_after_auto_heal',
                    'stats' => $stats,
                ];
            }
        }

        return $residual;
    }
}
