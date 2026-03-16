<?php

declare(strict_types=1);

namespace MigrationModule\Application\Production;

final class HardeningService
{
    /** @return array<string,mixed> */
    public function check(string $root): array
    {
        $checks = [
            'php_version' => ['ok' => PHP_VERSION_ID >= 80100, 'current' => PHP_VERSION, 'required' => '>=8.1'],
            'pdo_mysql_extension' => ['ok' => extension_loaded('pdo_mysql')],
            'zip_extension' => ['ok' => extension_loaded('zip')],
            'disk_space' => ['ok' => disk_free_space($root) > 536870912, 'bytes_free' => disk_free_space($root)],
            'runtime_writable' => ['ok' => is_writable($root) || is_writable(dirname($root))],
        ];

        $ok = array_reduce($checks, static fn (bool $carry, array $item): bool => $carry && (($item['ok'] ?? false) === true), true);
        return ['ok' => $ok, 'checks' => $checks];
    }
}
