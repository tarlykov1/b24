<?php

declare(strict_types=1);

namespace MigrationModule\Application\Execution\Deterministic;

use MigrationModule\Prototype\Adapter\TargetAdapterInterface;
use MigrationModule\Prototype\Storage\SqliteStorage;

final class IdReservationService
{
    public function __construct(private readonly SqliteStorage $storage)
    {
    }

    public function reserve(string $planId, TargetAdapterInterface $target, string $entityType, string $sourceId, string $policy): array
    {
        $existing = $this->storage->findIdReservation($planId, $entityType, $sourceId);
        if ($existing !== null) {
            return $existing;
        }

        $requested = $sourceId;
        $occupied = $target->exists($entityType, $requested);
        $result = ['reserved_target_id' => $requested, 'reason' => 'preserved'];

        if ($occupied) {
            if ($policy === 'preserve_strict' || $policy === 'fail_on_conflict') {
                $result = ['reserved_target_id' => $requested, 'reason' => 'conflict_fail'];
            } elseif ($policy === 'allocate_on_conflict' || $policy === 'preserve_if_possible') {
                $result = ['reserved_target_id' => sprintf('migrated_%s', $sourceId), 'reason' => 'deterministic_conflict_remap'];
            }
        }

        $record = [
            'plan_id' => $planId,
            'entity_type' => $entityType,
            'source_id' => $sourceId,
            'requested_target_id' => $requested,
            'reserved_target_id' => (string) $result['reserved_target_id'],
            'policy' => $policy,
            'reason' => (string) $result['reason'],
        ];
        $this->storage->saveIdReservation($record);

        return $record;
    }
}
