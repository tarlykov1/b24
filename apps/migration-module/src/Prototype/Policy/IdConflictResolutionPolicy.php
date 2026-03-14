<?php

declare(strict_types=1);

namespace MigrationModule\Prototype\Policy;

use MigrationModule\Prototype\Adapter\TargetAdapterInterface;

final class IdConflictResolutionPolicy
{
    public function resolve(TargetAdapterInterface $target, string $entityType, string $sourceId): array
    {
        if (!$target->exists($entityType, $sourceId)) {
            return ['target_id' => $sourceId, 'conflict' => false, 'strategy' => 'preserve_source_id'];
        }

        return ['target_id' => sprintf('migrated_%s', $sourceId), 'conflict' => true, 'strategy' => 'fallback_suffix'];
    }
}
