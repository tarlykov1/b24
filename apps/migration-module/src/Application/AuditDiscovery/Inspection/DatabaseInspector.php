<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery\Inspection;

use PDO;
use Throwable;

final class DatabaseInspector
{
    public function inspect(?PDO $pdo): array
    {
        if ($pdo === null) {
            return ['available' => false, 'reason' => 'pdo_unavailable'];
        }

        $tables = [
            'b_user', 'b_tasks', 'b_tasks_member', 'b_tasks_comment',
            'b_crm_deal', 'b_crm_contact', 'b_crm_company', 'b_crm_lead',
            'b_file', 'b_disk_object', 'b_disk_attached_object', 'b_sonet_group', 'b_sonet_user2group',
        ];

        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = $this->countTable($pdo, $table);
        }

        return [
            'available' => true,
            'counts' => $counts,
            'users_without_email' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_user WHERE (EMAIL IS NULL OR EMAIL='')"),
            'users_without_login' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_user WHERE (LOGIN IS NULL OR LOGIN='')"),
            'users_inactive' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_user WHERE (ACTIVE='N' OR ACTIVE=0)"),
            'users_blocked' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_user WHERE (BLOCKED='Y' OR BLOCKED=1)"),
            'tasks_open' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_tasks WHERE (STATUS IS NULL OR STATUS NOT IN (5,6,7))"),
            'tasks_closed' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_tasks WHERE STATUS IN (5,6,7)"),
            'tasks_without_responsible' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_tasks WHERE RESPONSIBLE_ID IS NULL OR RESPONSIBLE_ID = 0"),
            'tasks_with_comments' => $this->scalar($pdo, "SELECT COUNT(DISTINCT TASK_ID) FROM b_tasks_comment"),
            'tasks_comments_total' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_tasks_comment"),
            'tasks_with_attachments' => $this->scalar($pdo, "SELECT COUNT(DISTINCT ENTITY_ID) FROM b_disk_attached_object WHERE MODULE_ID='tasks'"),
            'crm_custom_fields' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_user_field WHERE ENTITY_ID LIKE 'CRM_%'"),
            'orphan_disk_links' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_attached_object a LEFT JOIN b_disk_object o ON o.ID = a.OBJECT_ID WHERE o.ID IS NULL"),
            'missing_file_links' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_file f LEFT JOIN b_disk_object o ON o.FILE_ID = f.ID WHERE o.ID IS NULL"),
            'files_total_size' => $this->scalar($pdo, 'SELECT COALESCE(SUM(FILE_SIZE),0) FROM b_file'),
            'files_by_user' => $this->pairCounts($pdo, 'SELECT CREATED_BY as owner_id, COUNT(*) as cnt FROM b_file GROUP BY CREATED_BY ORDER BY cnt DESC LIMIT 30'),
            'tasks_by_group' => $this->pairCounts($pdo, 'SELECT GROUP_ID as owner_id, COUNT(*) as cnt FROM b_tasks GROUP BY GROUP_ID ORDER BY cnt DESC LIMIT 30'),
            'task_owner_distribution' => $this->pairCounts($pdo, 'SELECT RESPONSIBLE_ID as owner_id, COUNT(*) as cnt FROM b_tasks GROUP BY RESPONSIBLE_ID ORDER BY cnt DESC LIMIT 30'),
            'db_size_bytes' => $this->estimateDbSize($pdo),
        ];
    }

    private function countTable(PDO $pdo, string $table): int
    {
        return $this->scalar($pdo, sprintf('SELECT COUNT(*) FROM %s', $table));
    }

    private function scalar(PDO $pdo, string $sql): int
    {
        try {
            $stmt = $pdo->query($sql);
            if ($stmt === false) {
                return 0;
            }

            return (int) ($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private function pairCounts(PDO $pdo, string $sql): array
    {
        try {
            $stmt = $pdo->query($sql);
            if ($stmt === false) {
                return [];
            }

            $items = [];
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                $items[] = [
                    'owner_id' => (string) ($row['owner_id'] ?? '0'),
                    'count' => (int) ($row['cnt'] ?? 0),
                ];
            }

            return $items;
        } catch (Throwable) {
            return [];
        }
    }

    private function estimateDbSize(PDO $pdo): int
    {
        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $pageSize = $this->scalar($pdo, 'PRAGMA page_size');
                $pageCount = $this->scalar($pdo, 'PRAGMA page_count');

                return $pageSize * $pageCount;
            }

            if ($driver === 'mysql') {
                return $this->scalar($pdo, 'SELECT COALESCE(SUM(data_length + index_length),0) FROM information_schema.tables WHERE table_schema = DATABASE()');
            }
        } catch (Throwable) {
        }

        return 0;
    }
}
