<?php

declare(strict_types=1);

function run_cutover_json(string $cmd, ?int &$code = null): array
{
    exec($cmd . ' 2>&1', $lines, $exitCode);
    $code = $exitCode;
    $raw = trim(implode("\n", $lines));
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON from command: ' . $cmd . "\n" . $raw);
    }

    return $data;
}

$missing = run_cutover_json('php bin/migration-module cutover:freeze:status', $code);
if ($code === 0 || ($missing['error']['error_code'] ?? '') !== 'missing_required_argument') {
    throw new RuntimeException('missing argument should fail with structured error');
}

$job = run_cutover_json('php bin/migration-module create-job --mode=delta_sync', $code);
if ($code !== 0) {
    throw new RuntimeException('create-job failed unexpectedly');
}
$jobId = (string) $job['job_id'];
$freezeId = 'freeze_it_' . bin2hex(random_bytes(3));

$prepare = run_cutover_json("php bin/migration-module cutover:prepare --job-id={$jobId} --freeze-id={$freezeId} --source-instance-id=src1 --target-instance-id=tgt1 --actor=ops", $code);
if ($code !== 0 || ($prepare['state'] ?? '') !== 'prepared') {
    throw new RuntimeException('prepare failed');
}

$prepareDuplicate = run_cutover_json("php bin/migration-module cutover:prepare --job-id={$jobId} --freeze-id={$freezeId} --source-instance-id=src1 --target-instance-id=tgt1 --actor=ops", $code);
if ($code !== 0 || ($prepareDuplicate['state'] ?? '') !== 'prepared') {
    throw new RuntimeException('duplicate prepare in prepared state must be idempotent');
}

$completeDenied = run_cutover_json("php bin/migration-module cutover:complete --freeze-id={$freezeId} --actor=ops", $code);
if ($code === 0 || ($completeDenied['error']['error_code'] ?? '') !== 'complete_gate_denied') {
    throw new RuntimeException('complete without verdict must fail with structured gate error');
}

$signals = escapeshellarg(json_encode([
    'source_connectivity' => true,
    'target_connectivity' => true,
    'db_connectivity' => true,
    'filesystem_availability' => true,
    'migration_job_exists' => true,
    'last_baseline_sync_present' => true,
    'critical_integrity_issues' => 0,
    'blocking_entity_errors' => 0,
    'mapping_completeness' => 1.0,
    'disk_temp_space_sanity' => true,
    'admin_session_valid' => true,
    'source_activity_per_min' => 10,
    'delta_sync_failures_last_runs' => 0,
], JSON_UNESCAPED_SLASHES));
$readiness = run_cutover_json("php bin/migration-module cutover:readiness --job-id={$jobId} --freeze-id={$freezeId} --signals-json={$signals}", $code);
if ($code !== 0 || !in_array(($readiness['readiness']['status'] ?? ''), ['pass','pass_with_warnings'], true)) {
    throw new RuntimeException('readiness should pass');
}

run_cutover_json("php bin/migration-module cutover:arm --job-id={$jobId} --freeze-id={$freezeId} --mode=advisory_freeze --actor=ops", $code);
run_cutover_json("php bin/migration-module cutover:freeze:start --job-id={$jobId} --freeze-id={$freezeId} --mode=advisory_freeze --actor=ops --mutations-json='[]'", $code);
$baseline = escapeshellarg(json_encode(['baseline_reference' => 'checkpoint-1'], JSON_UNESCAPED_SLASHES));
run_cutover_json("php bin/migration-module cutover:delta:final --job-id={$jobId} --freeze-id={$freezeId} --actor=ops --baseline-json={$baseline}", $code);

$verifySignals = escapeshellarg(json_encode([
    'entity_count_diff' => 0,
    'sample_mismatch_count' => 0,
    'mapping_completeness' => 1.0,
    'failed_queue_items' => 0,
    'orphan_references' => 0,
    'missing_attachments' => 0,
    'critical_field_mismatch' => 0,
    'target_write_failures' => 0,
    'missing_required_users' => 0,
    'custom_field_mapping_completeness' => 1.0,
    'target_smoke_ok' => true,
], JSON_UNESCAPED_SLASHES));
$verify = run_cutover_json("php bin/migration-module cutover:verify --job-id={$jobId} --freeze-id={$freezeId} --actor=ops --signals-json={$verifySignals}", $code);
if ($code !== 0 || ($verify['state'] ?? '') !== 'ready_for_go_live') {
    throw new RuntimeException('verify should make session ready_for_go_live');
}

$ctx = escapeshellarg(json_encode([
    'readiness_status' => 'pass',
    'verification_color' => 'green',
    'blocking_mutations' => 0,
    'unresolved_critical_errors' => 0,
    'delta_failed_count' => 0,
], JSON_UNESCAPED_SLASHES));
$verdict = run_cutover_json("php bin/migration-module cutover:verdict --freeze-id={$freezeId} --context-json={$ctx}", $code);
if ($code !== 0 || ($verdict['rationale']['verdict'] ?? '') !== 'go_live_approved') {
    throw new RuntimeException('verdict should approve');
}

$complete = run_cutover_json("php bin/migration-module cutover:complete --freeze-id={$freezeId} --actor=ops", $code);
if ($code !== 0 || ($complete['state'] ?? '') !== 'completed') {
    throw new RuntimeException('complete failed');
}

echo "ok\n";
