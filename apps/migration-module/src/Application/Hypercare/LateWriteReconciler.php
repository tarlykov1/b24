<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class LateWriteReconciler
{
    /** @param list<array<string,mixed>> $lateWrites
     * @param array<string,array<string,mixed>> $targetState
     * @return array<string,mixed>
     */
    public function reconcile(array $lateWrites, array $targetState): array
    {
        $result = ['synced' => 0, 'merged' => 0, 'conflicts' => []];

        foreach ($lateWrites as $event) {
            $id = (string) ($event['entity_id'] ?? '');
            if ($id === '' || !isset($targetState[$id])) {
                ++$result['synced'];
                continue;
            }

            $sourceHash = (string) ($event['hash'] ?? '');
            $targetHash = (string) ($targetState[$id]['hash'] ?? '');
            if ($sourceHash !== '' && $targetHash !== '' && $sourceHash !== $targetHash) {
                $result['conflicts'][] = ['entity_id' => $id, 'type' => 'hash_mismatch'];
                continue;
            }
            ++$result['merged'];
        }

        return $result;
    }
}
