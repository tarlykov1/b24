<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery;

use DateTimeImmutable;
use MigrationModule\Audit\ChangeVelocityAnalyzer;
use MigrationModule\Application\AuditDiscovery\Inspection\DatabaseInspector;
use MigrationModule\Application\AuditDiscovery\Inspection\FilesystemInspector;
use MigrationModule\Application\AuditDiscovery\Inspection\RestInspector;
use MigrationModule\Application\AuditDiscovery\Reporting\HtmlReportRenderer;
use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;
use PDO;
use Throwable;

final class AuditDiscoveryService
{
    public function __construct(
        private readonly DatabaseInspector $dbInspector = new DatabaseInspector(),
        private readonly FilesystemInspector $filesystemInspector = new FilesystemInspector(),
        private readonly RestInspector $restInspector = new RestInspector(),
        private readonly RiskEngine $riskEngine = new RiskEngine(),
        private readonly StrategyHintEngine $strategyHintEngine = new StrategyHintEngine(),
        private readonly HtmlReportRenderer $htmlReportRenderer = new HtmlReportRenderer(),
        private readonly ChangeVelocityAnalyzer $changeVelocityAnalyzer = new ChangeVelocityAnalyzer(),
    ) {
    }

    public function run(string $section = 'run', bool $deep = false): array
    {
        $pdo = $this->buildReadonlyPdo();
        $client = $this->buildRestClient();
        $uploadPath = (string) $this->env('BITRIX_UPLOAD_PATH', '/upload');

        $db = $this->dbInspector->inspect($pdo, $deep);
        $fs = $this->filesystemInspector->inspect($uploadPath);
        $rest = $this->restInspector->inspect($client);

        $portal = $this->portalProfile($db, $fs, $rest);
        $users = $this->usersAudit($db, $rest);
        $tasks = $this->tasksAudit($db, $rest);
        $files = $this->filesAudit($db, $fs);
        $crm = $this->crmAudit($db, $rest);
        $permissions = $this->permissionsAudit($db);
        $linkage = $this->linkageAudit($db);

        $profile = [
            'generated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'portal' => $portal,
            'users' => $users,
            'tasks' => $tasks,
            'files' => $files,
            'crm' => $crm,
            'permissions' => $permissions,
            'linkage' => $linkage,
        ];

        $summary = $this->riskEngine->analyze([
            'users' => $users,
            'tasks' => $tasks,
            'files' => $files,
            'permissions' => $permissions,
            'linkage' => $linkage,
        ]);
        $strategyHints = $this->strategyHintEngine->build($profile, $summary);

        $result = [
            'portal_profile' => $portal,
            'data_volumes' => [
                'users' => $users['total'],
                'tasks' => $tasks['total'],
                'files' => $files['total'],
                'storage_size_gb' => $files['total_size_gb'],
            ],
            'users' => $users,
            'tasks' => $tasks,
            'files' => $files,
            'crm' => $crm,
            'permissions' => $permissions,
            'linkage' => $linkage,
            'summary' => $summary,
            'strategy_hints' => $strategyHints,
            'readiness_score' => $this->readinessScore($summary),
            'sources' => ['db' => $db['available'] ?? false, 'fs' => $fs['available'] ?? false, 'rest' => $rest['available'] ?? false],
            'change_velocity' => $this->changeVelocityAnalyzer->analyze($pdo, $client, $uploadPath),
        ];

        if (in_array($section, ['run', 'report'], true)) {
            $this->persistOutputs($result);
        }

        return match ($section) {
            'portal' => $portal,
            'users' => $users,
            'tasks' => $tasks,
            'files' => $files,
            'crm' => $crm,
            'permissions' => $permissions,
            'linkage' => $linkage,
            'summary' => $summary,
            'report' => ['report' => '.audit/report.html', 'profile' => '.audit/migration_profile.json'],
            'velocity' => $this->changeVelocityAnalyzer->analyze($pdo, $client, $uploadPath),
            default => $result,
        };
    }

    private function portalProfile(array $db, array $fs, array $rest): array
    {
        return [
            'bitrix_version' => (string) $this->env('BITRIX_VERSION', 'unknown'),
            'modules' => $this->installedModules(),
            'enabled_modules' => $this->installedModules(),
            'database_size' => $this->formatBytes((int) ($db['db_size_bytes'] ?? 0)),
            'file_storage_size' => $this->formatBytes((int) ($fs['total_size_bytes'] ?? 0)),
            'users_total' => (int) (($db['counts']['b_user'] ?? 0) ?: ($rest['users_total'] ?? 0)),
            'users_active' => max(0, (int) (($db['counts']['b_user'] ?? 0) - ($db['users_inactive'] ?? 0))),
            'groups_projects_count' => (int) ($db['counts']['b_sonet_group'] ?? 0),
            'smart_processes_enabled' => count((array) ($rest['smart_processes'] ?? [])) > 0,
        ];
    }

