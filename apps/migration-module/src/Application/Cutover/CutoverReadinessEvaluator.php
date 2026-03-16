<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

final class CutoverReadinessEvaluator
{
    /** @param array<string,mixed> $signals @return array<string,mixed> */
    public function evaluate(array $signals): array
    {
        $checks = [
            $this->check('source_connectivity', 'connectivity', (bool) ($signals['source_connectivity'] ?? false), 'critical', $signals['source_connectivity_evidence'] ?? 'missing source ping', true),
            $this->check('target_connectivity', 'connectivity', (bool) ($signals['target_connectivity'] ?? false), 'critical', $signals['target_connectivity_evidence'] ?? 'missing target ping', true),
            $this->check('db_connectivity', 'database', (bool) ($signals['db_connectivity'] ?? false), 'critical', $signals['db_connectivity_evidence'] ?? 'db probe failed', true),
            $this->check('filesystem_availability', 'filesystem', (bool) ($signals['filesystem_availability'] ?? false), 'critical', $signals['filesystem_evidence'] ?? 'upload mount unavailable', true),
            $this->check('migration_job_exists', 'migration', (bool) ($signals['migration_job_exists'] ?? false), 'critical', $signals['migration_job_evidence'] ?? 'job missing', true),
            $this->check('last_baseline_sync_present', 'sync', (bool) ($signals['last_baseline_sync_present'] ?? false), 'critical', $signals['baseline_evidence'] ?? 'no approved baseline', true),
            $this->check('critical_integrity_issues', 'integrity', ((int) ($signals['critical_integrity_issues'] ?? 0)) === 0, 'critical', 'critical=' . (int) ($signals['critical_integrity_issues'] ?? 0), false),
            $this->check('blocking_entity_errors', 'delta', ((int) ($signals['blocking_entity_errors'] ?? 0)) === 0, 'critical', 'blocking=' . (int) ($signals['blocking_entity_errors'] ?? 0), false),
            $this->check('retry_queue_depth', 'queue', ((int) ($signals['retry_queue_depth'] ?? 0)) <= (int) ($signals['retry_queue_threshold'] ?? 20), 'warning', 'depth=' . (int) ($signals['retry_queue_depth'] ?? 0), true),
            $this->check('mapping_completeness', 'mapping', ((float) ($signals['mapping_completeness'] ?? 0.0)) >= (float) ($signals['mapping_min'] ?? 0.98), 'critical', 'ratio=' . (float) ($signals['mapping_completeness'] ?? 0.0), false),
            $this->check('disk_temp_space_sanity', 'filesystem', (bool) ($signals['disk_temp_space_sanity'] ?? false), 'critical', $signals['disk_space_evidence'] ?? 'temp space unknown', true),
            $this->check('admin_session_valid', 'auth', (bool) ($signals['admin_session_valid'] ?? false), 'warning', $signals['admin_session_evidence'] ?? 'admin token stale', true),
            $this->check('rate_limit_risk', 'throttling', ((int) ($signals['rate_limit_risk_score'] ?? 0)) <= (int) ($signals['rate_limit_risk_threshold'] ?? 70), 'warning', 'risk=' . (int) ($signals['rate_limit_risk_score'] ?? 0), true),
            $this->check('source_activity_trend', 'source_activity', ((int) ($signals['source_activity_per_min'] ?? 0)) <= (int) ($signals['source_activity_threshold'] ?? 100), 'warning', 'per_min=' . (int) ($signals['source_activity_per_min'] ?? 0), true),
            $this->check('delta_sync_health', 'delta', ((int) ($signals['delta_sync_failures_last_runs'] ?? 0)) <= (int) ($signals['delta_sync_failures_threshold'] ?? 0), 'critical', 'failures=' . (int) ($signals['delta_sync_failures_last_runs'] ?? 0), false),
        ];

        $criticalFails = array_values(array_filter($checks, static fn (array $c): bool => $c['status'] === 'failed' && $c['severity'] === 'critical'));
        $warnFails = array_values(array_filter($checks, static fn (array $c): bool => $c['status'] === 'failed' && $c['severity'] === 'warning'));

        $status = $criticalFails !== [] ? 'blocked' : ($warnFails !== [] ? 'pass_with_warnings' : 'pass');

        return [
            'status' => $status,
            'checks' => $checks,
            'allow_freeze_activation' => $criticalFails === [],
        ];
    }

    /** @return array<string,mixed> */
    private function check(string $code, string $subsystem, bool $passed, string $severity, string $evidence, bool $allowFreezeIfFailed): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'subsystem' => $subsystem,
            'status' => $passed ? 'passed' : 'failed',
            'evidence' => $evidence,
            'recommended_action' => $passed ? 'none' : 'resolve_' . $code,
            'freeze_activation_allowed' => $passed || $allowFreezeIfFailed,
        ];
    }
}
