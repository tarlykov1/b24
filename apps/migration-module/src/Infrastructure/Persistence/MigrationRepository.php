<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence;

use MigrationModule\Domain\Job\JobLifecycle;

final class MigrationRepository
{
    /** @var array{jobs:array<int,array<string,mixed>>,queue:array<int,array<string,mixed>>,mapping:array<string,string>,checkpoints:array<string,array<string,mixed>>,logs:array<int,array<string,mixed>>,diffs:array<int,array<string,mixed>>,metrics:array<string,array<string,int|float>>} */
    private array $state;

    public function __construct(private readonly string $stateFile = '/tmp/b24_migration_state.json')
    {
        $this->state = $this->load();
    }

    /** @param array<string,mixed> $settings */
    public function beginJob(string $mode, array $settings): string
    {
        $id = (string) (\count($this->state['jobs']) + 1);
        $this->state['jobs'][(int) $id] = [
            'id' => $id,
            'mode' => $mode,
            'status' => JobLifecycle::QUEUED,
            'settings' => $settings,
            'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
        $this->flush();

        return $id;
    }

    /** @return array<string,mixed>|null */
    public function getJob(string $jobId): ?array
    {
        return $this->state['jobs'][(int) $jobId] ?? null;
    }

    public function updateJobStatus(string $jobId, string $status): void
    {
        if (!isset($this->state['jobs'][(int) $jobId])) {
            return;
        }
        $this->state['jobs'][(int) $jobId]['status'] = $status;
        $this->state['jobs'][(int) $jobId]['updated_at'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->flush();
    }

    /** @return array<int,array<string,mixed>> */
    public function allJobs(): array
    {
        return \array_values($this->state['jobs']);
    }

    /** @param array<string,mixed> $item */
    public function enqueue(string $jobId, array $item): bool
    {
        foreach ($this->state['queue'] as $row) {
            if ($row['job_id'] === $jobId && $row['dedupe_key'] === $item['dedupe_key']) {
                return false;
            }
        }

        $this->state['queue'][] = $item + ['job_id' => $jobId, 'status' => 'queued', 'attempt_count' => 0];
        $this->flush();

        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public function reserveQueue(string $jobId, int $limit): array
    {
        $reserved = [];
        foreach ($this->state['queue'] as $idx => $row) {
            if ($row['job_id'] !== $jobId || $row['status'] !== 'queued') {
                continue;
            }
            $this->state['queue'][$idx]['status'] = 'running';
            $reserved[] = $this->state['queue'][$idx];
            if (\count($reserved) >= $limit) {
                break;
            }
        }
        $this->flush();

        return $reserved;
    }

    public function completeQueueItem(string $jobId, string $dedupeKey): void
    {
        foreach ($this->state['queue'] as $idx => $row) {
            if ($row['job_id'] === $jobId && $row['dedupe_key'] === $dedupeKey) {
                $this->state['queue'][$idx]['status'] = 'done';
            }
        }
        $this->flush();
    }

    public function putMapping(string $jobId, string $entityType, string $sourceId, string $targetId): void
    {
        $this->state['mapping'][$this->mappingKey($jobId, $entityType, $sourceId)] = $targetId;
        $this->flush();
    }

    public function getMapping(string $jobId, string $entityType, string $sourceId): ?string
    {
        return $this->state['mapping'][$this->mappingKey($jobId, $entityType, $sourceId)] ?? null;
    }

    public function saveCheckpoint(string $jobId, string $scope, string $value, array $meta = []): void
    {
        $this->state['checkpoints'][$jobId . ':' . $scope] = ['value' => $value, 'meta' => $meta];
        $this->flush();
    }

    /** @return array{value:string,meta:array<string,mixed>}|null */
    public function getCheckpoint(string $jobId, string $scope): ?array
    {
        return $this->state['checkpoints'][$jobId . ':' . $scope] ?? null;
    }

    /** @param array<string,mixed> $log */
    public function appendLog(array $log): void
    {
        $this->state['logs'][] = $log;
        $this->flush();
    }

    /** @return array<int,array<string,mixed>> */
    public function logsByJob(string $jobId): array
    {
        return \array_values(\array_filter($this->state['logs'], static fn (array $l): bool => ($l['job_id'] ?? null) === $jobId));
    }

    /** @param array<string,mixed> $diff */
    public function saveDiff(string $jobId, array $diff): void
    {
        $this->state['diffs'][] = $diff + ['job_id' => $jobId];
        $this->flush();
    }

    /** @return array<int,array<string,mixed>> */
    public function diffsByJob(string $jobId): array
    {
        return \array_values(\array_filter($this->state['diffs'], static fn (array $d): bool => ($d['job_id'] ?? null) === $jobId));
    }

    public function incrementMetric(string $jobId, string $metric, int|float $value): void
    {
        if (!isset($this->state['metrics'][$jobId])) {
            $this->state['metrics'][$jobId] = [];
        }
        $this->state['metrics'][$jobId][$metric] = ($this->state['metrics'][$jobId][$metric] ?? 0) + $value;
        $this->flush();
    }

    /** @return array<string,int|float> */
    public function metrics(string $jobId): array
    {
        return $this->state['metrics'][$jobId] ?? [];
    }

    /** @return array{jobs:array<int,array<string,mixed>>,queue:array<int,array<string,mixed>>,mapping:array<string,string>,checkpoints:array<string,array<string,mixed>>,logs:array<int,array<string,mixed>>,diffs:array<int,array<string,mixed>>,metrics:array<string,array<string,int|float>>} */
    private function load(): array
    {
        if (!is_file($this->stateFile)) {
            return ['jobs' => [], 'queue' => [], 'mapping' => [], 'checkpoints' => [], 'logs' => [], 'diffs' => [], 'metrics' => []];
        }

        $decoded = json_decode((string) file_get_contents($this->stateFile), true);

        return is_array($decoded)
            ? $decoded
            : ['jobs' => [], 'queue' => [], 'mapping' => [], 'checkpoints' => [], 'logs' => [], 'diffs' => [], 'metrics' => []];
    }

    private function flush(): void
    {
        file_put_contents($this->stateFile, (string) json_encode($this->state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function mappingKey(string $jobId, string $entityType, string $sourceId): string
    {
        return $jobId . ':' . $entityType . ':' . $sourceId;
    }
}
