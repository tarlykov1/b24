<?php

declare(strict_types=1);

function run_cutover_json(string $cmd): array
{
    exec($cmd . ' 2>&1', $lines, $code);
    if ($code !== 0) {
        throw new RuntimeException("Command failed ($code): $cmd\n" . implode("\n", $lines));
    }
    $raw = trim(implode("\n", $lines));
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON from command: ' . $cmd . "\n" . $raw);
    }

    return $data;
}

$job = run_cutover_json('php bin/migration-module create-job --mode=delta_sync');
$jobId = (string) $job['job_id'];
$freezeId = 'freeze_it_' . bin2hex(random_bytes(3));

$prepare = run_cutover_json("php bin/migration-module cutover:prepare --job-id={$jobId} --freeze-id={$freezeId} --source-instance-id=src1 --target-instance-id=tgt1 --actor=ops");
if (($prepare['state'] ?? '') !== 'prepared') {
    throw new RuntimeException('prepare failed');
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
$readiness = run_cutover_json("php bin/migration-module cutover:readiness --job-id={$jobId} --freeze-id={$freezeId} --signals-json={$signals}");
if (($readiness['readiness']['status'] ?? '') !== 'pass') {
    throw new RuntimeException('readiness should pass');
}

run_cutover_json("php bin/migration-module cutover:arm --job-id={$jobId} --freeze-id={$freezeId} --mode=advisory_freeze --actor=ops");
run_cutover_json("php bin/migration-module cutover:freeze:start --job-id={$jobId} --freeze-id={$freezeId} --mode=advisory_freeze --actor=ops --mutations-json='[]'");
$baseline = escapeshellarg(json_encode(['baseline_reference' => 'checkpoint-1'], JSON_UNESCAPED_SLASHES));
run_cutover_json("php bin/migration-module cutover:delta:final --job-id={$jobId} --freeze-id={$freezeId} --actor=ops --baseline-json={$baseline}");

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
$verify = run_cutover_json("php bin/migration-module cutover:verify --job-id={$jobId} --freeze-id={$freezeId} --actor=ops --signals-json={$verifySignals}");
if (($verify['state'] ?? '') !== 'ready_for_go_live') {
    throw new RuntimeException('verify should make session ready_for_go_live');
}

$ctx = escapeshellarg(json_encode([
    'readiness_status' => 'pass',
    'verification_color' => 'green',
    'blocking_mutations' => 0,
    'unresolved_critical_errors' => 0,
    'delta_failed_count' => 0,
], JSON_UNESCAPED_SLASHES));
$verdict = run_cutover_json("php bin/migration-module cutover:verdict --freeze-id={$freezeId} --context-json={$ctx}");
if (($verdict['rationale']['verdict'] ?? '') !== 'go_live_approved') {
    throw new RuntimeException('verdict should approve');
}

$complete = run_cutover_json("php bin/migration-module cutover:complete --freeze-id={$freezeId} --actor=ops");
if (($complete['state'] ?? '') !== 'completed') {
    throw new RuntimeException('complete failed');
}

echo "ok\n";
