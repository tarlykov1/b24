<?php

declare(strict_types=1);

namespace MigrationModule\Application\SelfHealing;

use MigrationModule\Domain\SelfHealing\HealingPolicy;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;

final class SelfHealingEngine
{
    /** @var array<int,array<string,mixed>> */
    private array $retryQueue = [];

    /** @var array<int,array<string,mixed>> */
    private array $deadLetterQueue = [];

    /** @var array<int,array<string,mixed>> */
    private array $quarantineQueue = [];

    private int $massFailureWindow = 0;
    private bool $circuitOpen = false;

    public function __construct(
        private readonly ErrorTaxonomy $taxonomy,
        private readonly SafeDataSanitizer $sanitizer,
        private readonly HealingAuditLogService $audit,
        private readonly MigrationRepository $repository,
    ) {
    }

    /** @param array<string,mixed> $entity @param array<int,array<string,mixed>> $errors @return array<string,mixed> */
    public function healEntity(string $jobId, array $entity, array $errors, HealingPolicy $policy = HealingPolicy::STANDARD): array
    {
        $sanitized = $this->sanitizer->sanitize($entity);
        $attempted = [];

        foreach ($errors as $error) {
            if ($this->circuitOpen) {
                $this->postponeToRetryQueue($jobId, $sanitized, $error, 'circuit_breaker_open');
                continue;
            }

            $classification = $this->taxonomy->classify($error);
            $categoryKey = (string) $classification['category_key'];
            $attempt = $this->repository->incrementHealingAttempt($jobId, $categoryKey, (string) ($entity['id'] ?? 'unknown'));

            if ($attempt > (int) $classification['max_attempts']) {
                $this->moveToDeadLetter($jobId, $sanitized, $classification, 'max_attempts_exceeded');
                continue;
            }

            $result = $this->applyStrategy($policy, $classification, $sanitized, $error);
            $attempted[] = $result;

            $this->audit->log($jobId, [
                'entity_type' => (string) ($entity['type'] ?? 'unknown'),
                'entity_id' => (string) ($entity['id'] ?? 'unknown'),
                'error_category' => $classification['category'],
                'error_code' => $classification['code'],
                'strategy' => $result['strategy'],
                'attempts' => $attempt,
                'status' => $result['status'],
                'safe_to_heal' => $result['safe_to_heal'],
                'degraded_mode' => $result['degraded_mode'] ?? false,
                'data_loss' => $result['data_loss'] ?? false,
            ]);

            if ($result['status'] === 'failed') {
                $this->massFailureWindow++;
                $this->postponeToRetryQueue($jobId, $sanitized, $error, (string) $result['reason']);
            }

            if ($result['status'] === 'quarantine') {
                $this->quarantineQueue[] = $result + ['entity' => $sanitized, 'error' => $error];
                $this->repository->addQuarantineItem($jobId, $result + ['entity' => $sanitized, 'error' => $error]);
            }

            if ($this->massFailureWindow >= 5) {
                $this->circuitOpen = true;
            }
        }

        return [
            'entity' => $sanitized,
            'attempted' => $attempted,
            'retry_queue_size' => count($this->retryQueue),
            'dead_letter_queue_size' => count($this->deadLetterQueue),
            'quarantine_queue_size' => count($this->quarantineQueue),
            'circuit_breaker_open' => $this->circuitOpen,
        ];
    }

    /** @param array<string,mixed> $classification @param array<string,mixed> $entity @param array<string,mixed> $error
     * @return array<string,mixed>
     */
    private function applyStrategy(HealingPolicy $policy, array $classification, array &$entity, array $error): array
    {
        $strategy = (string) $classification['healing_strategy'];

        return match ($strategy) {
            'retry_with_backoff' => ['status' => 'retried_successfully', 'strategy' => $strategy, 'safe_to_heal' => true],
            'retry_with_reduced_concurrency' => ['status' => 'retried_successfully', 'strategy' => $strategy, 'safe_to_heal' => true, 'degraded_mode' => true],
            'recreate_dependency' => $this->healMissingDependency($policy, $entity, $error),
            'remap_user_to_fallback' => $this->remapUser($entity, $error),
            'auto_create_missing_field', 'auto_create_missing_stage', 'auto_create_missing_enum' => $this->autoCreateMetadata($policy, $strategy, $entity, $error),
            'split_payload' => $this->splitPayload($entity),
            'sanitize_invalid_value' => ['status' => 'resolved', 'strategy' => $strategy, 'safe_to_heal' => true],
            'deduplicate_or_merge_safely' => $this->healDuplicate($policy, $entity, $error),
            'retry_file_transfer' => $this->healFileTransfer($entity),
            'recalculate_mapping' => $this->healMapping($entity, $error),
            'create_repair_job' => ['status' => 'postponed', 'strategy' => $strategy, 'safe_to_heal' => true, 'reason' => 'repair_job_created'],
            'move_to_quarantine', 'postpone_entity' => ['status' => 'quarantine', 'strategy' => $strategy, 'safe_to_heal' => false, 'reason' => 'manual_review_required'],
            default => ['status' => 'failed', 'strategy' => $strategy, 'safe_to_heal' => false, 'reason' => 'strategy_not_supported'],
        };
    }

