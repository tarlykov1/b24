<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

use MigrationModule\Prototype\Storage\MySqlStorage;
use PDO;

final class FinalDeltaCollector
{
    public function __construct(private readonly MySqlStorage $storage)
    {
    }

    /** @param array<string,mixed> $baseline @return array<string,mixed> */
    public function collect(string $jobId, array $baseline, int $chunkSize = 100): array
    {
        $pdo = $this->storage->pdo();
        $stmt = $pdo->prepare('SELECT DISTINCT entity_type FROM delta_entity_state WHERE job_id=:job');
        $stmt->execute(['job' => $jobId]);
        $types = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $all = [];
        foreach ($types as $type) {
            $all = array_merge($all, $this->storage->deltaStates($jobId, (string) $type));
        }

        $offset = (int) ($baseline['offset'] ?? 0);
        $slice = array_slice($all, $offset, $chunkSize);
        $total = count($all);
        $domains = [];
        foreach ($slice as $row) {
            $d = (string) ($row['entity_type'] ?? 'unknown');
            $domains[$d] ??= ['candidate_count' => 0, 'migrated_count' => 0, 'skipped_count' => 0, 'retried_count' => 0, 'failed_count' => 0, 'blocked_count' => 0];
            $domains[$d]['candidate_count']++;
            $isDeleted = ((int) ($row['deleted'] ?? 0)) === 1;
            if ($isDeleted) {
                $domains[$d]['blocked_count']++;
            } else {
                $domains[$d]['migrated_count']++;
            }
        }

        return [
            'baseline_reference' => $baseline['baseline_reference'] ?? 'latest_known_checkpoint',
            'processed_chunk' => count($slice),
            'next_offset' => $offset + count($slice),
            'complete' => ($offset + count($slice)) >= $total,
            'nothing_changed' => $total === 0,
            'domains' => $domains,
        ];
    }
}
