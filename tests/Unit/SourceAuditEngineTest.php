<?php

declare(strict_types=1);

use MigrationModule\Application\AuditDiscovery\SourceAuditEngine;

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

it('generates source audit report artifacts', function (): void {
    $dbPath = sys_get_temp_dir() . '/source-audit-fixture-' . uniqid() . '.sqlite';
    $pdo = new PDO('sqlite:' . $dbPath);
    $sql = file_get_contents(__DIR__ . '/../Fixtures/audit_fixture.sql');
    $pdo->exec((string) $sql);

    putenv('BITRIX_DB_DSN=sqlite:' . $dbPath);
    putenv('BITRIX_UPLOAD_PATH=' . __DIR__ . '/../Fixtures');

    $result = (new SourceAuditEngine())->run();

    assert(isset($result['migration_complexity_score']));
    assert(isset($result['estimated_runtime_hours']));
    assert(is_file('.audit/source_audit_report.json'));
    assert(is_file('.audit/source_migration_report.md'));

    @unlink($dbPath);
});

function it(string $title, callable $fn): void
{
    $fn();
    echo "[ok] {$title}\n";
}
