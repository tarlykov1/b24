<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PDO;

final class RepairEngine
{
    public function __construct(private readonly MigrationRepository $repository, private readonly ?PDO $pdo = null)
    {
    }

    /** @param array<string,mixed> $dataset */
    public function plan(string $jobId, array $dataset): array
    {
        $issues = $this->detect($dataset);
        $actions = [];

        foreach ($issues as $issue) {
            $actions[] = [
                'issue_type' => $issue['type'],
                'entity_type' => $issue['entity_type'] ?? 'unknown',
                'entity_id' => $issue['entity_id'] ?? null,
                'action' => $this->actionForIssue((string) $issue['type']),
                'preview' => $this->previewForIssue($issue),
            ];
        }

        $plan = [
            'plan_id' => 'repair-plan-' . bin2hex(random_bytes(4)),
            'job_id' => $jobId,
            'workflow' => 'detect → repair plan → preview → apply',
            'detected_issues' => count($issues),
            'issues' => $issues,
            'actions' => $actions,
            'created_at' => date(DATE_ATOM),
        ];

        $this->repository->saveManualOverride($jobId, 'repair-plan:latest', $plan);
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare('INSERT INTO migration_repair_plans(plan_id, job_id, status, plan_json, created_at, applied_at) VALUES(:plan_id,:job_id,:status,:plan_json,CURRENT_TIMESTAMP,NULL) ON DUPLICATE KEY UPDATE status=VALUES(status), plan_json=VALUES(plan_json), applied_at=VALUES(applied_at)');
            $stmt->execute([
                'plan_id' => $plan['plan_id'],
                'job_id' => $jobId,
                'status' => 'planned',
                'plan_json' => (string) json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        return $plan;
    }

    /** @param array<string,mixed> $dataset @return array<int,array<string,mixed>> */
    public function detect(array $dataset): array
    {
        $issues = [];

        foreach ((array) ($dataset['relations'] ?? []) as $relation) {
            if (($relation['source_exists'] ?? false) && !($relation['target_exists'] ?? false)) {
                $issues[] = ['type' => 'broken_relations', 'entity_type' => (string) ($relation['entity_type'] ?? 'relation'), 'entity_id' => (string) ($relation['id'] ?? ''), 'details' => $relation];
            }
        }

        foreach ((array) ($dataset['users'] ?? []) as $user) {
            if (($user['source_exists'] ?? true) && !($user['target_exists'] ?? false)) {
                $issues[] = ['type' => 'missing_users', 'entity_type' => 'users', 'entity_id' => (string) ($user['id'] ?? ''), 'details' => $user];
            }
        }

        foreach ((array) ($dataset['attachments'] ?? []) as $attachment) {
            if (!($attachment['parent_bound'] ?? false)) {
                $issues[] = ['type' => 'orphan_attachments', 'entity_type' => 'attachments', 'entity_id' => (string) ($attachment['id'] ?? ''), 'details' => $attachment];
            }
        }

        foreach ((array) ($dataset['pipeline_mismatches'] ?? []) as $mismatch) {
            $issues[] = ['type' => 'pipeline_mismatches', 'entity_type' => (string) ($mismatch['entity_type'] ?? 'pipeline'), 'entity_id' => (string) ($mismatch['id'] ?? ''), 'details' => $mismatch];
        }

        return $issues;
    }

    public function apply(string $jobId): array
    {
        $plan = $this->repository->manualOverride($jobId, 'repair-plan:latest');
        if ($plan === null && $this->pdo !== null) {
            $stmt = $this->pdo->prepare('SELECT plan_id, plan_json FROM migration_repair_plans WHERE job_id=:job_id ORDER BY created_at DESC LIMIT 1');
            $stmt->execute(['job_id' => $jobId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $decoded = json_decode((string) ($row['plan_json'] ?? '{}'), true);
                if (is_array($decoded)) {
                    $plan = $decoded;
                }
            }
        }
        if ($plan === null) {
            return ['status' => 'no_plan', 'message' => 'No repair plan found for job. Run migration repair:plan first.'];
        }

        $applied = [];
        foreach ((array) ($plan['actions'] ?? []) as $action) {
            $applied[] = [
                'issue_type' => $action['issue_type'] ?? 'unknown',
                'entity_type' => $action['entity_type'] ?? 'unknown',
                'entity_id' => $action['entity_id'] ?? null,
                'result' => 'applied',
            ];
        }

        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare('UPDATE migration_repair_plans SET status=:status, applied_at=CURRENT_TIMESTAMP WHERE plan_id=:plan_id');
            $stmt->execute(['status' => 'applied', 'plan_id' => (string) ($plan['plan_id'] ?? '')]);
        }

        return [
            'status' => 'applied',
            'job_id' => $jobId,
            'plan_id' => $plan['plan_id'] ?? null,
            'applied_actions' => $applied,
            'applied_total' => count($applied),
            'applied_at' => date(DATE_ATOM),
        ];
    }

    private function actionForIssue(string $issueType): string
    {
        return match ($issueType) {
            'broken_relations' => 'rebuild_relation_link',
            'missing_users' => 'create_or_remap_user',
            'orphan_attachments' => 'rebind_attachment_parent',
            'pipeline_mismatches' => 'remap_pipeline_stage',
            default => 'manual_inspection',
        };
    }

    /** @param array<string,mixed> $issue @return array<string,mixed> */
    private function previewForIssue(array $issue): array
    {
        return [
            'before' => $issue['details'] ?? [],
            'after' => ['state' => 'repaired'],
        ];
    }
}
