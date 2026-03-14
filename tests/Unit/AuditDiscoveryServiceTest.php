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


it('runs ownership audit and returns acl/orphan metrics', function (): void {
    $dbPath = sys_get_temp_dir() . '/audit-fixture-' . uniqid() . '.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $sql = file_get_contents(__DIR__ . '/../Fixtures/audit_fixture.sql');
    $pdo->exec((string) $sql);

    putenv('BITRIX_DB_DSN=sqlite:' . $dbPath);
    putenv('BITRIX_UPLOAD_PATH=' . __DIR__ . '/../Fixtures');

    $service = new AuditDiscoveryService();
    $ownership = $service->run('ownership');

    assert(($ownership['metrics']['files_owned_by_inactive_users'] ?? 0) > 0);
    assert(($ownership['metrics']['disk_acl_invalid_entries'] ?? 0) > 0);
    assert(($ownership['orphans']['tasks_referencing_missing_files'] ?? 0) > 0);

    @unlink($dbPath);
});

function it(string $title, callable $fn): void
{
    $fn();
    echo "[ok] {$title}\n";
}
