<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

final class CutoverVerificationRunner
{
    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function run(array $signals): array
    {
        $checks = [
            'entity_count_comparison' => (($signals['entity_count_diff'] ?? 0) <= ($signals['entity_count_diff_threshold'] ?? 0)),
            'sampled_record_equivalence' => (($signals['sample_mismatch_count'] ?? 0) === 0),
            'mapping_completeness' => (($signals['mapping_completeness'] ?? 0.0) >= ($signals['mapping_min'] ?? 0.98)),
            'unresolved_failed_queue_items' => (($signals['failed_queue_items'] ?? 0) === 0),
            'orphan_references' => (($signals['orphan_references'] ?? 0) === 0),
            'attachment_presence' => (($signals['missing_attachments'] ?? 0) === 0),
            'critical_field_parity' => (($signals['critical_field_mismatch'] ?? 0) === 0),
            'target_write_success_sanity' => (($signals['target_write_failures'] ?? 0) === 0),
            'required_users_present' => (($signals['missing_required_users'] ?? 0) === 0),
            'custom_field_mapping_completeness' => (($signals['custom_field_mapping_completeness'] ?? 1.0) >= ($signals['custom_field_mapping_min'] ?? 0.98)),
            'target_smoke_sanity' => (($signals['target_smoke_ok'] ?? false) === true),
        ];

        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => $ok === false));
        $warnings = [];
        if (($signals['sample_mismatch_count'] ?? 0) > 0 && ($signals['sample_mismatch_count'] ?? 0) <= 2) {
            $warnings[] = 'sampled_record_equivalence';
        }

        $color = $failed === [] ? ($warnings === [] ? 'green' : 'yellow') : 'red';

        return ['color' => $color, 'checks' => $checks, 'failed_checks' => $failed, 'warnings' => $warnings];
    }
}
