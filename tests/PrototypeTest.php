<?php

declare(strict_types=1);

use MigrationModule\Prototype\Adapter\StubSourceAdapter;
use MigrationModule\Prototype\Adapter\StubTargetAdapter;
use MigrationModule\Prototype\ConfigLoader;
use MigrationModule\Prototype\Policy\IdConflictResolutionPolicy;
use MigrationModule\Prototype\Policy\UserHandlingPolicy;
use MigrationModule\Prototype\PrototypeRuntime;
use MigrationModule\Prototype\Storage\SqliteStorage;

spl_autoload_register(static function (string $class): void {
    $prefix = 'MigrationModule\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../apps/migration-module/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

function ok(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
}

@unlink(__DIR__ . '/../.prototype/test.sqlite');
file_put_contents(__DIR__ . '/../migration.test.config.yml', "batch_size: 2\nstorage:\n  path: .prototype/test.sqlite\nuser_policy:\n  cutoff_date: 2024-01-01T00:00:00+00:00\n  inactive_strategy: skip_user\n");
$config = (new ConfigLoader())->load(__DIR__ . '/../migration.test.config.yml');
$runtime = new PrototypeRuntime(
    new SqliteStorage(__DIR__ . '/../.prototype/test.sqlite'),
    new StubSourceAdapter(),
    new StubTargetAdapter(),
    new IdConflictResolutionPolicy(),
    new UserHandlingPolicy(),
    $config,
);

$validated = $runtime->validate();
ok($validated['ok'] === true, 'config loading and validate');

$storage = new SqliteStorage(__DIR__ . '/../.prototype/test.sqlite');
$storage->initSchema();
$job = $storage->createJob('test');
$plan = $runtime->plan($job);
ok(($plan['summary']['create'] ?? 0) > 0, 'plan exists');

$dry = $runtime->dryRun($job);
ok($dry['mode'] === 'dry-run', 'dry-run flow');

$exec1 = $runtime->execute($job, false);
ok($exec1['status'] === 'paused', 'execute pauses on retryable');

$exec2 = $runtime->execute($job, true);
ok($exec2['status'] === 'completed', 'resume from checkpoint');

$verify = $runtime->verify($job);
ok(isset($verify['missing']), 'verification summary');

$plan2 = $runtime->plan($job);
ok(($plan2['summary']['skip'] ?? 0) >= 1, 'rerun delta skip');

$idPolicy = new IdConflictResolutionPolicy();
$resolution = $idPolicy->resolve(new StubTargetAdapter(), 'users', '1');
ok($resolution['conflict'] === true, 'id conflict resolution');

$userPolicy = new UserHandlingPolicy();
$decision = $userPolicy->apply(['id' => '1', 'active' => false, 'updated_at' => '2023-01-01T00:00:00+00:00'], $config['user_policy']);
ok($decision['decision'] === 'skip_user', 'user cutoff policy');

$pdo = new PDO('sqlite:' . __DIR__ . '/../.prototype/test.sqlite');
$count = (int) $pdo->query('SELECT COUNT(*) FROM entity_map')->fetchColumn();
ok($count > 0, 'schema/runtime consistency');

unlink(__DIR__ . '/../migration.test.config.yml');
echo "All prototype checks passed\n";
