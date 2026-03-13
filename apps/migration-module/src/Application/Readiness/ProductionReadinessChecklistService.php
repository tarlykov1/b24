<?php

declare(strict_types=1);

namespace MigrationModule\Application\Readiness;

final class ProductionReadinessChecklistService
{
    /** @param array<string,bool> $checks */
    public function evaluate(array $checks): array
    {
        $required = [
            'api_available',
            'permissions_ok',
            'config_valid',
            'dry_run_successful',
            'critical_conflicts_absent',
            'backup_available',
            'free_disk_space',
            'connection_stable',
        ];

        $results = [];
        $canStart = true;
        foreach ($required as $check) {
            $passed = (bool) ($checks[$check] ?? false);
            $results[$check] = $passed;
            $canStart = $canStart && $passed;
        }

        return [
            'can_start' => $canStart,
            'results' => $results,
            'failed' => array_keys(array_filter($results, static fn (bool $v): bool => $v === false)),
        ];
    }
}
