<?php

declare(strict_types=1);

namespace MigrationModule\Installation;

use MigrationModule\Infrastructure\Persistence\MySqlMigrationRunner;
use PDO;

final class InstallWizardService
{
    public function __construct(
        private readonly InstallConfigValidator $validator = new InstallConfigValidator(),
    ) {
    }

    /** @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function check(array $config): array
    {
        $validation = $this->validator->validate($config);
        return [
            'ok' => count($validation['blockers']) === 0,
            'status' => count($validation['blockers']) === 0 ? 'pass' : 'fail',
            'validation' => $validation,
        ];
    }

    /** @return array<string,mixed> */
    public function initDb(PDO $pdo, string $schemaPath): array
    {
        $runner = new MySqlMigrationRunner($pdo);
        return $runner->migrate($schemaPath);
    }

    /** @param array<string,mixed> $config
     * @return array<string,mixed> */
    public function generateConfig(array $config, string $outputPath): array
    {
        $payload = [
            'generated_at' => date(DATE_ATOM),
            'mysql' => $config['mysql'] ?? [],
            'platform' => $config['platform'] ?? [],
            'source' => $config['source'] ?? [],
            'target' => $config['target'] ?? [],
            'execution' => $config['execution'] ?? [],
            'policy' => $config['policy'] ?? [],
            'observability' => $config['observability'] ?? [],
            'security' => $config['security'] ?? [],
        ];

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        file_put_contents($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ['ok' => true, 'path' => $outputPath, 'redacted' => $this->redact($payload)];
    }

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function redact(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return $payload;
        }

        $json = preg_replace('/("password"\s*:\s*")[^"]+(")/i', '$1***$2', $json);
        $json = preg_replace('/("token"\s*:\s*")[^"]+(")/i', '$1***$2', $json);

        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? $decoded : $payload;
    }
}
