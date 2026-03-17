<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

final class CutoverVerificationRunner
{
    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function run(array $signals): array
    {
        $checks = [
            $this->check('entity_count_comparison', (($signals['entity_count_diff'] ?? 9999) <= ($signals['entity_count_diff_threshold'] ?? 0)), array_key_exists('entity_count_diff', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('sampled_record_equivalence', (($signals['sample_mismatch_count'] ?? 9999) === 0), array_key_exists('sample_mismatch_count', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('mapping_completeness', (($signals['mapping_completeness'] ?? 0.0) >= ($signals['mapping_min'] ?? 0.98)), array_key_exists('mapping_completeness', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('unresolved_failed_queue_items', (($signals['failed_queue_items'] ?? 9999) === 0), array_key_exists('failed_queue_items', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('orphan_references', (($signals['orphan_references'] ?? 9999) === 0), array_key_exists('orphan_references', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('attachment_presence', (($signals['missing_attachments'] ?? 9999) === 0), array_key_exists('missing_attachments', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('critical_field_parity', (($signals['critical_field_mismatch'] ?? 9999) === 0), array_key_exists('critical_field_mismatch', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('target_write_success_sanity', (($signals['target_write_failures'] ?? 9999) === 0), array_key_exists('target_write_failures', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('required_users_present', (($signals['missing_required_users'] ?? 9999) === 0), array_key_exists('missing_required_users', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('custom_field_mapping_completeness', (($signals['custom_field_mapping_completeness'] ?? 0.0) >= ($signals['custom_field_mapping_min'] ?? 0.98)), array_key_exists('custom_field_mapping_completeness', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('target_smoke_sanity', (($signals['target_smoke_ok'] ?? null) === true), array_key_exists('target_smoke_ok', $signals) ? 'manual_input' : 'unavailable'),
        ];

        $failed = array_values(array_map(static fn (array $check): string => (string) $check['code'], array_filter($checks, static fn (array $check): bool => $check['status'] !== 'passed')));
        $warnings = [];
        if (($signals['sample_mismatch_count'] ?? 0) > 0 && ($signals['sample_mismatch_count'] ?? 0) <= 2) {
            $warnings[] = 'sampled_record_equivalence';
        }

        $color = $failed === [] ? ($warnings === [] ? 'green' : 'yellow') : 'red';

        return ['color' => $color, 'checks' => $checks, 'failed_checks' => $failed, 'warnings' => $warnings];
    }

    /** @return array<string,mixed> */
    private function check(string $code, bool $ok, string $provenance): array
    {
        return [
            'code' => $code,
            'status' => $ok ? 'passed' : ($provenance === 'unavailable' ? 'unavailable' : 'failed'),
            'provenance' => $provenance,
        ];
    }
}
