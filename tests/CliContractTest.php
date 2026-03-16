<?php

declare(strict_types=1);

function runCmd(string $cmd, ?int &$exitCode = null, ?string &$stderr = null): array
{
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/..');
    if (!is_resource($proc)) {
        throw new RuntimeException('Cannot start command: ' . $cmd);
    }
    $stdout = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    $stderrOut = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);
    $exitCode = proc_close($proc);
    $stderr = $stderrOut;

    $decoded = json_decode($stdout, true);

    return is_array($decoded) ? $decoded : ['raw' => $stdout];
}

function ok(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$cfg = __DIR__ . '/../migration.test.cli.config.yml';
$db = __DIR__ . '/../.prototype/test-cli.sqlite';
@unlink($db);
file_put_contents($cfg, "batch_size: 2\nstorage:\n  path: .prototype/test-cli.sqlite\nretry_policy:\n  max_retries: 2\nruntime:\n  profile: test\nid_preservation_policy: preserve_if_possible\n");

$create = runCmd('php bin/migration-module create-job --config=' . escapeshellarg($cfg) . ' --mode=execute', $code, $err);
ok($code === 0, 'create-job exits 0');
ok(isset($create['job_id']), 'create-job returns job id');
$jobId = (string) $create['job_id'];

$dry = runCmd('php bin/migration-module dry-run --config=' . escapeshellarg($cfg) . ' --job-id=' . escapeshellarg($jobId) . ' --format=json --mode=dry-run', $code, $err);
ok($code === 0, 'dry-run exits 0');
ok(($dry['mode'] ?? null) === 'dry-run', 'dry-run uses new contract');

$legacy = runCmd('php bin/migration-module dry-run ' . escapeshellarg($cfg) . ' ' . escapeshellarg($jobId), $code, $err);
ok($code === 0, 'legacy positional still works');
ok(str_contains((string) $err, 'deprecated_cli'), 'legacy positional emits deprecation warning');

$invalid = runCmd('php bin/migration-module execute --config=' . escapeshellarg($cfg) . ' --job-id=' . escapeshellarg($jobId) . ' --mode=verify', $code, $err);
ok($code === 2, 'invalid mode exits with strict code');
ok(($invalid['error'] ?? null) === 'invalid_mode_for_command', 'invalid mode returns machine-readable error');

$missingJob = runCmd('php bin/migration-module verify --config=' . escapeshellarg($cfg), $code, $err);
ok($code === 2, 'missing job-id exits strict');
ok(($missingJob['error'] ?? null) === 'job_id_required', 'missing job-id is explicit');

@unlink($cfg);
echo "CLI contract checks passed\n";
