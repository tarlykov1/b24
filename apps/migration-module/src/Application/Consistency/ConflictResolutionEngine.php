<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use PDO;

final class ConflictResolutionEngine
{
    private const SUPPORTED_POLICIES = [
        'preserve_target',
        'overwrite_target',
        'merge',
        'remap_reference',
        'skip',
        'manual_resolution',
    ];

    public function __construct(private readonly MigrationRepository $repository, private readonly ?PDO $pdo = null)
    {
    }

    /** @return array<int,array<string,mixed>> */
    public function list(string $jobId): array
    {
        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare('SELECT id, job_id, entity_type, entity_id, conflict_type, payload, resolution_status, resolution_policy, resolution_payload, created_at, resolved_at FROM migration_conflicts WHERE job_id=:job_id ORDER BY created_at DESC');
            $stmt->execute(['job_id' => $jobId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(static function (array $row): array {
                $row['payload'] = json_decode((string) ($row['payload'] ?? '{}'), true) ?: [];
                $row['resolution_payload'] = json_decode((string) ($row['resolution_payload'] ?? '{}'), true) ?: [];

                return $row;
            }, $rows);
        }

        return $this->repository->conflicts($jobId);
    }

    /** @param array<string,mixed> $resolution */
    public function resolve(string $jobId, string $conflictId, array $resolution): array
    {
        $policy = (string) ($resolution['policy'] ?? 'manual_resolution');
        if (!in_array($policy, self::SUPPORTED_POLICIES, true)) {
            throw new \InvalidArgumentException('Unsupported conflict policy: ' . $policy);
        }

        $decision = [
            'conflict_id' => $conflictId,
            'policy' => $policy,
            'payload' => $resolution,
            'resolved_at' => date(DATE_ATOM),
        ];

        $this->repository->saveManualOverride($jobId, 'conflict:' . $conflictId, $decision);

        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare('INSERT INTO migration_operator_decisions(job_id, decision_key, policy, payload) VALUES(:job_id,:decision_key,:policy,:payload)');
            $stmt->execute([
                'job_id' => $jobId,
                'decision_key' => 'conflict:' . $conflictId,
                'policy' => $policy,
                'payload' => (string) json_encode($resolution, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $update = $this->pdo->prepare('UPDATE migration_conflicts SET resolution_status=:status, resolution_policy=:policy, resolution_payload=:resolution_payload, resolved_at=CURRENT_TIMESTAMP WHERE id=:id AND job_id=:job_id');
            $update->execute([
                'status' => 'resolved',
                'policy' => $policy,
                'resolution_payload' => (string) json_encode($resolution, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => $conflictId,
                'job_id' => $jobId,
            ]);
        }
        $this->repository->saveOperatorDecision($jobId, [
            'decision_key' => 'conflict:' . $conflictId,
            'strategy' => $policy,
            'payload' => $resolution,
            'timestamp' => $decision['resolved_at'],
        ]);

        return ['status' => 'resolved', 'conflict_id' => $conflictId, 'policy' => $policy, 'stored' => true];
    }

    /** @return list<string> */
    public function supportedPolicies(): array
    {
        return self::SUPPORTED_POLICIES;
    }
}
