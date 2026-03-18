<?php

declare(strict_types=1);

namespace MigrationModule\Installation;

use MigrationModule\Support\DbConfig;
use PDO;
use PDOException;
use Throwable;

final class ConnectivityProbeService
{
    /** @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function probeMySql(array $config): array
    {
        $db = DbConfig::fromRuntimeSources($config);
        $host = (string) ($db['host'] ?? '');
        $port = (int) ($db['port'] ?? 3306);
        $name = (string) ($db['name'] ?? '');
        $user = (string) ($db['user'] ?? '');

        if ($host === '' || $name === '' || $user === '') {
            return $this->fail(
                'config',
                'mysql_config_missing',
                'MySQL host, name, and user are required.',
                ['host' => $host, 'port' => $port, 'database' => $name, 'user' => $user]
            );
        }

        if (!extension_loaded('pdo_mysql')) {
            return $this->fail('config', 'pdo_mysql_missing', 'PDO MySQL extension is required.');
        }

        $tcp = @fsockopen($host, $port, $errno, $errstr, 3.0);
        if ($tcp === false) {
            $class = ($errno === 110 || str_contains(strtolower((string) $errstr), 'timed out')) ? 'timeout' : 'network';
            return $this->fail($class, 'mysql_tcp_unreachable', 'Cannot reach MySQL TCP endpoint.', [
                'host' => $host,
                'port' => $port,
                'errno' => $errno,
                'detail' => (string) $errstr,
            ]);
        }
        fclose($tcp);

        try {
            $pdo = new PDO(DbConfig::dsn($db), (string) $db['user'], (string) $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $pdo->query('SELECT 1')->fetchColumn();

            $table = 'installer_probe_' . bin2hex(random_bytes(4));
            $pdo->exec("CREATE TABLE `{$table}` (id INT PRIMARY KEY, marker VARCHAR(16) NULL)");
            $pdo->exec("INSERT INTO `{$table}` (id, marker) VALUES (1, 'ok')");
            $pdo->exec("UPDATE `{$table}` SET marker='ok2' WHERE id=1");
            $pdo->exec("DROP TABLE `{$table}`");
        } catch (PDOException $e) {
            $classAndCode = $this->classifyPdoException($e->getMessage());
            return $this->fail($classAndCode['class'], $classAndCode['code'], 'MySQL probe failed.', [
                'detail' => $e->getMessage(),
                'host' => $host,
                'port' => $port,
                'database' => $name,
                'user' => $user,
            ]);
        } catch (Throwable $e) {
            return $this->fail('unknown', 'mysql_probe_failed', 'Unexpected MySQL probe error.', ['detail' => $e->getMessage()]);
        }

        return [
            'ok' => true,
            'status' => 'pass',
            'probe' => 'mysql',
            'checks' => [
                'tcp' => true,
                'auth' => true,
                'schema' => true,
                'write' => true,
            ],
            'checked_at' => date(DATE_ATOM),
        ];
    }

    /** @param array<string,mixed> $config
     * @return array<string,mixed>
     */
    public function probeBitrix(array $config, string $surface): array
    {
        $url = trim((string) ($config['url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));

        if ($url === '' || $token === '') {
            return $this->fail('config', 'bitrix_credentials_missing', 'Bitrix URL and token are required.', ['surface' => $surface]);
        }

        if (!extension_loaded('curl')) {
            return $this->fail('config', 'curl_missing', 'cURL extension is required for Bitrix connectivity checks.', ['surface' => $surface]);
        }

        $endpoint = rtrim($url, '/') . '/profile.json';
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return $this->fail('unknown', 'curl_init_failed', 'Unable to initialize cURL.', ['surface' => $surface]);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['auth' => $token]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $raw = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || !is_string($raw)) {
            $class = $this->classifyCurlError($curlErrNo, $curlErr);
            return $this->fail($class, 'bitrix_http_transport_failed', 'Bitrix endpoint not reachable.', [
                'surface' => $surface,
                'http_code' => $httpCode,
                'curl_errno' => $curlErrNo,
                'detail' => $curlErr,
            ]);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $this->fail('schema', 'bitrix_invalid_json', 'Bitrix probe returned invalid JSON.', [
                'surface' => $surface,
                'http_code' => $httpCode,
            ]);
        }

        if ($httpCode >= 500) {
            return $this->fail('network', 'bitrix_upstream_unavailable', 'Bitrix endpoint returned server error.', [
                'surface' => $surface,
                'http_code' => $httpCode,
            ]);
        }

        if ($httpCode === 401 || $httpCode === 403 || isset($decoded['error'])) {
            $errorCode = (string) ($decoded['error'] ?? 'unknown');
            $errorClass = $this->classifyBitrixError($errorCode);

            return $this->fail($errorClass, 'bitrix_auth_or_permission_failure', 'Bitrix rejected authentication or permissions.', [
                'surface' => $surface,
                'http_code' => $httpCode,
                'bitrix_error' => $errorCode,
                'bitrix_description' => (string) ($decoded['error_description'] ?? ''),
            ]);
        }

        if (($decoded['result'] ?? null) === null) {
            return $this->fail('schema', 'bitrix_result_missing', 'Bitrix probe completed without a result payload.', [
                'surface' => $surface,
                'http_code' => $httpCode,
            ]);
        }

        return [
            'ok' => true,
            'status' => 'pass',
            'probe' => 'bitrix',
            'surface' => $surface,
            'checks' => [
                'network' => true,
                'auth' => true,
                'schema' => true,
            ],
            'meta' => [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
            ],
            'checked_at' => date(DATE_ATOM),
        ];
    }

    /** @return array<string,string> */
    private function classifyPdoException(string $message): array
    {
        $lower = strtolower($message);

        return match (true) {
            str_contains($lower, 'access denied') => ['class' => 'auth', 'code' => 'mysql_auth_failure'],
            str_contains($lower, 'timed out'), str_contains($lower, 'timeout') => ['class' => 'timeout', 'code' => 'mysql_timeout'],
            str_contains($lower, 'unknown database') => ['class' => 'config', 'code' => 'mysql_database_not_found'],
            str_contains($lower, 'permission denied'), str_contains($lower, 'command denied') => ['class' => 'permission', 'code' => 'mysql_permission_failure'],
            str_contains($lower, 'syntax error'), str_contains($lower, 'column not found') => ['class' => 'schema', 'code' => 'mysql_schema_failure'],
            default => ['class' => 'network', 'code' => 'mysql_connection_failed'],
        };
    }

    private function classifyCurlError(int $errno, string $error): string
    {
        return match (true) {
            in_array($errno, [6, 7], true) => 'network',
            $errno === 28 => 'timeout',
            str_contains(strtolower($error), 'timed out') => 'timeout',
            default => 'network',
        };
    }

    private function classifyBitrixError(string $errorCode): string
    {
        $upper = strtoupper($errorCode);
        return match (true) {
            str_contains($upper, 'ACCESS_DENIED') => 'permission',
            str_contains($upper, 'INVALID_CREDENTIALS'), str_contains($upper, 'INVALID_TOKEN'), str_contains($upper, 'NO_AUTH_FOUND') => 'auth',
            default => 'auth',
        };
    }

    /** @param array<string,mixed> $details
     * @return array<string,mixed>
     */
    private function fail(string $errorClass, string $code, string $message, array $details = []): array
    {
        return [
            'ok' => false,
            'status' => 'fail',
            'probe' => str_starts_with($code, 'mysql_') || $code === 'pdo_mysql_missing' ? 'mysql' : 'bitrix',
            'error' => [
                'class' => $errorClass,
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'checked_at' => date(DATE_ATOM),
        ];
    }
}