    private function usersAudit(array $db, array $rest): array
    {
        $total = (int) (($db['counts']['b_user'] ?? 0) ?: ($rest['users_total'] ?? 0));
        $inactive = (int) ($db['users_inactive'] ?? 0);

        return [
            'total' => $total,
            'active' => max(0, $total - $inactive),
            'inactive' => $inactive,
            'external' => 0,
            'blocked' => (int) ($db['users_blocked'] ?? 0),
            'owning_tasks' => count((array) ($db['task_owner_distribution'] ?? [])),
            'owning_files' => count((array) ($db['files_by_user'] ?? [])),
            'owning_disks' => (int) ($db['counts']['b_disk_object'] ?? 0),
            'owning_projects_groups' => (int) ($db['counts']['b_sonet_user2group'] ?? 0),
            'without_email' => (int) ($db['users_without_email'] ?? 0),
            'without_login' => (int) ($db['users_without_login'] ?? 0),
            'without_activity' => $inactive,
        ];
    }

    private function tasksAudit(array $db, array $rest): array
    {
        $total = (int) (($db['counts']['b_tasks'] ?? 0) ?: ($rest['tasks_total'] ?? 0));

        return [
            'total' => $total,
            'open' => (int) ($db['tasks_open'] ?? 0),
            'closed' => (int) ($db['tasks_closed'] ?? 0),
            'with_files' => (int) ($db['tasks_with_attachments'] ?? 0),
            'with_comments' => (int) ($db['tasks_with_comments'] ?? 0),
            'without_responsible' => (int) ($db['tasks_without_responsible'] ?? 0),
            'referencing_deleted_users' => 0,
            'by_project_group' => (array) ($db['tasks_by_group'] ?? []),
            'comments_total' => (int) ($db['tasks_comments_total'] ?? 0),
            'attachments_total' => (int) ($db['tasks_with_attachments'] ?? 0),
            'distribution_per_user' => (array) ($db['task_owner_distribution'] ?? []),
        ];
    }

    private function filesAudit(array $db, array $fs): array
    {
        $bytes = (int) (($fs['total_size_bytes'] ?? 0) ?: ($db['files_total_size'] ?? 0));

        return [
            'total' => (int) (($fs['total_files'] ?? 0) ?: ($db['counts']['b_file'] ?? 0)),
            'total_size_bytes' => $bytes,
            'total_size_gb' => round($bytes / 1024 / 1024 / 1024, 2),
            'attached_to_tasks' => (int) ($db['tasks_with_attachments'] ?? 0),
            'attached_to_smart_processes' => 0,
            'in_user_disks' => (int) ($db['counts']['b_disk_object'] ?? 0),
            'in_group_disks' => (int) ($db['counts']['b_sonet_group'] ?? 0),
            'orphan_files' => (int) ($db['missing_file_links'] ?? 0),
            'missing_physical_files' => (int) ($fs['missing_physical_files'] ?? 0),
            'duplicate_files_by_checksum' => (int) ($fs['duplicate_files_by_checksum'] ?? 0),
            'size_distribution' => (array) ($fs['size_buckets'] ?? ['lt_10mb' => 0, 'mb_10_100' => 0, 'mb_100_1gb' => 0, 'gt_1gb' => 0]),
            'mime_distribution' => (array) ($fs['mime_distribution'] ?? []),
        ];
    }


    private function linkageAudit(array $db): array
    {
        $linkage = (array) ($db['linkage'] ?? []);

        return [
            'tasks_with_attachments' => (int) ($linkage['tasks_with_attachments'] ?? 0),
            'tasks_with_comment_attachments' => (int) ($linkage['tasks_with_comment_attachments'] ?? 0),
            'files_multi_linked' => (int) ($linkage['files_multi_linked'] ?? 0),
            'orphan_attachment_references' => (int) ($linkage['orphan_attachment_references'] ?? 0),
            'disk_objects_without_attached_context' => (int) ($linkage['disk_objects_without_attached_context'] ?? 0),
            'average_attachments_per_task' => (float) ($linkage['average_attachments_per_task'] ?? 0.0),
            'attachment_type_distribution' => (array) ($linkage['attachment_type_distribution'] ?? []),
            'attachments_per_task_top' => (array) ($linkage['attachments_per_task_top'] ?? []),
            'raw' => $linkage,
        ];
    }

