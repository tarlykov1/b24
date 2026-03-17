<?php

declare(strict_types=1);

namespace MigrationModule\Application\Readiness;

use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;
use PDO;
use Throwable;

final class SystemCheckService
{
    public function __construct(private readonly ?BitrixRestClient $client = null)
    {
    }

    /** @param array<string,mixed> $dbConfig
     * @return array<string,mixed>
     */
    public function check(array $dbConfig = []): array
    {
        $host = (string) ($dbConfig['host'] ?? ($_ENV['DB_HOST'] ?? '127.0.0.1'));
        $port = (int) ($dbConfig['port'] ?? ($_ENV['DB_PORT'] ?? 3306));
        $name = (string) ($dbConfig['name'] ?? ($_ENV['DB_NAME'] ?? ''));
        $user = (string) ($dbConfig['user'] ?? ($_ENV['DB_USER'] ?? ''));
        $password = (string) ($dbConfig['password'] ?? ($_ENV['DB_PASSWORD'] ?? ''));
        $charset = (string) ($dbConfig['charset'] ?? ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));

        $checks = [
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mysql_tcp_reachable' => false,
            'mysql_select_1' => false,
            'mysql_schema_permissions' => false,
        ];

        $errors = [];

        if ($checks['pdo_mysql']) {
            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 3.0);
            if ($socket !== false) {
                $checks['mysql_tcp_reachable'] = true;
                fclose($socket);
            } else {
                $errors[] = ['code' => 'mysql_tcp_unreachable', 'host' => $host, 'port' => $port, 'message' => (string) $errstr];
            }

            if ($name !== '' && $user !== '') {
                try {
                    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
                    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->query('SELECT 1')->fetchColumn();
                    $checks['mysql_select_1'] = true;

                    $probe = 'install_probe_' . bin2hex(random_bytes(4));
                    $pdo->exec("CREATE TABLE {$probe} (id INT PRIMARY KEY)");
                    $pdo->exec("ALTER TABLE {$probe} ADD COLUMN marker VARCHAR(16) NULL");
                    $pdo->exec("CREATE INDEX idx_marker ON {$probe}(marker)");
                    $pdo->exec("DROP TABLE {$probe}");
                    $checks['mysql_schema_permissions'] = true;
                } catch (Throwable $e) {
                    $errors[] = ['code' => 'mysql_connection_or_permission_failed', 'message' => $e->getMessage()];
                }
            } else {
                $errors[] = ['code' => 'mysql_credentials_missing', 'message' => 'DB_NAME and DB_USER are required'];
            }
        } else {
            $errors[] = ['code' => 'pdo_mysql_missing', 'message' => 'PDO MySQL extension is required'];
        }

        return [
            'ok' => !in_array(false, $checks, true),
            'checks' => $checks,
            'errors' => $errors,
            'checked_at' => date(DATE_ATOM),
        ];
    }
}
