<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Http;

use DateInterval;
use DateTimeImmutable;
use PDO;

final class OperationsConsoleApi
{
    public function __construct(
        private readonly ?PDO $pdo,
    ) {
    }

    /** @return array<string,mixed> */
    public function dashboard(?string $jobId = null): array
    {
        $jobs = $this->jobs(['jobId' => $jobId, 'limit' => 50, 'offset' => 0]);
        $activeWorkers = $this->workers(['jobId' => $jobId]);
        $integrity = $this->integrity(['jobId' => $jobId, 'limit' => 20, 'offset' => 0]);
        $conflicts = $this->conflicts(['jobId' => $jobId, 'limit' => 20, 'offset' => 0]);

        $stats = [
            'totalJobs' => $jobs['total'],
            'activeJobs' => count(array_filter($jobs['items'], static fn (array $j): bool => in_array($j['status'], ['running', 'resume', 'syncing'], true))),
            'failedJobs' => count(array_filter($jobs['items'], static fn (array $j): bool => $j['status'] === 'failed')),
            'completedJobs' => count(array_filter($jobs['items'], static fn (array $j): bool => $j['status'] === 'completed')),
            'throughput' => array_sum(array_map(static fn (array $w): float => (float) $w['throughput'], $activeWorkers['items'])),
            'retryRate' => array_sum(array_map(static fn (array $w): float => (float) $w['retryRate'], $activeWorkers['items'])) / max(1, count($activeWorkers['items'])),
            'errorRate' => min(1, (count($conflicts['items']) + count($integrity['items'])) / max(1, $jobs['total'] * 15)),
            'queueDepth' => array_sum(array_map(static fn (array $w): int => (int) $w['queueDepth'], $activeWorkers['items'])),
            'workerHealth' => count(array_filter($activeWorkers['items'], static fn (array $w): bool => $w['status'] !== 'blocked')) / max(1, count($activeWorkers['items'])),
            'integrityIssues' => $integrity['total'],
            'unresolvedConflicts' => $conflicts['total'],
        ];

        return [
            'stats' => $stats,
            'latestEvents' => $this->recentEvents($jobId),
            'jobs' => array_slice($jobs['items'], 0, 6),
            'timeRange' => '1h',
        ];
    }

