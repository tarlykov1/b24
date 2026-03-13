<?php

declare(strict_types=1);

namespace MigrationModule\Application\Validation;

final class StatisticsComparisonService
{
    /**
     * @param array<string, array<int, array<string, mixed>>> $source
     * @param array<string, array<int, array<string, mixed>>> $target
     * @return array{status:string, rows:array<int, array<string, mixed>>}
     */
    public function compare(array $source, array $target, int $warningThreshold = 1, int $errorThreshold = 5): array
    {
        $entities = ['users', 'tasks', 'comments', 'crm_contacts', 'crm_companies', 'crm_deals', 'files'];
        $rows = [];
        $status = 'pass';

        foreach ($entities as $entity) {
            $sourceCount = count($source[$entity] ?? []);
            $targetCount = count($target[$entity] ?? []);
            $diff = $targetCount - $sourceCount;
            $severity = 'ok';
            if (abs($diff) > $errorThreshold) {
                $severity = 'error';
                $status = 'fail';
            } elseif (abs($diff) > $warningThreshold) {
                $severity = 'warning';
                if ($status !== 'fail') {
                    $status = 'warning';
                }
            }

            $rows[] = [
                'entity' => $entity,
                'source_portal' => $sourceCount,
                'destination_portal' => $targetCount,
                'difference' => $diff,
                'severity' => $severity,
            ];
        }

        return ['status' => $status, 'rows' => $rows];
    }
}
