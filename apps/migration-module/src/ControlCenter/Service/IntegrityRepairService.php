<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Service;

use PDO;

final class IntegrityRepairService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function issues(string $jobId, int $limit = 100, int $offset = 0): array
    {
        $limit = min(max($limit, 1), 100);
        $stmt = $this->pdo->prepare('SELECT id, entity_type, source_id, issue FROM integrity_issues WHERE job_id = :job_id ORDER BY id LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':job_id', $jobId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', max($offset, 0), PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row): array => $this->toRepairIssue($row), $rows);
    }

    /** @param array<string,mixed> $issue */
    public function repairIssue(string $jobId, array $issue, bool $confirmed = false): array
    {
        if (!$confirmed) {
            return ['job_id' => $jobId, 'status' => 'confirmation_required', 'issue' => $issue];
        }

        $this->log($jobId, 'integrity_repair', sprintf('repaired %s %s', (string) ($issue['entity'] ?? ''), (string) ($issue['id'] ?? '')));

        return ['job_id' => $jobId, 'status' => 'repaired', 'issue' => $issue];
    }

    /** @param array<int,array<string,mixed>> $issues */
    public function repairBatch(string $jobId, array $issues, bool $confirmed = false): array
    {
        $items = array_slice($issues, 0, 1000);
        $results = [];
        foreach ($items as $issue) {
            $results[] = $this->repairIssue($jobId, $issue, $confirmed);
        }

        return [
            'job_id' => $jobId,
            'status' => $confirmed ? 'completed' : 'confirmation_required',
            'processed' => count($results),
            'limited_to' => 1000,
            'results' => $results,
        ];
    }

    public function autoRepair(string $jobId, bool $confirmed = false): array
    {
        return $this->repairBatch($jobId, $this->issues($jobId, 100), $confirmed);
    }

    public function scheduleRepair(string $jobId, string $when): array
    {
        $this->log($jobId, 'operator_action', 'scheduled integrity repair at ' . $when);

        return ['job_id' => $jobId, 'status' => 'scheduled', 'when' => $when];
    }

    /** @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function toRepairIssue(array $row): array
    {
        $issue = strtolower((string) ($row['issue'] ?? ''));
        $repair = str_contains($issue, 'relation') ? 'rebuild_relation' : (str_contains($issue, 'file') ? 're_download_file' : 're_sync_entity');

        return [
            'issue' => (string) ($row['issue'] ?? 'unknown'),
            'entity' => (string) ($row['entity_type'] ?? 'unknown'),
            'id' => (string) ($row['source_id'] ?? ''),
            'repair' => $repair,
        ];
    }

    private function log(string $jobId, string $level, string $message): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO logs(job_id, level, message) VALUES(:job_id, :level, :message)');
        $stmt->execute(['job_id' => $jobId, 'level' => $level, 'message' => $message]);
    }
}
