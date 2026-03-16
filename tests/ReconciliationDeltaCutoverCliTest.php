<?php

declare(strict_types=1);

function run_cmd(string $cmd): array
{
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException('Command failed: ' . $cmd . "\n" . implode("\n", $output));
    }

    $json = json_decode(implode("\n", $output), true);
    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON: ' . $cmd . "\n" . implode("\n", $output));
    }

    return $json;
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

@unlink(__DIR__ . '/../.prototype/test-reconcile.sqlite');
file_put_contents(__DIR__ . '/../migration.reconcile.config.yml', "batch_size: 2\nstorage:\n  path: .prototype/test-reconcile.sqlite\n");

$create = run_cmd('php bin/migration-module create-job execute');
$jobId = (string) ($create['job_id'] ?? '');
assert_true($jobId !== '', 'job id created');

$reconcile = run_cmd('php bin/migration-module migration reconcile ' . $jobId . ' migration.reconcile.config.yml --entity=tasks --strategy=balanced --limit=5 --sample=1 --rate-limit=1000');
assert_true(($reconcile['processed'] ?? 0) >= 1, 'reconcile processed rows');
assert_true(isset($reconcile['reports']['json']), 'reconcile report path returned');

$delta = run_cmd('php bin/migration-module migration delta-sync ' . $jobId . ' migration.reconcile.config.yml --since=2024-01-01T00:00:00+00:00 --entity=tasks --batch-size=2 --rate-limit=1000 --dry-run');
assert_true(($delta['scanned'] ?? 0) >= 1, 'delta scanned rows');
assert_true(($delta['dry_run'] ?? false) === true, 'delta dry run true');

$cutover = run_cmd('php bin/migration-module migration cutover-check ' . $jobId . ' migration.reconcile.config.yml --sample=2');
assert_true(isset($cutover['final_status']), 'cutover has final status');

$simulate = run_cmd('php bin/migration-module migration cutover-simulate ' . $jobId . ' migration.reconcile.config.yml --entity=tasks --since=2024-01-01T00:00:00+00:00 --batch-size=2 --sample=2 --rate-limit=1000');
assert_true(($simulate['mode'] ?? '') === 'simulation', 'simulation mode result');

@unlink(__DIR__ . '/../migration.reconcile.config.yml');
echo "Reconciliation/Delta/Cutover CLI checks passed\n";