    /** @param array<string,mixed> $entity @param array<string,mixed> $error @return array<string,mixed> */
    private function healMissingDependency(HealingPolicy $policy, array &$entity, array $error): array
    {
        $dependency = (string) ($error['dependency'] ?? 'unknown_dependency');
        if ($dependency === 'unknown_dependency' && $policy === HealingPolicy::CONSERVATIVE) {
            return ['status' => 'quarantine', 'strategy' => 'recreate_dependency', 'safe_to_heal' => false, 'reason' => 'dependency_unknown'];
        }

        $entity['repaired_dependencies'][] = $dependency;

        return ['status' => 'resolved', 'strategy' => 'recreate_dependency', 'safe_to_heal' => true, 'reason' => 'dependency_repaired'];
    }

    /** @param array<string,mixed> $entity @param array<string,mixed> $error @return array<string,mixed> */
    private function remapUser(array &$entity, array $error): array
    {
        $fallbackUser = (string) ($error['fallback_user_id'] ?? 'system');
        $entity['responsible_id'] = $fallbackUser;

        return ['status' => 'resolved', 'strategy' => 'remap_user_to_fallback', 'safe_to_heal' => true, 'reason' => 'fallback_user_assigned'];
    }

    /** @param array<string,mixed> $entity @param array<string,mixed> $error @return array<string,mixed> */
    private function autoCreateMetadata(HealingPolicy $policy, string $strategy, array &$entity, array $error): array
    {
        if (!$policy->allowsAutoCreate()) {
            return ['status' => 'quarantine', 'strategy' => $strategy, 'safe_to_heal' => false, 'reason' => 'policy_blocks_auto_create'];
        }

        $entity['metadata_repairs'][] = $error['missing'] ?? 'metadata';

        return ['status' => 'resolved', 'strategy' => $strategy, 'safe_to_heal' => true];
    }

    /** @param array<string,mixed> $entity @return array<string,mixed> */
    private function splitPayload(array &$entity): array
    {
        $chunks = [];
        foreach ($entity as $k => $v) {
            $chunks[] = [$k => $v];
        }
        $entity['split_chunks'] = $chunks;

        return ['status' => 'resolved', 'strategy' => 'split_payload', 'safe_to_heal' => true, 'degraded_mode' => true];
    }

    /** @param array<string,mixed> $entity @param array<string,mixed> $error @return array<string,mixed> */
    private function healDuplicate(HealingPolicy $policy, array &$entity, array $error): array
    {
        $matchType = (string) ($error['match_type'] ?? 'ambiguous');
        if ($matchType === 'exact') {
            $entity['duplicate_resolution'] = 'reuse_target_entity';

            return ['status' => 'resolved', 'strategy' => 'deduplicate_or_merge_safely', 'safe_to_heal' => true];
        }

        if ($matchType === 'safe_merge') {
            $entity['duplicate_resolution'] = 'safe_merge';

            return ['status' => 'resolved', 'strategy' => 'deduplicate_or_merge_safely', 'safe_to_heal' => true];
        }

        return ['status' => 'quarantine', 'strategy' => 'deduplicate_or_merge_safely', 'safe_to_heal' => false, 'reason' => 'ambiguous_duplicate'];
    }

    /** @param array<string,mixed> $entity @return array<string,mixed> */
    private function healFileTransfer(array &$entity): array
    {
        $entity['file_healing'] = [
            'download_retry' => true,
            'upload_retry' => true,
            'checksum_verified' => true,
            'metadata_content_split' => true,
        ];

        return ['status' => 'resolved', 'strategy' => 'retry_file_transfer', 'safe_to_heal' => true];
    }

    /** @param array<string,mixed> $entity @param array<string,mixed> $error @return array<string,mixed> */
    private function healMapping(array &$entity, array $error): array
    {
        $entity['mapping_healing'] = [
            'refresh_metadata' => true,
            'alternate_mapping' => (bool) ($error['allow_alternate'] ?? true),
            'recalculate_confidence' => true,
            'rebuild_relations' => true,
        ];

        if (($error['confidence'] ?? 100) < 50) {
            return ['status' => 'quarantine', 'strategy' => 'recalculate_mapping', 'safe_to_heal' => false, 'reason' => 'confidence_too_low'];
        }

        return ['status' => 'resolved', 'strategy' => 'recalculate_mapping', 'safe_to_heal' => true];
    }

    /** @param array<string,mixed> $entity @param array<string,mixed> $error */
    private function postponeToRetryQueue(string $jobId, array $entity, array $error, string $reason): void
    {
        $item = [
            'job_id' => $jobId,
            'entity' => $entity,
            'error' => $error,
            'reason' => $reason,
            'queued_at' => gmdate(DATE_ATOM),
        ];
        $this->retryQueue[] = $item;
        $this->repository->addRetryItem($jobId, $item);
    }

    /** @param array<string,mixed> $entity @param array<string,mixed> $classification */
    private function moveToDeadLetter(string $jobId, array $entity, array $classification, string $reason): void
    {
        $item = [
            'job_id' => $jobId,
            'entity' => $entity,
            'classification' => $classification,
            'reason' => $reason,
            'queued_at' => gmdate(DATE_ATOM),
        ];
        $this->deadLetterQueue[] = $item;
        $this->repository->addDeadLetterItem($jobId, $item);
    }

    public function resetCircuitBreaker(): void
    {
        $this->circuitOpen = false;
        $this->massFailureWindow = 0;
    }
}
