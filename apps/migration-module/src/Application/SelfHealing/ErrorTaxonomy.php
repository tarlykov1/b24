<?php

declare(strict_types=1);

namespace MigrationModule\Application\SelfHealing;

final class ErrorTaxonomy
{
    /** @var array<string,array<string,mixed>> */
    private array $definitions;

    public function __construct()
    {
        $this->definitions = [
            'network_error' => $this->def('E-NETWORK', 'network errors', 'major', true, 'retry_with_backoff', 'escalate_after_max_attempts', 6),
            'timeout' => $this->def('E-TIMEOUT', 'timeout', 'major', true, 'retry_with_backoff', 'escalate_after_max_attempts', 6),
            'rate_limit' => $this->def('E-RATE-LIMIT', 'rate limit', 'major', true, 'retry_with_reduced_concurrency', 'cooldown_and_retry_later', 8),
            'temporary_api_failure' => $this->def('E-TEMP-API', 'temporary API failure', 'major', true, 'retry_with_backoff', 'escalate_after_max_attempts', 6),
            'validation_error' => $this->def('E-VALIDATION', 'validation error', 'medium', true, 'sanitize_invalid_value', 'quarantine_if_still_invalid', 3),
            'missing_dependency' => $this->def('E-MISSING-DEP', 'missing dependency', 'major', true, 'recreate_dependency', 'quarantine_if_dependency_unsafe', 4),
            'missing_user' => $this->def('E-MISSING-USER', 'missing user', 'major', true, 'remap_user_to_fallback', 'quarantine_if_no_fallback', 4),
            'missing_field' => $this->def('E-MISSING-FIELD', 'missing field', 'medium', true, 'auto_create_missing_field', 'quarantine_if_schema_locked', 3),
            'missing_stage' => $this->def('E-MISSING-STAGE', 'missing stage', 'medium', true, 'auto_create_missing_stage', 'postpone_or_quarantine', 4),
            'missing_enum_value' => $this->def('E-MISSING-ENUM', 'missing enum value', 'medium', true, 'auto_create_missing_enum', 'postpone_or_quarantine', 4),
            'duplicate_conflict' => $this->def('E-DUPLICATE', 'duplicate / conflict', 'major', true, 'deduplicate_or_merge_safely', 'quarantine_ambiguous_duplicate', 3),
            'file_transfer_error' => $this->def('E-FILE-TRANSFER', 'file transfer error', 'major', true, 'retry_file_transfer', 'file_quarantine', 5),
            'permission_error' => $this->def('E-PERMISSION', 'permission error', 'critical', false, 'postpone_entity', 'manual_review_required', 1),
            'payload_too_large' => $this->def('E-PAYLOAD-LARGE', 'payload too large', 'medium', true, 'split_payload', 'quarantine_if_split_fails', 3),
            'unsupported_entity_shape' => $this->def('E-UNSUPPORTED-SHAPE', 'unsupported entity shape', 'critical', false, 'move_to_quarantine', 'manual_review_required', 1),
            'data_corruption' => $this->def('E-DATA-CORRUPTION', 'data corruption / malformed data', 'critical', true, 'sanitize_or_reread_source', 'quarantine_if_data_untrusted', 2),
            'reconciliation_mismatch' => $this->def('E-RECON-MISMATCH', 'reconciliation mismatch', 'major', true, 'create_repair_job', 'manual_review_after_repair', 4),
            'mapping_error' => $this->def('E-MAPPING', 'mapping error', 'major', true, 'recalculate_mapping', 'quarantine_if_confidence_low', 4),
        ];
    }

    /** @param array<string,mixed> $error @return array<string,mixed> */
    public function classify(array $error): array
    {
        $category = (string) ($error['category'] ?? $this->detectCategory($error));
        $definition = $this->definitions[$category] ?? $this->definitions['validation_error'];

        return $definition + [
            'category_key' => $category,
            'message' => (string) ($error['message'] ?? ''),
            'error_code' => (string) ($error['error_code'] ?? $definition['code']),
        ];
    }

    /** @return array<string,array<string,mixed>> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /** @param array<string,mixed> $error */
    private function detectCategory(array $error): string
    {
        $message = mb_strtolower((string) ($error['message'] ?? ''));
        $code = mb_strtolower((string) ($error['error_code'] ?? ''));
        $h = $code . ' ' . $message;

        return match (true) {
            str_contains($h, '429') || str_contains($h, 'rate') => 'rate_limit',
            str_contains($h, 'timeout') => 'timeout',
            str_contains($h, 'network') => 'network_error',
            str_contains($h, 'missing user') => 'missing_user',
            str_contains($h, 'missing stage') => 'missing_stage',
            str_contains($h, 'missing enum') => 'missing_enum_value',
            str_contains($h, 'missing field') => 'missing_field',
            str_contains($h, 'mapping') => 'mapping_error',
            str_contains($h, 'duplicate') || str_contains($h, 'conflict') => 'duplicate_conflict',
            str_contains($h, 'payload') && str_contains($h, 'large') => 'payload_too_large',
            str_contains($h, 'permission') => 'permission_error',
            str_contains($h, 'file') => 'file_transfer_error',
            str_contains($h, 'malformed') || str_contains($h, 'corrupt') => 'data_corruption',
            str_contains($h, 'reconciliation') => 'reconciliation_mismatch',
            str_contains($h, 'dependency') => 'missing_dependency',
            default => 'validation_error',
        };
    }

    /** @return array<string,mixed> */
    private function def(string $code, string $category, string $severity, bool $retryable, string $strategy, string $escalation, int $maxAttempts): array
    {
        return [
            'code' => $code,
            'category' => $category,
            'severity' => $severity,
            'retryable' => $retryable,
            'healing_strategy' => $strategy,
            'escalation_policy' => $escalation,
            'max_attempts' => $maxAttempts,
        ];
    }
}
