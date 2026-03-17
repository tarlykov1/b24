<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

final class CutoverReadinessEvaluator
{
    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function evaluate(array $signals): array
    {
        $checks = [
            $this->check('source_connectivity', 'connectivity', $signals['source_connectivity'] ?? null, 'critical', $signals['source_connectivity_evidence'] ?? 'missing source ping', true),
            $this->check('target_connectivity', 'connectivity', $signals['target_connectivity'] ?? null, 'critical', $signals['target_connectivity_evidence'] ?? 'missing target ping', true),
            $this->check('db_connectivity', 'database', $signals['db_connectivity'] ?? null, 'critical', $signals['db_connectivity_evidence'] ?? 'db probe failed', true),
            $this->check('filesystem_availability', 'filesystem', $signals['filesystem_availability'] ?? null, 'critical', $signals['filesystem_evidence'] ?? 'upload mount unavailable', true),
            $this->check('migration_job_exists', 'migration', $signals['migration_job_exists'] ?? null, 'critical', $signals['migration_job_evidence'] ?? 'job missing', true),
            $this->check('last_baseline_sync_present', 'sync', $signals['last_baseline_sync_present'] ?? null, 'critical', $signals['baseline_evidence'] ?? 'no approved baseline', true),
            $this->check('critical_integrity_issues', 'integrity', ((int) ($signals['critical_integrity_issues'] ?? 9999)) === 0, 'critical', 'critical=' . (int) ($signals['critical_integrity_issues'] ?? -1), false, array_key_exists('critical_integrity_issues', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('blocking_entity_errors', 'delta', ((int) ($signals['blocking_entity_errors'] ?? 9999)) === 0, 'critical', 'blocking=' . (int) ($signals['blocking_entity_errors'] ?? -1), false, array_key_exists('blocking_entity_errors', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('retry_queue_depth', 'queue', ((int) ($signals['retry_queue_depth'] ?? 9999)) <= (int) ($signals['retry_queue_threshold'] ?? 20), 'warning', 'depth=' . (int) ($signals['retry_queue_depth'] ?? -1), true, array_key_exists('retry_queue_depth', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('mapping_completeness', 'mapping', ((float) ($signals['mapping_completeness'] ?? 0.0)) >= (float) ($signals['mapping_min'] ?? 0.98), 'critical', 'ratio=' . (float) ($signals['mapping_completeness'] ?? 0.0), false, array_key_exists('mapping_completeness', $signals) ? 'manual_input' : 'unavailable'),
            $this->check('disk_temp_space_sanity', 'filesystem', $signals['disk_temp_space_sanity'] ?? null, 'critical', $signals['disk_space_evidence'] ?? 'temp space unknown', true),
            $this->check('admin_session_valid', 'auth', $signals['admin_session_valid'] ?? null, 'warning', $signals['admin_session_evidence'] ?? 'admin token stale', true),
            $this->check('rate_limit_risk', 'throttling', ((int) ($signals['rate_limit_risk_score'] ?? 0)) <= (int) ($signals['rate_limit_risk_threshold'] ?? 70), 'warning', 'risk=' . (int) ($signals['rate_limit_risk_score'] ?? -1), true, array_key_exists('rate_limit_risk_score', $signals) ? 'manual_input' : 'heuristic'),
            $this->check('source_activity_trend', 'source_activity', ((int) ($signals['source_activity_per_min'] ?? 0)) <= (int) ($signals['source_activity_threshold'] ?? 100), 'warning', 'per_min=' . (int) ($signals['source_activity_per_min'] ?? -1), true, array_key_exists('source_activity_per_min', $signals) ? 'manual_input' : 'heuristic'),
            $this->check('delta_sync_health', 'delta', ((int) ($signals['delta_sync_failures_last_runs'] ?? 9999)) <= (int) ($signals['delta_sync_failures_threshold'] ?? 0), 'critical', 'failures=' . (int) ($signals['delta_sync_failures_last_runs'] ?? -1), false, array_key_exists('delta_sync_failures_last_runs', $signals) ? 'manual_input' : 'unavailable'),
        ];

        $criticalFails = array_values(array_filter($checks, static fn (array $c): bool => $c['status'] !== 'passed' && $c['severity'] === 'critical'));
        $warnFails = array_values(array_filter($checks, static fn (array $c): bool => $c['status'] !== 'passed' && $c['severity'] === 'warning'));

        $status = $criticalFails !== [] ? 'blocked' : ($warnFails !== [] ? 'pass_with_warnings' : 'pass');

        return [
            'status' => $status,
            'checks' => $checks,
            'allow_freeze_activation' => $criticalFails === [],
        ];
    }

    /** @return array<string,mixed> */
    private function check(string $code, string $subsystem, mixed $rawValue, string $severity, string $evidence, bool $allowFreezeIfFailed, ?string $provenance = null): array
    {
        $resolvedProvenance = $provenance ?? ($rawValue === null ? 'unavailable' : 'manual_input');
        if ($rawValue === null) {
            $status = 'unavailable';
            $effectiveAllow = $allowFreezeIfFailed && $severity !== 'critical';
            $recommendedAction = 'collect_evidence_' . $code;
        } else {
            $passed = (bool) $rawValue;
            $status = $passed ? 'passed' : 'failed';
            $effectiveAllow = $passed || $allowFreezeIfFailed;
            $recommendedAction = $passed ? 'none' : 'resolve_' . $code;
        }

        return [
            'code' => $code,
            'severity' => $severity,
            'subsystem' => $subsystem,
            'status' => $status,
            'evidence' => $evidence,
            'provenance' => $resolvedProvenance,
            'recommended_action' => $recommendedAction,
            'freeze_activation_allowed' => $effectiveAllow,
        ];
    }
}
