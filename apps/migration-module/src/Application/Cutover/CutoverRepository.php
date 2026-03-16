<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

use PDO;

final class CutoverRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string,mixed> $policy */
    public function createRun(string $cutoverId, string $jobId, string $status, array $policy): void
    {
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO cutover_runs(cutover_id, job_id, status, policy_json, created_at, updated_at) VALUES(:id,:job,:status,:policy,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $stmt->execute(['id' => $cutoverId, 'job' => $jobId, 'status' => $status, 'policy' => json_encode($policy, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return array<string,mixed>|null */
    public function run(string $cutoverId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_runs WHERE cutover_id=:id LIMIT 1');
        $stmt->execute(['id' => $cutoverId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function updateStatus(string $cutoverId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE cutover_runs SET status=:status, updated_at=CURRENT_TIMESTAMP WHERE cutover_id=:id');
        $stmt->execute(['id' => $cutoverId, 'status' => $status]);
    }

    /** @param array<string,mixed> $result @param array<string,mixed> $error */
    public function saveStage(string $cutoverId, string $stage, string $status, array $result = [], array $error = [], string $summary = '', string $executionKey = '', int $retryCount = 0): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_stages(cutover_id, stage_name, status, result_json, error_json, summary, execution_key, retry_count, started_at, finished_at) VALUES(:c,:s,:st,:r,:e,:summary,:k,:rc,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $stmt->execute([
            'c' => $cutoverId,
            's' => $stage,
            'st' => $status,
            'r' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'e' => json_encode($error, JSON_UNESCAPED_UNICODE),
            'summary' => $summary,
            'k' => $executionKey,
            'rc' => $retryCount,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function stages(string $cutoverId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_stages WHERE cutover_id=:id ORDER BY id');
        $stmt->execute(['id' => $cutoverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $payload */
    public function saveEvent(string $cutoverId, string $eventType, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_events(cutover_id, event_type, payload_json) VALUES(:id,:t,:p)');
        $stmt->execute(['id' => $cutoverId, 't' => $eventType, 'p' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return list<array<string,mixed>> */
    public function events(string $cutoverId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_events WHERE cutover_id=:id ORDER BY id');
        $stmt->execute(['id' => $cutoverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $check */
    public function saveCheck(string $cutoverId, string $group, string $name, string $status, array $check): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_checks(cutover_id, check_group, check_name, status, payload_json) VALUES(:id,:g,:n,:s,:p)');
        $stmt->execute(['id' => $cutoverId, 'g' => $group, 'n' => $name, 's' => $status, 'p' => json_encode($check, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return list<array<string,mixed>> */
    public function checks(string $cutoverId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_checks WHERE cutover_id=:id ORDER BY id');
        $stmt->execute(['id' => $cutoverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $approval */
    public function saveApproval(string $cutoverId, string $scope, string $approver, string $status, ?string $comment = null, array $approval = []): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_approvals(cutover_id, approval_scope, approver_identity, status, comment, payload_json) VALUES(:id,:s,:a,:st,:c,:p)');
        $stmt->execute(['id' => $cutoverId, 's' => $scope, 'a' => $approver, 'st' => $status, 'c' => $comment, 'p' => json_encode($approval, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return list<array<string,mixed>> */
    public function approvals(string $cutoverId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_approvals WHERE cutover_id=:id ORDER BY id');
        $stmt->execute(['id' => $cutoverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $report */
    public function saveReport(string $cutoverId, array $report): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_artifacts(cutover_id, artifact_type, payload_json) VALUES(:id,:t,:p)');
        $stmt->execute(['id' => $cutoverId, 't' => 'report', 'p' => json_encode($report, JSON_UNESCAPED_UNICODE)]);
    }
}
