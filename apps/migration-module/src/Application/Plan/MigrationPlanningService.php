<?php

declare(strict_types=1);

namespace MigrationModule\Application\Plan;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class MigrationPlanningService
{
    public function __construct(private readonly MigrationRepository $repository)
    {
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $source
     * @param array<string, array<int, array<string, mixed>>> $target
     * @return array{items:array<int,array<string,mixed>>,summary:array<string,int>}
     */
    public function buildPlan(string $jobId, array $source, array $target, bool $incremental = false): array
    {
        $items = [];
        $summary = ['create' => 0, 'update' => 0, 'skip' => 0, 'conflict' => 0, 'manual_review' => 0];

        foreach ($source as $entityType => $records) {
            $targetIndex = $this->indexById($target[$entityType] ?? []);

            foreach ($records as $record) {
                $sourceId = (string) ($record['id'] ?? '');
                $mappedId = $this->repository->findMappedId($jobId, $entityType, $sourceId);
                $targetRecord = $mappedId !== null ? ($targetIndex[$mappedId] ?? null) : ($targetIndex[$sourceId] ?? null);
                $action = 'create';
                $reason = 'not_found_in_target';
                $conflictType = null;

                if (($record['requires_manual_review'] ?? false) === true) {
                    $action = 'manual_review';
                    $reason = 'explicit_manual_review_flag';
                } elseif ($targetRecord !== null) {
                    if ($this->hasConflict($record, $targetRecord)) {
                        $action = 'conflict';
                        $reason = 'key_fields_mismatch';
                        $conflictType = 'existing_key_mismatch';
                    } elseif ($incremental && !$this->isChanged($record, $targetRecord)) {
                        $action = 'skip';
                        $reason = 'already_synced';
                    } else {
                        $action = 'update';
                        $reason = 'target_exists_but_data_changed';
                    }
                }

                $summary[$action]++;
                $items[] = [
                    'entity_type' => $entityType,
                    'source_id' => $sourceId,
                    'target_exists' => $targetRecord !== null,
                    'target_id' => $targetRecord['id'] ?? $mappedId,
                    'action' => $action,
                    'reason' => $reason,
                    'dependencies' => $this->extractDependencies($record),
                    'links' => $this->extractLinks($record),
                    'conflict_type' => $conflictType,
                ];
            }
        }

        return ['items' => $items, 'summary' => $summary];
    }

    /** @param array<int,array<string,mixed>> $records @return array<string,array<string,mixed>> */
    private function indexById(array $records): array
    {
        $index = [];
        foreach ($records as $record) {
            if (isset($record['id'])) {
                $index[(string) $record['id']] = $record;
            }
        }

        return $index;
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target */
    private function hasConflict(array $source, array $target): bool
    {
        foreach (['email', 'title', 'phone', 'uf_crm_external_key'] as $key) {
            if (isset($source[$key], $target[$key]) && (string) $source[$key] !== (string) $target[$key]) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $target */
    private function isChanged(array $source, array $target): bool
    {
        return hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            !== hash('sha256', json_encode($target, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string,mixed> $record @return array<int,string> */
    private function extractDependencies(array $record): array
    {
        $keys = ['company_id', 'contact_id', 'assigned_by_id', 'responsible_id', 'task_id'];
        $dependencies = [];
        foreach ($keys as $key) {
            if (!empty($record[$key])) {
                $dependencies[] = sprintf('%s:%s', $key, (string) $record[$key]);
            }
        }

        return $dependencies;
    }

    /** @param array<string,mixed> $record @return array<int,string> */
    private function extractLinks(array $record): array
    {
        return array_values(array_map('strval', (array) ($record['references'] ?? [])));
    }
}
