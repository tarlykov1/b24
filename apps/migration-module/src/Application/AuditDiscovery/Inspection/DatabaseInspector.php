<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery\Inspection;

use PDO;
use Throwable;

final class DatabaseInspector
{
    public function inspect(?PDO $pdo, bool $deep = false): array
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
            'task_comments_missing_author' => $this->scalar($pdo, 'SELECT COUNT(*) FROM b_tasks_comment WHERE AUTHOR_ID IS NULL OR AUTHOR_ID = 0'),
            'tasks_owned_by_inactive_users' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_tasks t INNER JOIN b_user u ON u.ID = t.RESPONSIBLE_ID WHERE (u.ACTIVE='N' OR u.ACTIVE=0)"),
            'crm_custom_fields' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_user_field WHERE ENTITY_ID LIKE 'CRM_%'"),
            'orphan_disk_links' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_attached_object a LEFT JOIN b_disk_object o ON o.ID = a.OBJECT_ID WHERE o.ID IS NULL"),
            'missing_file_links' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_file f LEFT JOIN b_disk_object o ON o.FILE_ID = f.ID WHERE o.ID IS NULL"),
            'files_owned_by_inactive_users' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_file f INNER JOIN b_user u ON u.ID = f.CREATED_BY WHERE (u.ACTIVE='N' OR u.ACTIVE=0)"),
            'files_without_valid_owner' => $this->scalar($pdo, 'SELECT COUNT(*) FROM b_file WHERE CREATED_BY IS NULL OR CREATED_BY = 0'),
            'disk_folders_owned_by_inactive_users' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_object o INNER JOIN b_user u ON u.ID = o.CREATED_BY WHERE (u.ACTIVE='N' OR u.ACTIVE=0)"),
            'acl_invalid_user_entries' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_right r LEFT JOIN b_user u ON u.ID = r.ACCESS_CODE WHERE r.ACCESS_CODE LIKE 'U%' AND u.ID IS NULL"),
            'acl_invalid_group_entries' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_right r LEFT JOIN b_sonet_group g ON g.ID = r.ACCESS_CODE WHERE r.ACCESS_CODE LIKE 'SG%' AND g.ID IS NULL"),
            'broken_acl_inheritance' => $this->scalar($pdo, 'SELECT COUNT(*) FROM b_disk_object child LEFT JOIN b_disk_object parent ON parent.ID = child.PARENT_ID WHERE child.PARENT_ID IS NOT NULL AND parent.ID IS NULL'),
            'inherited_acl_chains' => $this->scalar($pdo, 'SELECT COUNT(*) FROM b_disk_object WHERE PARENT_ID IS NOT NULL'),
            'files_without_parent_disk_object' => $this->scalar($pdo, 'SELECT COUNT(*) FROM b_disk_object WHERE PARENT_ID IS NULL AND TYPE = 3'),
            'disk_objects_without_physical_file' => $this->scalar($pdo, 'SELECT COUNT(*) FROM b_disk_object o LEFT JOIN b_file f ON f.ID = o.FILE_ID WHERE o.FILE_ID IS NOT NULL AND o.FILE_ID > 0 AND f.ID IS NULL'),
            'files_attached_to_missing_entities' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_attached_object a LEFT JOIN b_tasks t ON (a.MODULE_ID='tasks' AND t.ID=a.ENTITY_ID) WHERE a.MODULE_ID='tasks' AND t.ID IS NULL"),
            'tasks_referencing_missing_files' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_attached_object a LEFT JOIN b_disk_object o ON o.ID = a.OBJECT_ID LEFT JOIN b_file f ON f.ID = o.FILE_ID WHERE a.MODULE_ID='tasks' AND (o.ID IS NULL OR f.ID IS NULL)"),
            'files_total_size' => $this->scalar($pdo, 'SELECT COALESCE(SUM(FILE_SIZE),0) FROM b_file'),
            'files_by_user' => $this->pairCounts($pdo, 'SELECT CREATED_BY as owner_id, COUNT(*) as cnt FROM b_file GROUP BY CREATED_BY ORDER BY cnt DESC LIMIT 30'),
            'file_ownership_by_user' => $this->pairCounts($pdo, 'SELECT CREATED_BY as owner_id, COUNT(*) as cnt FROM b_file GROUP BY CREATED_BY ORDER BY cnt DESC LIMIT 50'),
            'tasks_by_group' => $this->pairCounts($pdo, 'SELECT GROUP_ID as owner_id, COUNT(*) as cnt FROM b_tasks GROUP BY GROUP_ID ORDER BY cnt DESC LIMIT 30'),
            'task_owner_distribution' => $this->pairCounts($pdo, 'SELECT RESPONSIBLE_ID as owner_id, COUNT(*) as cnt FROM b_tasks GROUP BY RESPONSIBLE_ID ORDER BY cnt DESC LIMIT 30'),
            'tasks_by_responsible_user' => $this->pairCounts($pdo, 'SELECT RESPONSIBLE_ID as owner_id, COUNT(*) as cnt FROM b_tasks GROUP BY RESPONSIBLE_ID ORDER BY cnt DESC LIMIT 50'),
            'disk_files_per_storage' => $this->pairCounts($pdo, 'SELECT STORAGE_ID as owner_id, COUNT(*) as cnt FROM b_disk_object WHERE TYPE = 3 GROUP BY STORAGE_ID ORDER BY cnt DESC LIMIT 50'),
            'disk_folders_per_storage' => $this->pairCounts($pdo, 'SELECT STORAGE_ID as owner_id, COUNT(*) as cnt FROM b_disk_object WHERE TYPE = 2 GROUP BY STORAGE_ID ORDER BY cnt DESC LIMIT 50'),
            'db_size_bytes' => $this->estimateDbSize($pdo),
            'linkage' => $this->inspectLinkage($pdo, $deep),
        ];
    }

    private function inspectLinkage(PDO $pdo, bool $deep): array
    {
        $limit = $deep ? 1000 : 200;

        $tasksWithAttachments = $this->scalar($pdo, "SELECT COUNT(DISTINCT ENTITY_ID) FROM b_disk_attached_object WHERE MODULE_ID='tasks'");
        $tasksWithCommentAttachments = $this->scalar($pdo, "SELECT COUNT(DISTINCT c.TASK_ID) FROM b_tasks_comment c JOIN b_disk_attached_object a ON a.MODULE_ID='task_comment' AND a.ENTITY_ID=c.ID");
        $commentFiles = $this->scalar($pdo, "SELECT COUNT(DISTINCT a.OBJECT_ID) FROM b_disk_attached_object a WHERE a.MODULE_ID='task_comment'");

        $attachmentLinks = $this->scalar($pdo, 'SELECT COUNT(*) FROM b_disk_attached_object');
        $distinctAttachmentPairs = $this->scalar($pdo, 'SELECT COUNT(*) FROM (SELECT OBJECT_ID, MODULE_ID, ENTITY_ID FROM b_disk_attached_object GROUP BY OBJECT_ID, MODULE_ID, ENTITY_ID) x');
        $duplicates = max(0, $attachmentLinks - $distinctAttachmentPairs);

        $filesMultiLinked = $this->scalar($pdo, 'SELECT COUNT(*) FROM (SELECT o.FILE_ID FROM b_disk_attached_object a JOIN b_disk_object o ON o.ID = a.OBJECT_ID GROUP BY o.FILE_ID HAVING COUNT(*) > 1) x');
        $filesLinkedToMultipleTasks = $this->scalar($pdo, "SELECT COUNT(*) FROM (SELECT o.FILE_ID FROM b_disk_attached_object a JOIN b_disk_object o ON o.ID=a.OBJECT_ID WHERE a.MODULE_ID='tasks' GROUP BY o.FILE_ID HAVING COUNT(DISTINCT a.ENTITY_ID) > 1) x");
        $filesLinkedTaskAndComment = $this->scalar($pdo, "SELECT COUNT(*) FROM (SELECT o.FILE_ID FROM b_disk_attached_object a JOIN b_disk_object o ON o.ID=a.OBJECT_ID GROUP BY o.FILE_ID HAVING SUM(CASE WHEN a.MODULE_ID='tasks' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN a.MODULE_ID='task_comment' THEN 1 ELSE 0 END) > 0) x");

        $orphanAttachmentReferences = $this->scalar($pdo, 'SELECT COUNT(*) FROM b_disk_attached_object a LEFT JOIN b_disk_object o ON o.ID=a.OBJECT_ID LEFT JOIN b_file f ON f.ID=o.FILE_ID WHERE o.ID IS NULL OR f.ID IS NULL');
        $diskObjectsWithoutAttachedContext = $this->scalar($pdo, 'SELECT COUNT(*) FROM b_disk_object o LEFT JOIN b_disk_attached_object a ON a.OBJECT_ID=o.ID WHERE a.ID IS NULL');
        $diskObjectsWithoutPhysicalFile = $this->scalar($pdo, 'SELECT COUNT(*) FROM b_disk_object o LEFT JOIN b_file f ON f.ID=o.FILE_ID WHERE f.ID IS NULL');
        $physicalFilesWithoutLogicalLinkage = $this->scalar($pdo, 'SELECT COUNT(*) FROM b_file f LEFT JOIN b_disk_object o ON o.FILE_ID=f.ID WHERE o.ID IS NULL');

        $attachmentsPerTask = $this->pairCounts($pdo, "SELECT ENTITY_ID as owner_id, COUNT(*) as cnt FROM b_disk_attached_object WHERE MODULE_ID='tasks' GROUP BY ENTITY_ID ORDER BY cnt DESC LIMIT {$limit}");
        $taskWithMostAttachments = (int) ($attachmentsPerTask[0]['count'] ?? 0);
        $averageAttachmentsPerTask = $tasksWithAttachments > 0 ? round($this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_attached_object WHERE MODULE_ID='tasks'") / $tasksWithAttachments, 2) : 0.0;

        return [
            'tasks_with_attachments' => $tasksWithAttachments,
            'tasks_with_comment_attachments' => $tasksWithCommentAttachments,
            'tasks_with_multiple_attachment_sources' => $this->scalar($pdo, "SELECT COUNT(*) FROM (SELECT t.ID FROM b_tasks t LEFT JOIN b_disk_attached_object ta ON ta.MODULE_ID='tasks' AND ta.ENTITY_ID=t.ID LEFT JOIN b_tasks_comment c ON c.TASK_ID=t.ID LEFT JOIN b_disk_attached_object ca ON ca.MODULE_ID='task_comment' AND ca.ENTITY_ID=c.ID GROUP BY t.ID HAVING COUNT(DISTINCT ta.ID) > 0 AND COUNT(DISTINCT ca.ID) > 0) x"),
            'tasks_with_missing_file_object' => $this->scalar($pdo, "SELECT COUNT(DISTINCT a.ENTITY_ID) FROM b_disk_attached_object a LEFT JOIN b_disk_object o ON o.ID=a.OBJECT_ID LEFT JOIN b_file f ON f.ID=o.FILE_ID WHERE a.MODULE_ID='tasks' AND (o.ID IS NULL OR f.ID IS NULL)"),
            'tasks_with_duplicated_attachment_references' => $this->scalar($pdo, "SELECT COUNT(*) FROM (SELECT ENTITY_ID FROM b_disk_attached_object WHERE MODULE_ID='tasks' GROUP BY ENTITY_ID, OBJECT_ID HAVING COUNT(*) > 1) x"),
            'tasks_attachments_outside_context' => $this->scalar($pdo, "SELECT COUNT(DISTINCT a.ENTITY_ID) FROM b_disk_attached_object a JOIN b_disk_object o ON o.ID=a.OBJECT_ID WHERE a.MODULE_ID='tasks' AND o.PARENT_ID IS NULL"),
            'comments_with_files' => $this->scalar($pdo, "SELECT COUNT(DISTINCT a.ENTITY_ID) FROM b_disk_attached_object a WHERE a.MODULE_ID='task_comment'"),
            'comments_with_missing_authors' => $this->scalar($pdo, 'SELECT COUNT(*) FROM b_tasks_comment WHERE AUTHOR_ID IS NULL OR AUTHOR_ID = 0'),
            'comments_with_missing_file_references' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_attached_object a LEFT JOIN b_disk_object o ON o.ID=a.OBJECT_ID LEFT JOIN b_file f ON f.ID=o.FILE_ID WHERE a.MODULE_ID='task_comment' AND (o.ID IS NULL OR f.ID IS NULL)"),
            'comments_unreachable_disk_objects' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_attached_object a JOIN b_tasks_comment c ON c.ID=a.ENTITY_ID LEFT JOIN b_tasks t ON t.ID=c.TASK_ID WHERE a.MODULE_ID='task_comment' AND t.ID IS NULL"),
            'files_multi_linked' => $filesMultiLinked,
            'files_linked_to_multiple_tasks' => $filesLinkedToMultipleTasks,
            'files_linked_task_and_comment' => $filesLinkedTaskAndComment,
            'files_linked_task_and_disk_folder' => $this->scalar($pdo, "SELECT COUNT(*) FROM (SELECT o.FILE_ID FROM b_disk_attached_object a JOIN b_disk_object o ON o.ID=a.OBJECT_ID GROUP BY o.FILE_ID HAVING SUM(CASE WHEN a.MODULE_ID='tasks' THEN 1 ELSE 0 END) > 0 AND SUM(CASE WHEN a.MODULE_ID='disk' THEN 1 ELSE 0 END) > 0) x"),
            'files_reused_across_smart_process_group_user' => $this->scalar($pdo, "SELECT COUNT(*) FROM (SELECT o.FILE_ID FROM b_disk_attached_object a JOIN b_disk_object o ON o.ID=a.OBJECT_ID GROUP BY o.FILE_ID HAVING COUNT(DISTINCT a.MODULE_ID) >= 3) x"),
            'orphan_attachment_references' => $orphanAttachmentReferences,
            'disk_objects_without_attached_context' => $diskObjectsWithoutAttachedContext,
            'disk_objects_without_physical_file' => $diskObjectsWithoutPhysicalFile,
            'physical_files_without_logical_linkage' => $physicalFilesWithoutLogicalLinkage,
            'attached_objects_missing_parent_entity' => $this->scalar($pdo, "SELECT COUNT(*) FROM b_disk_attached_object a LEFT JOIN b_tasks t ON a.MODULE_ID='tasks' AND t.ID=a.ENTITY_ID LEFT JOIN b_tasks_comment c ON a.MODULE_ID='task_comment' AND c.ID=a.ENTITY_ID WHERE (a.MODULE_ID='tasks' AND t.ID IS NULL) OR (a.MODULE_ID='task_comment' AND c.ID IS NULL)"),
            'average_attachments_per_task' => $averageAttachmentsPerTask,
            'max_attachments_per_task' => $taskWithMostAttachments,
            'tasks_with_large_attachments' => $this->scalar($pdo, "SELECT COUNT(*) FROM (SELECT ENTITY_ID FROM b_disk_attached_object WHERE MODULE_ID='tasks' GROUP BY ENTITY_ID HAVING COUNT(*) >= 10) x"),
            'attachment_type_distribution' => $this->pairCounts($pdo, 'SELECT MODULE_ID as owner_id, COUNT(*) as cnt FROM b_disk_attached_object GROUP BY MODULE_ID ORDER BY cnt DESC LIMIT 20'),
            'attachments_per_task_top' => $attachmentsPerTask,
            'storage_owner_distribution' => $this->pairCounts($pdo, 'SELECT CREATED_BY as owner_id, COUNT(*) as cnt FROM b_file GROUP BY CREATED_BY ORDER BY cnt DESC LIMIT 20'),
            'attachment_links_total' => $attachmentLinks,
            'duplicated_attachment_references' => $duplicates,
            'deep_mode' => $deep,
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
