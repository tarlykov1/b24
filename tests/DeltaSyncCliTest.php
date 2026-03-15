<?php

declare(strict_types=1);

function run(string $cmd): array
{
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    $json = json_decode(implode("\n", $output), true);

    if ($code !== 0) {
        throw new RuntimeException('Command failed: ' . $cmd . "\n" . implode("\n", $output));
    }

    if (!is_array($json)) {
        throw new RuntimeException('Invalid JSON from: ' . $cmd . "\n" . implode("\n", $output));
    }

    return $json;
}

function ok(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

@unlink(__DIR__ . '/../.prototype/test-delta.sqlite');
file_put_contents(__DIR__ . '/../migration.delta.config.yml', "batch_size: 2\nstorage:\n  path: .prototype/test-delta.sqlite\n");

$help = run('php bin/migration-module help migration.delta.config.yml');
ok(in_array('migration delta:scan <job_id> [entity_type|all] [--phase=initial_bulk|incremental|pre_cutover|final_cutover]', $help['commands'] ?? [], true), 'delta command listed in help');

$jobId = 'job_delta_test';
$scan = run('php bin/migration-module migration delta:scan ' . $jobId . ' all migration.delta.config.yml --phase=initial_bulk');
ok(($scan['phase'] ?? '') === 'initial_bulk', 'scan phase');
ok(isset($scan['entities']['users']['created']), 'users delta summary exists');

$status1 = run('php bin/migration-module migration delta:status ' . $jobId . ' _ migration.delta.config.yml');
ok(($status1['totals']['pending'] ?? 0) > 0, 'pending changes after scan');

$scanId = (string) ($scan['scan_id'] ?? '');
$apply1 = run('php bin/migration-module migration delta:apply ' . $jobId . ' ' . $scanId . ' migration.delta.config.yml');
ok(($apply1['processed'] ?? 0) > 0, 'first apply processed');
ok(($apply1['idempotent'] ?? false) === true, 'idempotent marker present');

$apply2 = run('php bin/migration-module migration delta:apply ' . $jobId . ' ' . $scanId . ' migration.delta.config.yml');
ok(($apply2['processed'] ?? -1) === 0, 'second apply is idempotent with no duplicates');

$status2 = run('php bin/migration-module migration delta:status ' . $jobId . ' _ migration.delta.config.yml');
ok(($status2['totals']['pending'] ?? -1) === 0, 'no pending after second apply');
ok(($status2['totals']['applied'] ?? 0) >= ($apply1['processed'] ?? 0), 'applied count persisted');
ok(count($status2['cursors'] ?? []) > 0, 'cursors persisted');

unlink(__DIR__ . '/../migration.delta.config.yml');
echo "Delta sync CLI checks passed\n";
