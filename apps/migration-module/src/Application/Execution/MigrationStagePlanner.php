<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution;

final class MigrationStagePlanner
{
    /**
     * @var array<string,array<int,string>>
     */
    private const STAGES = [
        'stage_1_reference_bootstrap' => ['users', 'directories', 'pipelines', 'stages', 'custom_fields'],
        'stage_2_core_crm' => ['leads', 'contacts', 'companies', 'deals', 'smart_processes'],
        'stage_3_operational' => ['tasks', 'relations'],
        'stage_4_heavy_dependencies' => ['comments', 'activities', 'files'],
        'stage_5_reconciliation' => ['incremental_sync', 'repeat_verification'],
    ];

    /** @param array<string, array<int, array<string,mixed>>> $source */
    public function buildQueuePlan(array $source, int $chunkSize, int $batchSize): array
    {
        $plan = [];
        foreach (self::STAGES as $stageName => $types) {
            $queues = [];
            foreach ($types as $entityType) {
                $records = $source[$entityType] ?? [];
                if ($records === []) {
                    continue;
                }

                $chunks = [];
                foreach (array_chunk($records, max(1, $chunkSize)) as $chunkIndex => $chunk) {
                    $chunks[] = [
                        'chunk_cursor' => $chunkIndex,
                        'batches' => array_map(
                            static fn (array $batch, int $batchIndex): array => [
                                'batch_cursor' => $batchIndex,
                                'size' => count($batch),
                                'records' => $batch,
                            ],
                            array_chunk($chunk, max(1, $batchSize)),
                            array_keys(array_chunk($chunk, max(1, $batchSize))),
                        ),
                    ];
                }

                $queues[$entityType] = [
                    'entity_type' => $entityType,
                    'parallel_safe' => !in_array($entityType, ['comments', 'activities', 'files', 'relations'], true),
                    'chunks' => $chunks,
                    'total_records' => count($records),
                ];
            }

            if ($queues !== []) {
                $plan[] = ['stage' => $stageName, 'queues' => $queues];
            }
        }

        return $plan;
    }
}
