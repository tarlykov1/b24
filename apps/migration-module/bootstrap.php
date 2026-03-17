<?php

declare(strict_types=1);

if (!function_exists('migration_module_bootstrap')) {
    function migration_module_bootstrap(string $projectRoot): void
    {
        $vendorAutoload = rtrim($projectRoot, '/') . '/vendor/autoload.php';
        if (is_file($vendorAutoload)) {
            require_once $vendorAutoload;

            return;
        }

        spl_autoload_register(static function (string $class) use ($projectRoot): void {
            $prefix = 'MigrationModule\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $path = rtrim($projectRoot, '/') . '/apps/migration-module/src/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($path)) {
                require_once $path;
            }
        });
    }
}
