<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Persistence;

use MigrationModule\Domain\Integrity\IntegrityIssue;
use PDO;

final class MigrationIntegrityIssueRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(IntegrityIssue $issue): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO migration_integrity_issues (entity_type, entity_id, problem_type, description, created_at) VALUES (:entity_type, :entity_id, :problem_type, :description, :created_at)'
        );

        $stmt->execute($issue->toArray());
    }
}
