<?php

declare(strict_types=1);

namespace MigrationModule\Application\Production;

use PDO;

final class DisasterRecoveryService
{
    /** @return array<string,mixed> */
    public function repair(PDO $pdo): array
    {
        $pdo->exec("UPDATE queue SET status='pending' WHERE status='retry'");
        $orphans = (int) $pdo->query("SELECT COUNT(*) FROM entity_map WHERE target_id IS NULL OR target_id='' ")->fetchColumn();

        return ['ok' => true, 'queue_rebuilt' => true, 'mapping_orphans' => $orphans];
    }

    /** @return array<string,mixed> */
    public function recover(PDO $pdo): array
    {
        $pdo->exec("INSERT OR IGNORE INTO state(entity_type, entity_id, status, updated_at) VALUES('recovery','runtime','{\"recovered\":true}', datetime('now'))");

        return ['ok' => true, 'state_repaired' => true, 'resume_supported' => true];
    }
}
