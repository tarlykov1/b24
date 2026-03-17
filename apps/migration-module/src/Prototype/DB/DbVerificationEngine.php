<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\DB;

use MigrationModule\Prototype\Adapter\Target\MySqlTargetReadAdapter;
use MigrationModule\Prototype\Storage\MySqlStorage;

final class DbVerificationEngine
{
    public function __construct(
        private readonly MySqlStorage $storage,
        private readonly ?MySqlTargetReadAdapter $targetReadAdapter = null,
    ) {
    }

    public function verify(string $jobId, string $mode): array
    {
        $snapshot = $this->storage->latestSchemaSnapshot($jobId) ?? [];
        $graph = $this->storage->entityGraph($jobId) ?? [];

        $sourceCounts = [];
        foreach ((array) ($snapshot['tables'] ?? []) as $table => $meta) {
            $sourceCounts[$table] = (int) ($meta['row_count'] ?? 0);
        }

        $targetCounts = [];
        if ($this->targetReadAdapter !== null && in_array($mode, ['target_db', 'mixed'], true)) {
            foreach (array_keys($sourceCounts) as $table) {
                $targetCounts[$table] = $this->targetReadAdapter->rowCount($table);
            }
        }

        $orphans = [];
        foreach ((array) ($snapshot['detected_relations'] ?? []) as $relation) {
            if ($this->targetReadAdapter === null || !in_array($mode, ['target_db', 'mixed'], true)) {
                break;
            }
            $table = (string) ($relation['from_table'] ?? '');
            $fk = (string) ($relation['column'] ?? '');
            $parent = (string) ($relation['to_entity_hint'] ?? '');
            if ($table === '' || $fk === '' || $parent === '') {
                continue;
            }
            $orphans[] = ['table' => $table, 'fk' => $fk, 'parent' => $parent, 'items' => $this->targetReadAdapter->findOrphans($table, $fk, $parent, 'id')];
        }

        $result = [
            'job_id' => $jobId,
            'verify_mode' => $mode,
            'verified_via_runtime' => in_array($mode, ['runtime', 'mixed'], true),
            'verified_via_source_db' => in_array($mode, ['source_db', 'mixed'], true),
            'verified_via_target_db' => in_array($mode, ['target_db', 'mixed'], true),
            'row_counts' => ['source' => $sourceCounts, 'target' => $targetCounts],
            'mapping_integrity' => ['graph_nodes' => count((array) ($graph['nodes'] ?? [])), 'graph_edges' => count((array) ($graph['edges'] ?? []))],
            'relation_existence' => (array) ($snapshot['detected_relations'] ?? []),
            'orphans' => $orphans,
            'attachment_metadata_checks' => ['status' => 'not_implemented_yet', 'safe_foundation' => true],
            'verify_depth' => 'db_truth_foundation',
        ];

        $this->storage->saveDbVerifyResult($jobId, $mode, $result);

        return $result;
    }
}