    private function crmAudit(array $db, array $rest): array
    {
        return [
            'pipelines' => [],
            'deal_stages' => [],
            'custom_fields' => (int) ($db['crm_custom_fields'] ?? 0),
            'field_types' => [],
            'mandatory_fields' => [],
            'enum_values' => [],
            'smart_processes' => (array) ($rest['smart_processes'] ?? []),
            'records_per_smart_process' => [],
            'custom_field_fill_rate' => [],
            'unused_fields' => [],
        ];
    }

    private function permissionsAudit(array $db): array
    {
        $aclInvalidEntries = (int) ($db['acl_invalid_user_entries'] ?? 0) + (int) ($db['acl_invalid_group_entries'] ?? 0);
        $filesInactive = (int) ($db['files_owned_by_inactive_users'] ?? 0);
        $tasksInactive = (int) ($db['tasks_owned_by_inactive_users'] ?? 0);

        return [
            'groups' => (int) ($db['counts']['b_sonet_group'] ?? 0),
            'projects' => (int) ($db['counts']['b_sonet_group'] ?? 0),
            'group_disks' => (int) ($db['counts']['b_sonet_group'] ?? 0),
            'user_disks' => (int) ($db['counts']['b_disk_object'] ?? 0),
            'acl_anomalies' => $aclInvalidEntries,
            'disk_acl_invalid_entries' => $aclInvalidEntries,
            'acl_invalid_user_entries' => (int) ($db['acl_invalid_user_entries'] ?? 0),
            'acl_invalid_group_entries' => (int) ($db['acl_invalid_group_entries'] ?? 0),
            'broken_acl_inheritance' => (int) ($db['broken_acl_inheritance'] ?? 0),
            'inherited_acl_chains' => (int) ($db['inherited_acl_chains'] ?? 0),
            'files_owned_by_inactive_users' => $filesInactive,
            'tasks_owned_by_inactive_users' => $tasksInactive,
            'inactive_owners' => $filesInactive + $tasksInactive,
        ];
    }

    private function ownershipAudit(array $db, array $users, array $tasks, array $files): array
    {
        return [
            'entities' => ['tasks', 'task_comments', 'files', 'disk_objects', 'smart_process_records', 'crm_entities'],
            'fields' => ['author', 'owner', 'responsible', 'participants', 'watchers', 'disk_owner', 'parent_disk', 'attached_entity'],
            'missing_owners' => [
                'tasks_without_responsible' => (int) ($db['tasks_without_responsible'] ?? 0),
                'files_without_valid_owner' => (int) ($db['files_without_valid_owner'] ?? 0),
                'comments_with_missing_author' => (int) ($db['task_comments_missing_author'] ?? 0),
            ],
            'inactive_owners' => [
                'files_owned_by_inactive_users' => (int) ($db['files_owned_by_inactive_users'] ?? 0),
                'tasks_owned_by_inactive_users' => (int) ($db['tasks_owned_by_inactive_users'] ?? 0),
                'disk_folders_owned_by_inactive_users' => (int) ($db['disk_folders_owned_by_inactive_users'] ?? 0),
            ],
            'scope_risks' => [
                'users_policy' => 'active_only',
                'ownership_risk' => ((int) ($db['files_owned_by_inactive_users'] ?? 0)) > 0 || ((int) ($db['tasks_owned_by_inactive_users'] ?? 0)) > 0,
            ],
            'acl_graph' => [
                'invalid_entries' => (int) ($db['acl_invalid_user_entries'] ?? 0) + (int) ($db['acl_invalid_group_entries'] ?? 0),
                'missing_users' => (int) ($db['acl_invalid_user_entries'] ?? 0),
                'deleted_groups' => (int) ($db['acl_invalid_group_entries'] ?? 0),
                'inherited_acl_chains' => (int) ($db['inherited_acl_chains'] ?? 0),
                'broken_acl_inheritance' => (int) ($db['broken_acl_inheritance'] ?? 0),
            ],
            'disk_structure' => [
                'user_disks' => (int) ($db['counts']['b_disk_object'] ?? 0),
                'group_project_disks' => (int) ($db['counts']['b_sonet_group'] ?? 0),
                'task_attachments' => (int) ($db['tasks_with_attachments'] ?? 0),
                'smart_process_attachments' => 0,
                'files_per_storage' => (array) ($db['disk_files_per_storage'] ?? []),
                'folders_per_storage' => (array) ($db['disk_folders_per_storage'] ?? []),
                'ownership_distribution' => (array) ($db['file_ownership_by_user'] ?? []),
            ],
            'orphans' => [
                'files_without_parent_disk_object' => (int) ($db['files_without_parent_disk_object'] ?? 0),
                'disk_objects_without_physical_file' => (int) ($db['disk_objects_without_physical_file'] ?? 0),
                'files_attached_to_missing_entities' => (int) ($db['files_attached_to_missing_entities'] ?? 0),
                'tasks_referencing_missing_files' => (int) ($db['tasks_referencing_missing_files'] ?? 0),
                'files_referencing_missing_tasks' => (int) ($db['tasks_referencing_missing_files'] ?? 0),
            ],
            'metrics' => [
                'files_owned_by_inactive_users' => (int) ($db['files_owned_by_inactive_users'] ?? 0),
                'tasks_owned_by_inactive_users' => (int) ($db['tasks_owned_by_inactive_users'] ?? 0),
                'disk_acl_invalid_entries' => (int) ($db['acl_invalid_user_entries'] ?? 0) + (int) ($db['acl_invalid_group_entries'] ?? 0),
                'files_without_valid_owner' => (int) ($db['files_without_valid_owner'] ?? 0),
                'orphan_files' => (int) ($files['orphan_files'] ?? 0),
            ],
            'charts' => [
                'ownership_distribution' => (array) ($db['file_ownership_by_user'] ?? []),
                'file_ownership_by_user' => (array) ($db['file_ownership_by_user'] ?? []),
                'tasks_by_responsible_user' => (array) ($db['tasks_by_responsible_user'] ?? []),
            ],
            'totals' => [
                'users' => (int) ($users['total'] ?? 0),
                'tasks' => (int) ($tasks['total'] ?? 0),
                'files' => (int) ($files['total'] ?? 0),
            ],
        ];
    }