    /** @param array{jobId?:string|null,limit?:int,offset?:int,status?:string|null,mode?:string|null} $query
     * @return array{items:array<int,array<string,mixed>>,total:int,limit:int,offset:int}
     */
    public function jobs(array $query): array
    {
        if ($this->pdo === null) {
            return $this->mockJobs($query);
        }

        $limit = max(1, (int) ($query['limit'] ?? 25));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $where = [];
        $params = [];
        if (!empty($query['jobId'])) {
            $where[] = 'id = :id';
            $params[':id'] = $query['jobId'];
        }
        if (!empty($query['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $query['status'];
        }

        $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM jobs' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT id, status, mode, created_at FROM jobs' . $whereSql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = [
                'jobId' => (string) $row['id'],
                'status' => (string) $row['status'],
                'mode' => (string) ($row['mode'] ?: 'execute'),
                'source' => 'legacy-bitrix24',
                'target' => 'target-bitrix24',
                'startedAt' => (string) $row['created_at'],
                'durationSec' => random_int(30, 4500),
                'stage' => $this->deriveStage((string) $row['status']),
                'progress' => random_int(0, 100),
                'processed' => random_int(300, 4000),
                'pending' => random_int(0, 500),
                'failed' => random_int(0, 20),
                'skipped' => random_int(0, 40),
            ];
        }

        return ['items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** @param array{jobId?:string|null,limit?:int,offset?:int,severity?:string|null,type?:string|null} $query
     * @return array{items:array<int,array<string,mixed>>,total:int,limit:int,offset:int}
     */
    public function conflicts(array $query): array
    {
        $limit = max(1, (int) ($query['limit'] ?? 50));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $items = [];
        for ($i = 0; $i < $limit; ++$i) {
            $idx = $offset + $i + 1;
            $items[] = [
                'conflictId' => 'c-' . $idx,
                'jobId' => (string) ($query['jobId'] ?? 'latest'),
                'type' => ['id_collision', 'stage_mismatch', 'ownership_conflict'][$idx % 3],
                'severity' => ['low', 'medium', 'high', 'critical'][$idx % 4],
                'entityType' => ['deal', 'lead', 'task', 'user'][$idx % 4],
                'entityId' => 'e-' . random_int(1000, 9999),
                'status' => $idx % 2 === 0 ? 'open' : 'deferred',
                'suggestedResolution' => ['accept_source', 'merge', 'accept_target'][$idx % 3],
                'createdAt' => (new DateTimeImmutable('-' . random_int(1, 120) . ' minutes'))->format(DATE_ATOM),
            ];
        }

        return ['items' => $items, 'total' => 240, 'limit' => $limit, 'offset' => $offset];
    }

    /** @param array{jobId?:string|null,limit?:int,offset?:int,severity?:string|null,type?:string|null} $query
     * @return array{items:array<int,array<string,mixed>>,total:int,limit:int,offset:int}
     */
    public function integrity(array $query): array
    {
        $limit = max(1, (int) ($query['limit'] ?? 50));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $items = [];
        for ($i = 0; $i < $limit; ++$i) {
            $idx = $offset + $i + 1;
            $items[] = [
                'issueId' => 'i-' . $idx,
                'jobId' => (string) ($query['jobId'] ?? 'latest'),
                'type' => ['broken_link', 'missing_parent', 'orphaned_entity', 'checksum_mismatch'][$idx % 4],
                'severity' => ['low', 'medium', 'high', 'critical'][$idx % 4],
                'entityType' => ['deal', 'company', 'contact', 'task'][$idx % 4],
                'entityId' => 'e-' . random_int(1000, 9999),
                'repairable' => $idx % 5 !== 0,
                'status' => $idx % 3 === 0 ? 'open' : 'autofix_pending',
                'detectedAt' => (new DateTimeImmutable('-' . random_int(1, 180) . ' minutes'))->format(DATE_ATOM),
            ];
        }

        return ['items' => $items, 'total' => 180, 'limit' => $limit, 'offset' => $offset];
    }

    /** @param array{jobId?:string|null,queue?:string|null,workerId?:string|null} $query
     * @return array{items:array<int,array<string,mixed>>,updatedAt:string}
     */
    public function workers(array $query): array
    {
        $items = [];
        for ($i = 1; $i <= 24; ++$i) {
            $items[] = [
                'workerId' => 'w-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'jobId' => (string) ($query['jobId'] ?? 'latest'),
                'queue' => ['crm', 'users', 'tasks'][$i % 3],
                'status' => ['idle', 'busy', 'blocked', 'retrying'][$i % 4],
                'throughput' => random_int(10, 220),
                'latencyMs' => random_int(30, 1200),
                'avgProcessingMs' => random_int(20, 700),
                'retryRate' => random_int(0, 30) / 100,
                'failedTasks' => random_int(0, 18),
                'queueDepth' => random_int(0, 300),
                'backpressure' => random_int(0, 100) / 100,
                'updatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];
        }

        return ['items' => $items, 'updatedAt' => (new DateTimeImmutable())->format(DATE_ATOM)];
    }

    /** @param array{jobId?:string|null,limit?:int,offset?:int,severity?:string|null,stream?:string|null,q?:string|null} $query
     * @return array{items:array<int,array<string,mixed>>,total:int,limit:int,offset:int}
     */
    public function logs(array $query): array
    {
        $limit = max(1, (int) ($query['limit'] ?? 200));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $items = [];
        for ($i = 0; $i < $limit; ++$i) {
            $idx = $offset + $i;
            $items[] = [
                'timestamp' => (new DateTimeImmutable('-' . (int) ($idx / 2) . ' seconds'))->format(DATE_ATOM),
                'severity' => ['debug', 'info', 'warning', 'error', 'critical'][$idx % 5],
                'jobId' => (string) ($query['jobId'] ?? 'latest'),
                'entityType' => ['deal', 'contact', 'task', 'file'][$idx % 4],
                'entityId' => 'e-' . random_int(1000, 9999),
                'workerId' => 'w-' . str_pad((string) (($idx % 12) + 1), 2, '0', STR_PAD_LEFT),
                'module' => ['sync', 'mapping', 'integrity', 'worker-pool'][$idx % 4],
                'phase' => ['plan', 'execute', 'verify', 'sync'][$idx % 4],
                'message' => 'Processing batch #' . ($idx + 1),
                'correlationId' => 'corr-' . substr(hash('sha256', (string) $idx), 0, 12),
                'traceId' => 'tr-' . substr(hash('sha1', (string) $idx), 0, 16),
                'payload' => ['attempt' => ($idx % 4) + 1, 'queue' => ['crm', 'user', 'task'][$idx % 3]],
            ];
        }

        return ['items' => $items, 'total' => 3000, 'limit' => $limit, 'offset' => $offset];
    }

    /** @param array{jobId?:string|null} $query @return array<string,mixed> */
    public function dependencyGraph(array $query): array
    {
        $nodes = [];
        $edges = [];
        $types = ['users', 'departments', 'groups', 'crm', 'tasks', 'comments', 'files'];
        for ($i = 1; $i <= 36; ++$i) {
            $nodes[] = [
                'id' => 'n-' . $i,
                'label' => strtoupper(substr($types[$i % count($types)], 0, 3)) . '-' . $i,
                'entityType' => $types[$i % count($types)],
                'status' => ['ok', 'blocked', 'broken', 'pending'][$i % 4],
                'blockedReason' => $i % 4 === 1 ? 'Missing parent relation' : null,
                'criticalChain' => $i % 9 === 0,
            ];
            if ($i > 1) {
                $edges[] = ['from' => 'n-' . ($i - 1), 'to' => 'n-' . $i, 'type' => $i % 6 === 0 ? 'broken' : 'mapped_fk'];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges, 'jobId' => (string) ($query['jobId'] ?? 'latest')];
    }

    /** @param array{jobId?:string|null,x?:string,y?:string} $query @return array<string,mixed> */
    public function heatmap(array $query): array
    {
        $x = (string) ($query['x'] ?? 'entityType');
        $y = (string) ($query['y'] ?? 'phase');
        $xBuckets = ['deal', 'lead', 'contact', 'task', 'file'];
        $yBuckets = ['plan', 'dry-run', 'execute', 'verify', 'sync'];
        $cells = [];
        foreach ($xBuckets as $xb) {
            foreach ($yBuckets as $yb) {
                $cells[] = ['x' => $xb, 'y' => $yb, 'count' => random_int(0, 35), 'critical' => random_int(0, 10) > 7];
            }
        }

        return ['x' => $x, 'y' => $y, 'cells' => $cells, 'jobId' => (string) ($query['jobId'] ?? 'latest')];
    }

    /** @param array{jobId?:string|null} $query @return array<string,mixed> */
    public function mapping(array $query): array
    {
        return [
            'jobId' => (string) ($query['jobId'] ?? 'latest'),
            'requiredFields' => ['TITLE', 'STAGE_ID', 'ASSIGNED_BY_ID'],
            'rules' => [
                ['source' => 'TITLE', 'target' => 'TITLE', 'transform' => 'trim', 'required' => true, 'lossRisk' => false, 'warning' => null],
                ['source' => 'UF_CRM_LEGACY_TYPE', 'target' => 'UF_CRM_SOURCE_KIND', 'transform' => 'enumMap', 'required' => false, 'lossRisk' => true, 'warning' => 'Enum mismatch'],
                ['source' => 'OPPORTUNITY', 'target' => 'OPPORTUNITY', 'transform' => 'currencyNormalize', 'required' => false, 'lossRisk' => false, 'warning' => null],
            ],
            'versions' => [
                ['version' => 'v12', 'createdAt' => (new DateTimeImmutable('-2 days'))->format(DATE_ATOM), 'author' => 'architect'],
                ['version' => 'v11', 'createdAt' => (new DateTimeImmutable('-9 days'))->format(DATE_ATOM), 'author' => 'operator'],
            ],
        ];
    }

    /** @param array{jobId?:string|null} $query @return array<string,mixed> */
    public function diff(array $query): array
    {
        $items = [];
        for ($i = 1; $i <= 40; ++$i) {
            $items[] = [
                'entityId' => 'e-' . (5000 + $i),
                'entityType' => ['deal', 'lead', 'task', 'file'][$i % 4],
                'kind' => ['schema', 'field', 'relation', 'missing'][$i % 4],
                'mismatch' => $i % 5 !== 0,
                'source' => ['status' => 'SRC_' . ($i % 7), 'value' => random_int(100, 200)],
                'target' => ['status' => 'TGT_' . ($i % 5), 'value' => random_int(100, 200)],
            ];
        }

        return ['jobId' => (string) ($query['jobId'] ?? 'latest'), 'items' => $items];
    }

    /** @param array{jobId?:string|null,mode?:string|null} $query @return array<string,mixed> */
    public function replayPreview(array $query): array
    {
        return [
            'jobId' => (string) ($query['jobId'] ?? 'latest'),
            'mode' => (string) ($query['mode'] ?? 'resume'),
            'reviewed' => random_int(200, 1200),
            'willChange' => random_int(40, 300),
            'alreadyMapped' => random_int(100, 800),
            'skippedByCheckpoint' => random_int(10, 200),
            'risks' => ['high_api_pressure', 'locked_stage_dependency'],
        ];
    }

    /** @param array{jobId?:string|null} $query @return array<string,mixed> */
    public function systemHealth(array $query): array
    {
        return [
            'jobId' => (string) ($query['jobId'] ?? 'latest'),
            'throughputPerSec' => random_int(50, 220),
            'eventRate' => random_int(100, 300),
            'queueDepth' => random_int(100, 1000),
            'processingLagSec' => random_int(0, 120),
            'retriesPerMin' => random_int(0, 60),
            'errorBursts' => random_int(0, 5),
            'adaptiveThrottlingState' => ['normal', 'backoff', 'safe_mode'][random_int(0, 2)],
            'safeMode' => (bool) random_int(0, 1),
            'legacyApiPressure' => [
                'rpmLimit' => 120,
                'currentRpm' => random_int(40, 115),
                'backoffMs' => random_int(50, 1000),
                'protectedSyncWindow' => '01:00-06:00 UTC',
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function recentEvents(?string $jobId): array
    {
        $events = [];
        for ($i = 0; $i < 15; ++$i) {
            $events[] = [
                'timestamp' => (new DateTimeImmutable())->sub(new DateInterval('PT' . ($i * 3 + 1) . 'M'))->format(DATE_ATOM),
                'jobId' => $jobId ?? 'latest',
                'kind' => ['queue_spike', 'retry_burst', 'worker_block', 'integrity_detected'][$i % 4],
                'message' => 'System event #' . ($i + 1),
                'severity' => ['info', 'warning', 'error'][$i % 3],
            ];
        }

        return $events;
    }

    /** @param array<string,mixed> $query @return array{items:array<int,array<string,mixed>>,total:int,limit:int,offset:int} */
    private function mockJobs(array $query): array
    {
        $limit = max(1, (int) ($query['limit'] ?? 25));
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $items = [];
        for ($i = 0; $i < $limit; ++$i) {
            $id = $offset + $i + 1;
            $items[] = [
                'jobId' => 'job-' . $id,
                'status' => ['running', 'paused', 'failed', 'completed'][$id % 4],
                'mode' => ['validate', 'plan', 'dry-run', 'execute', 'resume', 'verify', 'sync'][$id % 7],
                'source' => 'legacy-bitrix24',
                'target' => 'target-bitrix24',
                'startedAt' => (new DateTimeImmutable('-' . $id . ' hours'))->format(DATE_ATOM),
                'durationSec' => random_int(300, 10000),
                'stage' => ['extract', 'map', 'load', 'verify', 'sync'][$id % 5],
                'progress' => random_int(0, 100),
                'processed' => random_int(50, 7000),
                'pending' => random_int(0, 800),
                'failed' => random_int(0, 40),
                'skipped' => random_int(0, 80),
            ];
        }

        return ['items' => $items, 'total' => 180, 'limit' => $limit, 'offset' => $offset];
    }

    private function deriveStage(string $status): string
    {
        return match ($status) {
            'failed' => 'recovery',
            'paused' => 'checkpoint',
            'completed' => 'finalize',
            default => 'execute',
        };
    }
}
