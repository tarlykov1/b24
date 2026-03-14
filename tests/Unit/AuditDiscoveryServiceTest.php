<?php

declare(strict_types=1);

use MigrationModule\Application\AuditDiscovery\AuditDiscoveryService;

if (is_file(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'MigrationModule\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/../../apps/migration-module/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
}

it('runs summary and returns risk level', function (): void {
    $dbPath = sys_get_temp_dir() . '/audit-fixture-' . uniqid() . '.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $sql = file_get_contents(__DIR__ . '/../Fixtures/audit_fixture.sql');
    $pdo->exec((string) $sql);

    putenv('BITRIX_DB_DSN=sqlite:' . $dbPath);
    putenv('BITRIX_UPLOAD_PATH=' . __DIR__ . '/../Fixtures');

    $service = new AuditDiscoveryService();
    $summary = $service->run('summary');
    assert(in_array($summary['risk_level'], ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], true));

    @unlink($dbPath);
});

it('generates report artifacts on audit run', function (): void {
    $dbPath = sys_get_temp_dir() . '/audit-fixture-' . uniqid() . '.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $sql = file_get_contents(__DIR__ . '/../Fixtures/audit_fixture.sql');
    $pdo->exec((string) $sql);

    putenv('BITRIX_DB_DSN=sqlite:' . $dbPath);
    putenv('BITRIX_UPLOAD_PATH=' . __DIR__ . '/../Fixtures');

    $service = new AuditDiscoveryService();
    $result = $service->run('run');

    assert(isset($result['summary']['risk_level']));
    assert(is_file('.audit/migration_profile.json'));
    assert(is_file('.audit/report.html'));

    @unlink($dbPath);
});


it('builds linkage audit metrics and strategy hints', function (): void {
    $dbPath = sys_get_temp_dir() . '/audit-fixture-' . uniqid() . '.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $sql = file_get_contents(__DIR__ . '/../Fixtures/audit_fixture.sql');
    $pdo->exec((string) $sql);

    putenv('BITRIX_DB_DSN=sqlite:' . $dbPath);
    putenv('BITRIX_UPLOAD_PATH=' . __DIR__ . '/../Fixtures');

    $service = new AuditDiscoveryService();
    $linkage = $service->run('linkage');

    assert(($linkage['tasks_with_attachments'] ?? 0) > 0);
    assert(($linkage['tasks_with_comment_attachments'] ?? 0) > 0);

    $full = $service->run('run', true);
    assert(($full['deep_mode'] ?? false) === true);
    assert(isset($full['strategy_hints']['file_migration_strategy']));

    @unlink($dbPath);
});

function it(string $title, callable $fn): void
{
    $fn();
    echo "[ok] {$title}\n";
}
