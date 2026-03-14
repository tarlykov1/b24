<?php

declare(strict_types=1);

namespace MigrationModule\Application\Consistency;

final class RelationIntegrityEngine
{
    /** @param array<int,array<string,mixed>> $relations */
    public function verify(array $relations): array
    {
        $restored = 0;
        $unresolved = 0;
        $dangling = 0;

        foreach ($relations as $relation) {
            if (($relation['target_exists'] ?? false) && ($relation['source_exists'] ?? false)) {
                $restored++;
                continue;
            }

            if (($relation['source_exists'] ?? false) && !($relation['target_exists'] ?? false)) {
                $unresolved++;
                continue;
            }

            $dangling++;
        }

        $total = count($relations);

        return [
            'total_relations_expected' => $total,
            'restored_relations' => $restored,
            'unresolved_relations' => $unresolved,
            'dangling_references' => $dangling,
            'healthy' => $total > 0 ? ($restored === $total) : true,
        ];
    }
}
