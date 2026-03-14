<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\Service;

use DateInterval;
use DateTimeImmutable;
use PDO;

final class MigrationMonitor
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function dashboard(string $jobId): array
    {
        $totals = (int) $this->scalar('SELECT COUNT(*) FROM queue WHERE job_id = :job_id', $jobId);
        $processed = (int) $this->scalar('SELECT COUNT(*) FROM queue WHERE job_id = :job_id AND status = "done"', $jobId);
        $failed = (int) $this->scalar('SELECT COUNT(*) FROM queue WHERE job_id = :job_id AND status = "failed"', $jobId);
        $retry = (int) $this->scalar('SELECT COUNT(*) FROM queue WHERE job_id = :job_id AND status = "retry"', $jobId);
        $remaining = max($totals - $processed, 0);

        $currentStage = (string) ($this->scalar('SELECT status FROM jobs WHERE id = :job_id LIMIT 1', $jobId) ?: 'unknown');
        $throughput = $this->throughputPerMinute($jobId);
        $eta = $this->estimateEta($remaining, $throughput);

        $progress = $totals > 0 ? round(($processed / $totals) * 100, 2) : 0.0;

        return [
            'job' => $jobId,
            'progress' => $progress,
            'processed' => $processed,
            'remaining' => $remaining,
            'errors' => $failed,
            'speed' => sprintf('%d entities/min', $throughput),
            'eta' => $eta,
            'metrics' => [
                'total_entities' => $totals,
                'processed_entities' => $processed,
                'failed_entities' => $failed,
                'retry_queue' => $retry,
                'current_stage' => $currentStage,
                'throughput_per_minute' => $throughput,
                'estimated_completion_time' => $eta,
            ],
            'refresh_interval_seconds' => 2,
            'timeline' => $this->timeline($currentStage),
        ];
    }

    private function scalar(string $sql, string $jobId): int|string|null
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);

        $value = $stmt->fetchColumn();

        return $value === false ? null : $value;
    }

    private function throughputPerMinute(string $jobId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM queue WHERE job_id = :job_id AND status = "done" AND updated_at >= datetime("now", "-1 minute")'
        );
        $stmt->execute(['job_id' => $jobId]);
        $value = (int) $stmt->fetchColumn();

        return max($value, 1);
    }

    private function estimateEta(int $remaining, int $throughputPerMinute): string
    {
        if ($remaining === 0) {
            return '00:00:00';
        }

        $minutes = (int) ceil($remaining / max($throughputPerMinute, 1));
        $interval = new DateInterval(sprintf('PT%dM', $minutes));
        $target = (new DateTimeImmutable('now'))->add($interval);
        $diff = (new DateTimeImmutable('now'))->diff($target);

        return sprintf('%02d:%02d:%02d', ($diff->h + ($diff->d * 24)), $diff->i, $diff->s);
    }

    /** @return array<int,array<string,string>> */
    private function timeline(string $currentStage): array
    {
        $stages = ['validation', 'planning', 'execution', 'self-healing', 'verification', 'complete'];
        $seenCurrent = false;
        $timeline = [];

        foreach ($stages as $stage) {
            if ($stage === $currentStage) {
                $status = 'running';
                $seenCurrent = true;
            } elseif (!$seenCurrent && $currentStage !== 'unknown') {
                $status = 'done';
            } else {
                $status = 'pending';
            }

            $timeline[] = ['stage' => $stage, 'status' => $status];
        }

        return $timeline;
    }
}