    private function persistOutputs(array $result): void
    {
        if (!is_dir('.audit')) {
            mkdir('.audit', 0775, true);
        }

        $profile = [
            'users' => [
                'total' => $result['users']['total'] ?? 0,
                'active' => $result['users']['active'] ?? 0,
            ],
            'tasks' => [
                'total' => $result['tasks']['total'] ?? 0,
                'with_files' => $result['tasks']['with_files'] ?? 0,
            ],
            'files' => [
                'total_size_gb' => $result['files']['total_size_gb'] ?? 0,
            ],
            'linkage' => [
                'tasks_with_attachments' => $result['linkage']['tasks_with_attachments'] ?? 0,
                'tasks_with_comment_attachments' => $result['linkage']['tasks_with_comment_attachments'] ?? 0,
                'multi_linked_files' => $result['linkage']['files_multi_linked'] ?? 0,
                'orphan_attachment_references' => $result['linkage']['orphan_attachment_references'] ?? 0,
                'recommended_attachment_strategy' => ((bool) ($result['strategy_hints']['file_migration_strategy']['metadata_first'] ?? false)) ? 'metadata_first_then_rebind' : 'inline_attachment_copy',
            ],
            'migration_strategy' => [
                'files_separate_pipeline' => (($result['strategy_hints']['files_strategy'] ?? '') === 'separate_bulk_transfer'),
            ],
            'raw' => $result,
        ];

        file_put_contents('.audit/migration_profile.json', json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents('.audit/report.html', $this->htmlReportRenderer->render($result));
    }

    private function buildReadonlyPdo(): ?PDO
    {
        $host = (string) $this->env('DB_HOST', '127.0.0.1');
        $port = (int) $this->env('DB_PORT', '3306');
        $db = (string) $this->env('DB_NAME', '');
        $user = (string) $this->env('DB_USER', '');
        $password = (string) $this->env('DB_PASSWORD', '');
        $charset = (string) $this->env('DB_CHARSET', 'utf8mb4');
        if ($db === '' || $user === '') {
            return null;
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $db, $charset);

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('SET SESSION TRANSACTION READ ONLY');

            return $pdo;
        } catch (Throwable) {
            return null;
        }
    }

    private function buildRestClient(): ?BitrixRestClient
    {
        $url = (string) $this->env('BITRIX_WEBHOOK_URL', '');
        $token = (string) $this->env('BITRIX_WEBHOOK_TOKEN', '');

        return ($url !== '' && $token !== '') ? new BitrixRestClient($url, $token) : null;
    }

    private function installedModules(): array
    {
        $modules = (string) $this->env('BITRIX_MODULES', 'crm,tasks,disk,intranet');

        return array_values(array_filter(array_map('trim', explode(',', $modules))));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = (int) floor(log($bytes, 1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    private function env(string $name, string $default): string
    {
        return isset($_ENV[$name]) && $_ENV[$name] !== '' ? (string) $_ENV[$name] : ((string) (getenv($name) ?: $default));
    }

    private function readinessScore(array $summary): int
    {
        $risk = (string) ($summary['risk_level'] ?? 'LOW');

        return match ($risk) {
            'LOW' => 90,
            'MEDIUM' => 70,
            'HIGH' => 45,
            default => 20,
        };
    }
}
