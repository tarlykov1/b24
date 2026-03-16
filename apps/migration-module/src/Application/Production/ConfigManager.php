<?php

declare(strict_types=1);

namespace MigrationModule\Application\Production;

use MigrationModule\Infrastructure\Config\SimpleYaml;

final class ConfigManager
{
    public function __construct(private readonly SimpleYaml $yaml = new SimpleYaml())
    {
    }

    /** @return array<string,mixed> */
    public function get(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        return $this->yaml->parse((string) file_get_contents($path));
    }

    public function set(string $path, string $dotKey, mixed $value): void
    {
        $config = $this->get($path);
        $parts = explode('.', $dotKey);
        $ref =& $config;
        foreach ($parts as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref =& $ref[$part];
        }
        $ref = $value;
        $this->write($path, $config);
    }

    /** @param array<string,mixed> $config */
    public function write(string $path, array $config): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0750, true);
        }
        file_put_contents($path, $this->yaml->dump($config) . "\n");
    }

    /** @return array{ok:bool,errors:array<int,string>} */
    public function validate(string $path): array
    {
        $config = $this->get($path);
        $errors = [];
        if (($config['source']['db_dsn'] ?? '') === '') {
            $errors[] = 'source.db_dsn is required';
        }
        if (($config['target']['rest_webhook'] ?? '') === '') {
            $errors[] = 'target.rest_webhook is required';
        }
        if ((int) ($config['workers']['worker_count'] ?? 0) <= 0) {
            $errors[] = 'workers.worker_count must be > 0';
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }
}
