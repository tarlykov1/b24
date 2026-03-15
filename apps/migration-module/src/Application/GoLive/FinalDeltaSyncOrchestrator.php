<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

final class FinalDeltaSyncOrchestrator
{
    private const PRIORITY = ['users', 'org_structure', 'permissions', 'groups', 'crm', 'tasks', 'files', 'comments', 'activities', 'timelines', 'residual_references'];

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function run(string $mode, array $input): array
    {
        $changes = (array) ($input['changes'] ?? []);
        $sourceQpsLimit = (int) ($input['sourceQpsLimit'] ?? 60);
        $workerCount = max(1, (int) ($input['workerCount'] ?? 8));
        $safeBatchSize = max(25, min(500, (int) floor(($sourceQpsLimit * 3) / $workerCount)));

        $ordered = $this->orderByPriority($changes);
        $total = array_sum(array_map(static fn (array $bucket): int => (int) ($bucket['count'] ?? 0), $ordered));
        $etaMin = (int) ceil($total / max(1, ($safeBatchSize * $workerCount)) * 4.0);

        return [
            'mode' => $mode,
            'safeBatchSize' => $safeBatchSize,
            'etaMin' => $etaMin,
            'throttle' => ['sourceQpsLimit' => $sourceQpsLimit, 'adaptive' => true],
            'buckets' => $ordered,
            'summary' => [
                'created' => $this->sumType($ordered, 'created'),
                'updated' => $this->sumType($ordered, 'updated'),
                'deleted' => $this->sumType($ordered, 'deleted'),
                'archived' => $this->sumType($ordered, 'archived'),
                'relinkNeeded' => $this->sumType($ordered, 'relink-needed'),
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $changes
     * @return array<int,array<string,mixed>>
     */
    private function orderByPriority(array $changes): array
    {
        usort($changes, function (array $a, array $b): int {
            return $this->priorityIndex((string) ($a['entityFamily'] ?? '')) <=> $this->priorityIndex((string) ($b['entityFamily'] ?? ''));
        });

        return $changes;
    }

    private function priorityIndex(string $entityFamily): int
    {
        $idx = array_search($entityFamily, self::PRIORITY, true);

        return $idx === false ? 999 : $idx;
    }

    /** @param array<int,array<string,mixed>> $changes */
    private function sumType(array $changes, string $type): int
    {
        $sum = 0;
        foreach ($changes as $bucket) {
            $sum += (int) (($bucket[$type] ?? 0));
        }

        return $sum;
    }
}
