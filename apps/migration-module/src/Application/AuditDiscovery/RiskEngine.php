<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery;

final class RiskEngine
{
    public function analyze(array $profile): array
    {
        $reasons = [];
        $score = 0;

        $tasks = (int) ($profile['tasks']['total'] ?? 0);
        $filesTotal = max(1, (int) ($profile['files']['total'] ?? 0));
        $filesGb = (float) ($profile['files']['total_size_gb'] ?? 0.0);
        $orphanFiles = (int) ($profile['files']['orphan_files'] ?? 0);
        $attachments = (int) ($profile['tasks']['with_files'] ?? 0);

        $linkage = (array) ($profile['linkage'] ?? []);
        $tasksWithCommentAttachments = (int) ($linkage['tasks_with_comment_attachments'] ?? 0);
        $filesMultiLinked = (int) ($linkage['files_multi_linked'] ?? 0);
        $orphanAttachmentReferences = (int) ($linkage['orphan_attachment_references'] ?? 0);
        $diskObjectsWithoutContext = (int) ($linkage['disk_objects_without_attached_context'] ?? 0);
        $attachmentTypeCount = count((array) ($linkage['attachment_type_distribution'] ?? []));
        $ownershipMetrics = (array) ($profile['permissions'] ?? []);
        $filesOwnedByInactive = (int) ($ownershipMetrics['files_owned_by_inactive_users'] ?? 0);
        $tasksOwnedByInactive = (int) ($ownershipMetrics['tasks_owned_by_inactive_users'] ?? 0);
        $aclInvalidEntries = (int) ($ownershipMetrics['disk_acl_invalid_entries'] ?? 0);
        $inactiveShare = (int) round(($filesOwnedByInactive / $filesTotal) * 100);

        if ($tasks > 200000) {
            $score += 4;
            $reasons[] = 'very large task volume';
        } elseif ($tasks > 50000) {
            $score += 2;
            $reasons[] = 'large task volume';
        }

        if ($filesGb > 500) {
            $score += 5;
            $reasons[] = 'very large file storage';
        } elseif ($filesGb > 100) {
            $score += 3;
            $reasons[] = 'large file storage';
        }

        if ($orphanFiles > 0) {
            $score += 4;
            $reasons[] = 'orphan files detected';
        }

        if ($inactiveShare > 20) {
            $score += 4;
            $reasons[] = '>20% files owned by inactive users';
        } elseif ($inactiveShare > 5) {
            $score += 2;
            $reasons[] = '>5% files owned by inactive users';
        }

        if ($tasksOwnedByInactive > 0) {
            $score += 2;
            $reasons[] = 'tasks owned by inactive users detected';
        }

        if ($aclInvalidEntries > 0) {
            $score += 2;
            $reasons[] = 'ACL entries referencing missing users/groups';
        }

        if ($attachments > 30000) {
            $score += 2;
            $reasons[] = 'many task attachments';
        }

        if ($tasksWithCommentAttachments > 5000) {
            $score += 2;
            $reasons[] = 'comment-file migration risk';
        }

        if ($filesMultiLinked > 2000) {
            $score += 3;
            $reasons[] = 'multi-context disk linkage';
        }

        if ($orphanAttachmentReferences > 0) {
            $score += 3;
            $reasons[] = 'orphan attachment graph';
        }

        if ($diskObjectsWithoutContext > 0) {
            $score += 2;
            $reasons[] = 'disk objects without attached context';
        }

        if ($attachmentTypeCount > 4) {
            $score += 2;
            $reasons[] = 'complex attachment topology';
        }

        $risk = match (true) {
            $score >= 8 => 'CRITICAL',
            $score >= 6 => 'HIGH',
            $score >= 3 => 'MEDIUM',
            default => 'LOW',
        };

        return [
            'risk_level' => $risk,
            'risks' => $reasons,
            'score' => $score,
            'ownership_metrics' => [
                'files_owned_by_inactive_users' => $filesOwnedByInactive,
                'tasks_owned_by_inactive_users' => $tasksOwnedByInactive,
                'disk_acl_invalid_entries' => $aclInvalidEntries,
                'files_without_valid_owner' => (int) ($ownershipMetrics['files_without_valid_owner'] ?? 0),
            ],
        ];
    }
}
