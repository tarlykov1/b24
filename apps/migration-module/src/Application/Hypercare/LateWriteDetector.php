<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

use DateTimeImmutable;

final class LateWriteDetector
{
    /** @param list<array<string,mixed>> $changes
     * @param array<string,string> $windows
     * @return list<array<string,mixed>>
     */
    public function detect(array $changes, array $windows): array
    {
        $events = [];
        $freezeStart = new DateTimeImmutable((string) ($windows['freeze_start'] ?? '1970-01-01T00:00:00+00:00'));
        $stabilizationEnd = new DateTimeImmutable((string) ($windows['stabilization_end'] ?? '1970-01-01T00:00:00+00:00'));

        foreach ($changes as $change) {
            $changedAt = new DateTimeImmutable((string) ($change['changed_at'] ?? '1970-01-01T00:00:00+00:00'));
            if ($changedAt >= $freezeStart && $changedAt <= $stabilizationEnd) {
                $events[] = [
                    'entity' => (string) ($change['entity'] ?? 'unknown'),
                    'entity_id' => (string) ($change['entity_id'] ?? ''),
                    'changed_at' => $changedAt->format(DATE_ATOM),
                    'window' => $this->windowOf($changedAt, $windows),
                    'action' => 'sync_missing_changes',
                ];
            }
        }

        return $events;
    }

    /** @param array<string,string> $windows */
    private function windowOf(DateTimeImmutable $changedAt, array $windows): string
    {
        $cutoverEnd = new DateTimeImmutable((string) ($windows['cutover_end'] ?? '1970-01-01T00:00:00+00:00'));
        if ($changedAt <= $cutoverEnd) {
            return 'during_cutover';
        }

        return 'during_stabilization';
    }
}
