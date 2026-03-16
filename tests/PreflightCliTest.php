<?php

declare(strict_types=1);

function runPreflight(string $cmd, ?int &$code = null): array
{
    $pipes = [];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__ . '/..');
    if (!is_resource($proc)) {
        throw new RuntimeException('Unable to start process');
    }
    $out = stream_get_contents($pipes[1]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    $decoded = json_decode($out, true);

    return is_array($decoded) ? $decoded : ['raw' => $out];
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$configPath = __DIR__ . '/../migration.preflight.test.yml';
file_put_contents($configPath, <<<YAML
source:
  upload_path: .
target:
  upload_path: .
workers:
  worker_count: 2
entity_policies: enabled
conflict_policies: enabled
mapping_rules: enabled
retry_rules: enabled
YAML);

$result = runPreflight('php bin/migration-module preflight --config=' . escapeshellarg($configPath), $code);
assertTrue($code === 1, 'preflight should exit with warning code for non-critical warnings');
assertTrue(isset($result['status']) && $result['status'] === 'warning', 'preflight status should be warning');
assertTrue(isset($result['checks']) && is_array($result['checks']), 'preflight result should include checks array');

$strict = runPreflight('php bin/migration-module preflight --strict --config=' . escapeshellarg($configPath), $strictCode);
assertTrue($strictCode === 2, 'strict mode should promote warning to blocked');
assertTrue(($strict['status'] ?? '') === 'blocked', 'strict preflight should be blocked');

@unlink($configPath);
echo "Preflight CLI checks passed\n";
