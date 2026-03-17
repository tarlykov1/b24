<?php

declare(strict_types=1);

namespace MigrationModule\Application\Readiness;

use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;
use MigrationModule\Support\DbConfig;
use PDO;
use PDOException;
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
        $dbConfig = DbConfig::fromRuntimeSources($dbConfig);
        $host = (string) $dbConfig['host'];
        $port = (int) $dbConfig['port'];
        $name = (string) $dbConfig['name'];
        $user = (string) $dbConfig['user'];
        $password = (string) $dbConfig['password'];

        $checks = [
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mysql_tcp_reachable' => false,
            'mysql_select_1' => false,
            'mysql_schema_permissions' => false,
        ];

        $errors = [];

        if ($checks['pdo_mysql']) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 3.0);
            if ($socket !== false) {
                $checks['mysql_tcp_reachable'] = true;
                fclose($socket);
            } else {
                $errors[] = ['code' => 'mysql_dns_or_network_failure', 'host' => $host, 'port' => $port, 'message' => (string) $errstr];
            }

            if ($name !== '' && $user !== '') {
                try {
                    $pdo = new PDO(DbConfig::dsn($dbConfig), $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]);
                    $pdo->query('SELECT 1')->fetchColumn();
                    $checks['mysql_select_1'] = true;

                    $probe = 'install_probe_' . bin2hex(random_bytes(4));
                    $pdo->exec("CREATE TABLE {$probe} (id INT PRIMARY KEY)");
                    $pdo->exec("ALTER TABLE {$probe} ADD COLUMN marker VARCHAR(16) NULL");
                    $pdo->exec("CREATE INDEX idx_marker ON {$probe}(marker)");
                    $pdo->exec("DROP TABLE {$probe}");
                    $checks['mysql_schema_permissions'] = true;
                } catch (PDOException $e) {
                    $errors[] = ['code' => $this->classifyPdoFailure($e), 'message' => $e->getMessage()];
                } catch (Throwable $e) {
                    $errors[] = ['code' => 'mysql_connection_or_permission_failed', 'message' => $e->getMessage()];
                }
            } else {
                $errors[] = ['code' => 'mysql_credentials_missing', 'message' => 'DB_NAME and DB_USER are required'];
            }
        } else {
            $errors[] = ['code' => 'pdo_mysql_missing', 'message' => 'PDO MySQL extension is required'];
        }

        $ok = !in_array(false, $checks, true) && $errors === [];

        return [
            'ok' => $ok,
            'status' => $ok ? 'pass' : 'fail',
            'code' => $ok ? 'system_check_passed' : 'system_check_failed',
            'checks' => $checks,
            'errors' => $errors,
            'checked_at' => date(DATE_ATOM),
        ];
    }

    private function classifyPdoFailure(PDOException $e): string
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, 'access denied') => 'mysql_auth_failure',
            str_contains($message, 'timeout') => 'mysql_timeout',
            str_contains($message, 'unknown database') => 'mysql_database_not_found',
            str_contains($message, 'permission denied') => 'mysql_permission_failure',
            default => 'mysql_connection_or_permission_failed',
        };
    }
}
