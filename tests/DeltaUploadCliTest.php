<?php

declare(strict_types=1);

function run_json(string $cmd): array
{
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException("Command failed: {$cmd}\n" . implode("\n", $out));
    }
    $json = json_decode(implode("\n", $out), true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON output: ' . $cmd . "\n" . implode("\n", $out));
    }

    return $json;
}

function assert_true(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException('Assertion failed: ' . $message);
    }
}

$base = __DIR__ . '/../.tmp-upload-test';
$source = $base . '/source';
$target = $base . '/target';
@mkdir($source . '/iblock/a', 0777, true);
@mkdir($target . '/iblock/a', 0777, true);
file_put_contents($source . '/iblock/a/one.txt', 'v1');
file_put_contents($source . '/iblock/a/two.txt', 'v2');
file_put_contents($target . '/iblock/a/one.txt', 'v1');

@unlink(__DIR__ . '/../.prototype/test-upload.sqlite');
file_put_contents(__DIR__ . '/../migration.upload.config.yml', "batch_size: 2\nstorage:\n  path: .prototype/test-upload.sqlite\n");

$create = run_json('php bin/migration-module create-job --config=migration.upload.config.yml');
$jobId = (string) ($create['job_id'] ?? '');
assert_true($jobId !== '', 'job id generated');

$baseline = run_json("php bin/migration-module baseline:index --job-id={$jobId} --config=migration.upload.config.yml --source-root={$source} --target-root={$target} --verification-mode=fast");
assert_true(($baseline['indexed_files'] ?? 0) === 2, 'baseline indexed two files');
$baselineId = (string) ($baseline['baseline_id'] ?? '');
assert_true($baselineId !== '', 'baseline id generated');

sleep(1);
file_put_contents($source . '/iblock/a/two.txt', 'v2-new');
file_put_contents($source . '/iblock/a/new.txt', 'new');
file_put_contents($target . '/iblock/a/target-only.txt', 'x');
$refsFile = $base . '/refs.json';
file_put_contents($refsFile, json_encode([
    ['path' => 'iblock/a/two.txt', 'entity_type' => 'tasks'],
    ['path' => 'iblock/a/new.txt', 'entity_type' => 'crm'],
], JSON_UNESCAPED_SLASHES));

$scan = run_json("php bin/migration-module delta:scan --job-id={$jobId} --config=migration.upload.config.yml --baseline-id={$baselineId} --source-root={$source} --target-root={$target} --refs-json={$refsFile}");
$scanId = (string) ($scan['scan_id'] ?? '');
assert_true($scanId !== '', 'scan id returned');
assert_true(($scan['total'] ?? 0) >= 3, 'scan produced items');

$plan = run_json("php bin/migration-module delta:plan --job-id={$jobId} --config=migration.upload.config.yml --scan-id={$scanId}");
$planId = (string) ($plan['plan_id'] ?? '');
assert_true($planId !== '', 'plan id returned');

$exec = run_json("php bin/migration-module delta:execute --job-id={$jobId} --config=migration.upload.config.yml --plan-id={$planId} --source-root={$source} --target-root={$target}");
assert_true(($exec['failed'] ?? 0) === 0, 'no transfer failures');

$cutover = run_json("php bin/migration-module delta:cutover-check --job-id={$jobId} --config=migration.upload.config.yml --scan-id={$scanId}");
assert_true(isset($cutover['cutover_verdict']), 'cutover verdict returned');

@unlink(__DIR__ . '/../migration.upload.config.yml');
echo "Delta upload CLI test passed\n";
