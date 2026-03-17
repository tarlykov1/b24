<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

use PDO;

final class CutoverFinalizationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @param array<string,mixed> $metadata */
    public function createSession(string $freezeId, string $jobId, string $sourceId, string $targetId, string $initiatedBy, string $state, array $metadata = []): bool
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_freeze_sessions(freeze_window_id,job_id,source_instance_id,target_instance_id,current_state,initiated_by,resumable_flag,metadata_json,created_at,updated_at) VALUES(:id,:job,:src,:tgt,:st,:by,1,:meta,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE freeze_window_id=freeze_window_id');
        $stmt->execute([
            'id' => $freezeId,
            'job' => $jobId,
            'src' => $sourceId,
            'tgt' => $targetId,
            'st' => $state,
            'by' => $initiatedBy,
            'meta' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<string,mixed>|null */
    public function session(string $freezeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_freeze_sessions WHERE freeze_window_id=:id LIMIT 1');
        $stmt->execute(['id' => $freezeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @param array<string,mixed> $patch */
    public function patchSession(string $freezeId, array $patch): void
    {
        if ($patch === []) {
            return;
        }
        $set = [];
        $params = ['id' => $freezeId];
        foreach ($patch as $k => $v) {
            $set[] = $k . '=:' . $k;
            $params[$k] = is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v;
        }
        $set[] = 'updated_at=CURRENT_TIMESTAMP';
        $sql = 'UPDATE cutover_freeze_sessions SET ' . implode(', ', $set) . ' WHERE freeze_window_id=:id';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function saveTransition(string $freezeId, string $from, string $to, string $actor, string $eventName, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_freeze_events(freeze_window_id,event_name,from_state,to_state,actor,payload_json) VALUES(:id,:event,:from,:to,:actor,:payload)');
        $stmt->execute([
            'id' => $freezeId,
            'event' => $eventName,
            'from' => $from,
            'to' => $to,
            'actor' => $actor,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @param array<string,mixed> $payload */
    public function saveAuditEvent(string $freezeId, string $eventName, string $actor, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_freeze_events(freeze_window_id,event_name,from_state,to_state,actor,payload_json) VALUES(:id,:event,:from,:to,:actor,:payload)');
        $stmt->execute([
            'id' => $freezeId,
            'event' => $eventName,
            'from' => 'audit',
            'to' => 'audit',
            'actor' => $actor,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @param array<string,mixed> $payload */
    public function saveReadinessReport(string $freezeId, string $status, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_readiness_reports(freeze_window_id,status,payload_json) VALUES(:id,:status,:payload)');
        $stmt->execute(['id' => $freezeId, 'status' => $status, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** @param array<string,mixed> $mutation */
    public function saveMutation(string $freezeId, array $mutation): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_mutation_journal(freeze_window_id,detected_at,entity_type,entity_id,mutation_kind,evidence_source,confidence,policy_impact,auto_recapture_possible,payload_json) VALUES(:id,:detected,:entity_type,:entity_id,:kind,:source,:confidence,:impact,:auto,:payload)');
        $stmt->execute([
            'id' => $freezeId,
            'detected' => $mutation['timestamp'] ?? gmdate(DATE_ATOM),
            'entity_type' => $mutation['entity_type'] ?? 'unknown',
            'entity_id' => (string) ($mutation['entity_id'] ?? ''),
            'kind' => $mutation['mutation_kind'] ?? 'update',
            'source' => $mutation['source_of_evidence'] ?? 'unknown',
            'confidence' => $mutation['confidence'] ?? 'medium',
            'impact' => $mutation['freeze_policy_impact'] ?? 'non_blocking',
            'auto' => !empty($mutation['auto_recapture_possible']) ? 1 : 0,
            'payload' => json_encode($mutation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function mutations(string $freezeId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_mutation_journal WHERE freeze_window_id=:id ORDER BY id');
        $stmt->execute(['id' => $freezeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $payload */
    public function savePhaseCheckpoint(string $freezeId, string $phase, string $checkpointRef, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_phase_checkpoints(freeze_window_id,phase,checkpoint_ref,payload_json) VALUES(:id,:phase,:ref,:payload)');
        $stmt->execute(['id' => $freezeId, 'phase' => $phase, 'ref' => $checkpointRef, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** @param array<string,mixed> $payload */
    public function saveVerification(string $freezeId, string $color, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_verification_reports(freeze_window_id,color,payload_json) VALUES(:id,:color,:payload)');
        $stmt->execute(['id' => $freezeId, 'color' => $color, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** @param array<string,mixed> $payload */
    public function saveVerdict(string $freezeId, string $verdict, array $payload, bool $overrideAllowed, string $risk): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_verdict_history(freeze_window_id,verdict,override_allowed,override_risk_level,payload_json) VALUES(:id,:verdict,:allow,:risk,:payload)');
        $stmt->execute(['id' => $freezeId, 'verdict' => $verdict, 'allow' => $overrideAllowed ? 1 : 0, 'risk' => $risk, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** @param array<string,mixed> $payload */
    public function saveOverride(string $freezeId, string $actor, string $reason, array $payload): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO cutover_operator_overrides(freeze_window_id,actor,reason,payload_json) VALUES(:id,:actor,:reason,:payload)');
        $stmt->execute(['id' => $freezeId, 'actor' => $actor, 'reason' => $reason, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public function incrementMetric(string $name, float $value = 1.0, array $tags = []): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO metrics(job_id, metric_name, metric_value, tags_json) VALUES(:job,:name,:value,:tags)');
        $stmt->execute(['job' => 'cutover', 'name' => $name, 'value' => $value, 'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    /** @return array<string,mixed>|null */
    public function latestVerdict(string $freezeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_verdict_history WHERE freeze_window_id=:id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id' => $freezeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function latestReadinessReport(string $freezeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_readiness_reports WHERE freeze_window_id=:id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id' => $freezeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return array<string,mixed>|null */
    public function latestVerificationReport(string $freezeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cutover_verification_reports WHERE freeze_window_id=:id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id' => $freezeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
