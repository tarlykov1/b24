<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Service;

use PDO;

final class ConflictResolver
{
    private const ALLOWED_ACTIONS = ['remap', 'skip', 'merge', 'create_new', 'assign_system_user', 'manual_edit'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function list(string $jobId, int $limit = 100, int $offset = 0): array
    {
        $limit = min(max($limit, 1), 100);

        $stmt = $this->pdo->prepare('SELECT id, entity_type, source_id, issue FROM integrity_issues WHERE job_id = :job_id ORDER BY id LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':job_id', $jobId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->normalizeConflict($row), $rows);
    }

    /** @param array<string,mixed> $conflict */
    public function resolve(string $jobId, array $conflict, string $action): array
    {
        $resolvedAction = in_array($action, self::ALLOWED_ACTIONS, true) ? $action : 'manual_edit';
        $this->log($jobId, 'conflict_resolution', sprintf('%s on %s:%s', $resolvedAction, (string) ($conflict['entity'] ?? 'unknown'), (string) ($conflict['entity_id'] ?? 'n/a')));

        return [
            'job_id' => $jobId,
            'status' => 'resolved',
            'action' => $resolvedAction,
            'conflict' => $conflict,
        ];
    }

    /** @param array<int,array<string,mixed>> $conflicts */
    public function resolveBatch(string $jobId, array $conflicts, string $action): array
    {
        $items = array_slice($conflicts, 0, 1000);

        $resolved = [];
        foreach ($items as $conflict) {
            $resolved[] = $this->resolve($jobId, $conflict, $action);
        }

        return [
            'job_id' => $jobId,
            'resolved_count' => count($resolved),
            'limited_to' => 1000,
            'results' => $resolved,
        ];
    }

    public function autoResolveWithPolicy(string $jobId, string $policy): array
    {
        $conflicts = $this->list($jobId, 100);
        $action = match ($policy) {
            'prefer_source' => 'remap',
            'safe_skip' => 'skip',
            default => 'assign_system_user',
        };

        return $this->resolveBatch($jobId, $conflicts, $action);
    }

    /** @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeConflict(array $row): array
    {
        $issue = strtolower((string) ($row['issue'] ?? 'unknown'));
        $type = str_contains($issue, 'user') ? 'user_missing' : (str_contains($issue, 'stage') ? 'stage_mapping_error' : 'deleted_reference');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'type' => $type,
            'entity' => (string) ($row['entity_type'] ?? 'unknown'),
            'entity_id' => (string) ($row['source_id'] ?? ''),
            'issue' => (string) ($row['issue'] ?? ''),
            'suggested_resolution' => $type === 'user_missing' ? 'assign_system_user' : 'manual_edit',
        ];
    }

    private function log(string $jobId, string $level, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO logs(job_id, level, message) VALUES(:job_id, :level, :message)');
        $stmt->execute(['job_id' => $jobId, 'level' => $level, 'message' => $message]);
    }
}
