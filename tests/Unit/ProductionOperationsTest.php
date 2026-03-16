<?php

declare(strict_types=1);

use MigrationModule\Application\Production\BackupManager;
use MigrationModule\Application\Production\ConfigManager;
use MigrationModule\Application\Production\HardeningService;
use MigrationModule\Application\Production\ProductionLayoutManager;

require_once __DIR__ . '/../../tests/bootstrap_simulation.php';

it('creates production layout and supports config+backup flow', static function (): void {
    $root = sys_get_temp_dir() . '/bitrix_migration_prod_' . bin2hex(random_bytes(4));
    $layout = (new ProductionLayoutManager())->ensure($root);

    assert(is_dir($root . '/config'));
    assert(is_dir($root . '/runtime/logs'));
    assert(is_dir($root . '/storage/backups'));
    assert(count($layout['created']) > 0);

    $cfg = new ConfigManager();
    $cfg->set($root . '/config/migration.yaml', 'source.db_dsn', 'mysql:host=source;dbname=src');
    $cfg->set($root . '/config/migration.yaml', 'target.rest_webhook', 'https://target/rest/1/token');
    $cfg->set($root . '/config/migration.yaml', 'workers.worker_count', 2);

    $validation = $cfg->validate($root . '/config/migration.yaml');
    assert($validation['ok'] === true);

    file_put_contents($root . '/runtime/jobs.db', 'sqlite-bytes');
    $backup = (new BackupManager())->create($root, 'runtime');
    assert(($backup['ok'] ?? false) === true);
    assert(is_file((string) $backup['path']));

    $hardening = (new HardeningService())->check($root);
    assert(isset($hardening['checks']['php_version']));
});
