<?php

declare(strict_types=1);

namespace MigrationModule\Application\Operations;

use DateTimeImmutable;
use MigrationModule\ControlCenter\Controller\JobController;
use PDO;

final class RuntimeControlPlaneService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function createJob(string $mode): array
    {
        $mode = $this->normalizeMode($mode);
        $jobId = 'job_' . bin2hex(random_bytes(6));
        $status = $mode === 'execute' ? 'running' : 'created';

        $stmt = $this->pdo->prepare('INSERT INTO jobs(id, mode, status) VALUES(:id,:mode,:status)');
        $stmt->execute(['id' => $jobId, 'mode' => $mode, 'status' => $status]);

        $this->recordStep($jobId, $mode, $status === 'running' ? 'running' : 'completed', ['source' => 'web']);
        $this->writeLog($jobId, 'info', sprintf('Job created in %s mode.', $mode), ['action' => 'create_job', 'mode' => $mode]);

        return $this->jobDetails($jobId);
    }

    /** @return array<string,mixed> */
    public function lifecycleAction(string $jobId, string $action): array
    {
        $action = $this->normalizeMode($action);
        $job = $this->requireJob($jobId);

        if (in_array($action, ['pause', 'resume'], true)) {
            $controller = new JobController($this->pdo);
            $state = $action === 'pause' ? $controller->pause($jobId) : $controller->resume($jobId);
            $newStatus = (string) ($state['status'] ?? $job['status']);
            $this->recordStep($jobId, $action, 'completed', ['trigger' => 'web']);

            return ['ok' => true, 'jobId' => $jobId, 'action' => $action, 'status' => $newStatus, 'job' => $this->jobDetails($jobId)];
        }

        $status = match ($action) {
            'validate' => 'validated',
            'dry-run' => 'dry-run-complete',
            'execute' => 'running',
            'verify' => 'verified',
            default => (string) $job['status'],
        };

        $stmt = $this->pdo->prepare('UPDATE jobs SET mode=:mode, status=:status, updated_at=CURRENT_TIMESTAMP WHERE id=:id');
        $stmt->execute(['id' => $jobId, 'mode' => $action, 'status' => $status]);

        $stepStatus = in_array($action, ['execute'], true) ? 'running' : 'completed';
        $this->recordStep($jobId, $action, $stepStatus, ['trigger' => 'web', 'previous_status' => $job['status']]);
        $this->writeLog($jobId, 'info', sprintf('Lifecycle action %s completed with status %s.', $action, $status), ['action' => $action, 'status' => $status]);

        if ($action === 'verify') {
            $this->createVerificationReport($jobId);
        }

        return ['ok' => true, 'jobId' => $jobId, 'action' => $action, 'status' => $status, 'job' => $this->jobDetails($jobId)];
    }

    /** @return array<string,mixed> */
    public function listJobs(int $limit = 25, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $total = (int) ($this->pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn() ?: 0);
        $stmt = $this->pdo->prepare('SELECT id, mode, status, created_at, updated_at FROM jobs ORDER BY updated_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $jobId = (string) $row['id'];
            $queue = $this->queueStats($jobId);
            $items[] = [
                'jobId' => $jobId,
                'mode' => (string) $row['mode'],
                'status' => (string) $row['status'],
                'createdAt' => (string) $row['created_at'],
                'updatedAt' => (string) $row['updated_at'],
                'progress' => $queue['total'] > 0 ? (int) floor(($queue['done'] / $queue['total']) * 100) : 0,
                'queue' => $queue,
            ];
        }

        return ['items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** @return array<string,mixed> */
    public function jobDetails(string $jobId): array
    {
        $job = $this->requireJob($jobId);
        $queue = $this->queueStats($jobId);
        $stepsStmt = $this->pdo->prepare('SELECT step_name, step_status, payload_json, updated_at FROM job_steps WHERE job_id=:job_id ORDER BY updated_at DESC');
        $stepsStmt->execute(['job_id' => $jobId]);
        $steps = [];
        while ($row = $stepsStmt->fetch(PDO::FETCH_ASSOC)) {
            $steps[] = [
                'name' => (string) $row['step_name'],
                'status' => (string) $row['step_status'],
                'updatedAt' => (string) $row['updated_at'],
                'payload' => $this->decodeJson($row['payload_json'] ?? null),
            ];
        }

        $warnings = $this->countLogsByLevel($jobId, 'warning');
        $errors = $this->countLogsByLevel($jobId, 'error') + $this->countLogsByLevel($jobId, 'critical');
        $currentStep = $steps[0]['name'] ?? 'queued';

        return [
            'jobId' => $jobId,
            'mode' => (string) $job['mode'],
            'status' => (string) $job['status'],
            'createdAt' => (string) $job['created_at'],
            'updatedAt' => (string) $job['updated_at'],
            'progress' => $queue['total'] > 0 ? (int) floor(($queue['done'] / $queue['total']) * 100) : ((string) $job['status'] === 'verified' ? 100 : 0),
            'currentStep' => $currentStep,
            'warnings' => $warnings,
            'errors' => $errors,
            'queue' => $queue,
            'steps' => $steps,
            'timeline' => $this->timeline($jobId),
            'reports' => $this->reports($jobId),
        ];
    }

    /** @return array<string,mixed> */
    public function logs(string $jobId, int $limit = 100, int $offset = 0): array
    {
        $this->requireJob($jobId);
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $totalStmt = $this->pdo->prepare('SELECT COUNT(*) FROM logs WHERE job_id=:job_id');
        $totalStmt->execute(['job_id' => $jobId]);
        $total = (int) ($totalStmt->fetchColumn() ?: 0);

        $stmt = $this->pdo->prepare('SELECT id, level, message, context_json, created_at FROM logs WHERE job_id=:job_id ORDER BY id DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':job_id', $jobId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'id' => (int) $row['id'],
                'timestamp' => (string) $row['created_at'],
                'severity' => (string) $row['level'],
                'message' => (string) $row['message'],
                'context' => $this->decodeJson($row['context_json'] ?? null),
            ];
        }

        return ['items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** @return array<int,array<string,mixed>> */
    public function reports(string $jobId): array
    {
        $this->requireJob($jobId);
        $stmt = $this->pdo->prepare('SELECT id, status, report_json, created_at FROM cutover_reports WHERE job_id=:job_id ORDER BY id DESC');
        $stmt->execute(['job_id' => $jobId]);

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payload = $this->decodeJson($row['report_json'] ?? null);
            $items[] = [
                'reportId' => (int) $row['id'],
                'status' => (string) $row['status'],
                'createdAt' => (string) $row['created_at'],
                'summary' => is_array($payload) ? ($payload['summary'] ?? null) : null,
                'payload' => $payload,
            ];
        }

        return $items;
    }

    /** @return array<string,mixed> */
    private function requireJob(string $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, mode, status, created_at, updated_at FROM jobs WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($job)) {
            throw new \RuntimeException('job_not_found');
        }

        return $job;
    }

    /** @return array<string,int> */
    private function queueStats(string $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) AS total FROM queue WHERE job_id=:job_id GROUP BY status');
        $stmt->execute(['job_id' => $jobId]);

        $stats = ['total' => 0, 'done' => 0, 'pending' => 0, 'retry' => 0, 'failed' => 0, 'skipped' => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = (string) $row['status'];
            $count = (int) $row['total'];
            $stats['total'] += $count;
            if (array_key_exists($status, $stats)) {
                $stats[$status] = $count;
            }
        }

        return $stats;
    }

    /** @return array<int,array<string,mixed>> */
    private function timeline(string $jobId): array
    {
        $stmt = $this->pdo->prepare('SELECT step_name, step_status, updated_at FROM job_steps WHERE job_id=:job_id ORDER BY updated_at ASC');
        $stmt->execute(['job_id' => $jobId]);

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = ['step' => (string) $row['step_name'], 'status' => (string) $row['step_status'], 'timestamp' => (string) $row['updated_at']];
        }

        return $items;
    }

    /** @param array<string,mixed> $payload */
    private function recordStep(string $jobId, string $step, string $status, array $payload = []): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO job_steps(job_id, step_name, step_status, payload_json) VALUES(:job_id,:step_name,:step_status,:payload_json) ON DUPLICATE KEY UPDATE step_status=VALUES(step_status), payload_json=VALUES(payload_json), updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([
            'job_id' => $jobId,
            'step_name' => $step,
            'step_status' => $status,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @param array<string,mixed> $context */
    private function writeLog(string $jobId, string $level, string $message, array $context = []): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO logs(job_id, level, message, context_json) VALUES(:job_id,:level,:message,:context_json)');
        $stmt->execute([
            'job_id' => $jobId,
            'level' => $level,
            'message' => $message,
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function countLogsByLevel(string $jobId, string $level): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM logs WHERE job_id=:job_id AND level=:level');
        $stmt->execute(['job_id' => $jobId, 'level' => $level]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function normalizeMode(string $mode): string
    {
        $mode = mb_strtolower(trim($mode));
        return match ($mode) {
            'dryrun', 'dry_run' => 'dry-run',
            'run', 'start' => 'execute',
            default => $mode,
        };
    }

    private function createVerificationReport(string $jobId): void
    {
        $report = [
            'summary' => 'Verification completed from web control plane',
            'jobId' => $jobId,
            'verifiedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'queue' => $this->queueStats($jobId),
            'warnings' => $this->countLogsByLevel($jobId, 'warning'),
            'errors' => $this->countLogsByLevel($jobId, 'error') + $this->countLogsByLevel($jobId, 'critical'),
        ];

        $stmt = $this->pdo->prepare('INSERT INTO cutover_reports(job_id, status, report_json) VALUES(:job_id,:status,:report_json)');
        $stmt->execute([
            'job_id' => $jobId,
            'status' => 'generated',
            'report_json' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function decodeJson(mixed $json): ?array
    {
        if (!is_string($json) || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }
}
