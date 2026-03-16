<?php

declare(strict_types=1);

function runSync(string $cmd): array
{
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    if ($code !== 0) {
        throw new RuntimeException("Command failed: {$cmd}\n" . implode("\n", $output));
    }

    $decoded = json_decode(implode("\n", $output), true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON: {$cmd}\n" . implode("\n", $output));
    }

    return $decoded;
}

@unlink(__DIR__ . '/../.prototype/test-sync.sqlite');
file_put_contents(__DIR__ . '/../migration.sync.config.yml', "batch_size: 2\nstorage:\n  path: .prototype/test-sync.sqlite\nsync:\n  mode: hybrid\n  direction: source_to_target\n  policy:\n    users: enabled\n    tasks: enabled\n");

$job = runSync('php bin/migration-module create-job --config=migration.sync.config.yml --mode=execute');
$jobId = (string) ($job['job_id'] ?? '');
if ($jobId === '') {
    throw new RuntimeException('create-job did not return job_id');
}

$start = runSync('php bin/migration-module migration sync:start ' . $jobId . ' --config=migration.sync.config.yml');
if (($start['service'] ?? '') !== 'started') {
    throw new RuntimeException('sync start failed');
}

$service = runSync('php bin/migration-module migration sync:service ' . $jobId . ' --config=migration.sync.config.yml');
if (!isset($service['sync_coordinator'])) {
    throw new RuntimeException('sync service heartbeat missing');
}

$verify = runSync('php bin/migration-module migration sync:verify ' . $jobId . ' --config=migration.sync.config.yml');
if (!array_key_exists('sync_health_score', $verify)) {
    throw new RuntimeException('sync verify missing health score');
}

$dr = runSync('php bin/migration-module migration dr:status ' . $jobId . ' --config=migration.sync.config.yml');
if (!array_key_exists('dr_readiness_score', $dr)) {
    throw new RuntimeException('dr status missing readiness score');
}

$stop = runSync('php bin/migration-module migration sync:stop ' . $jobId . ' --config=migration.sync.config.yml');
if (($stop['service'] ?? '') !== 'stopped') {
    throw new RuntimeException('sync stop failed');
}

unlink(__DIR__ . '/../migration.sync.config.yml');
echo "Continuous sync CLI checks passed\n";
