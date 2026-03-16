<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

final class DeterministicBatchScheduler
{
    public function schedule(array $graph, int $batchSize): array
    {
        $batchSize = max(1, $batchSize);
        $batches = [];
        $chunked = array_chunk($graph['nodes'], $batchSize);
        foreach ($chunked as $index => $chunk) {
            $entityType = (string) ($chunk[0]['entity_type'] ?? 'mixed');
            $batches[] = [
                'batch_id' => sprintf('%s_b%05d', $graph['plan_id'], $index + 1),
                'phase' => 'write_entities',
                'stable_order' => $index + 1,
                'entity_type' => $entityType,
                'items' => $chunk,
            ];
        }

        return $batches;
    }
}
