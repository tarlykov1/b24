<?php

declare(strict_types=1);

function runCli(string $command, ?int &$exitCode = null, ?string &$stderr = null): array
{
    $proc = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__ . '/..'
    );

    if (!is_resource($proc)) {
        throw new RuntimeException('Cannot start command: ' . $command);
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

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

$configPath = __DIR__ . '/../migration.mysql-failure.test.yml';
file_put_contents($configPath, <<<YAML
storage:
  host: 127.0.0.1
  port: 1
  name: offline_acceptance
  user: offline_user
  password: offline_pass
YAML);

$commands = [
    'create-job' => 'php bin/migration-module create-job --config=' . escapeshellarg($configPath),
    'status' => 'php bin/migration-module status --config=' . escapeshellarg($configPath) . ' --job-id=job_smoke',
    'report' => 'php bin/migration-module report --config=' . escapeshellarg($configPath) . ' --job-id=job_smoke',
    'validate' => 'php bin/migration-module validate --config=' . escapeshellarg($configPath),
    'dry-run' => 'php bin/migration-module dry-run --config=' . escapeshellarg($configPath) . ' --job-id=job_smoke',
    'execute' => 'php bin/migration-module execute --config=' . escapeshellarg($configPath) . ' --job-id=job_smoke',
    'resume' => 'php bin/migration-module resume --config=' . escapeshellarg($configPath) . ' --job-id=job_smoke',
    'pause' => 'php bin/migration-module pause --config=' . escapeshellarg($configPath) . ' --job-id=job_smoke',
    'verify' => 'php bin/migration-module verify --config=' . escapeshellarg($configPath) . ' --job-id=job_smoke',
];

foreach ($commands as $name => $cmd) {
    $result = runCli($cmd, $code, $stderr);

    assertTrue($code === 2, $name . ' must fail with deterministic non-fatal exit code 2');
    assertTrue(isset($result['ok']) && $result['ok'] === false, $name . ' should return structured failure payload');
    assertTrue(($result['command'] ?? null) === $name, $name . ' should preserve command in response');
    assertTrue(isset($result['error']['error_code']), $name . ' should include classified error code');
    assertTrue(in_array($result['error']['error_code'], ['mysql_connection_refused', 'mysql_connection_or_permission_failed'], true), $name . ' should classify MySQL bootstrap failure');
    assertTrue(!str_contains(strtolower((string) $stderr), 'fatal error'), $name . ' should not emit fatal stack trace as contract');
}

@unlink($configPath);

echo "MySQL bootstrap failure CLI contract test passed\n";
