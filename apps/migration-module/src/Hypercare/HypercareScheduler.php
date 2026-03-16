<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class HypercareScheduler
{
    public function policy(int $durationDays = 14): array
    {
        $durationDays = in_array($durationDays, [7, 14, 30], true) ? $durationDays : 14;

        return [
            'duration_days' => $durationDays,
            'scan_frequency' => [
                'integrity_scan' => 'PT6H',
                'adoption_analytics' => 'P1D',
                'performance_monitoring' => 'PT1M',
            ],
            'safety' => [
                'adaptive_throttling' => true,
                'max_db_queries_per_cycle' => 120,
                'max_rest_calls_per_cycle' => 80,
            ],
        ];
    }

    public function status(string $startedAt, int $durationDays = 14, ?string $now = null): array
    {
        $policy = $this->policy($durationDays);
        $start = new \DateTimeImmutable($startedAt);
        $current = new \DateTimeImmutable($now ?? 'now');
        $end = $start->modify(sprintf('+%d days', (int) $policy['duration_days']));
        $active = $current < $end;

        return [
            'started_at' => $start->format(DATE_ATOM),
            'ends_at' => $end->format(DATE_ATOM),
            'mode' => $active ? 'hypercare_active' : 'migration_completed',
            'remaining_hours' => max(0, (int) floor(($end->getTimestamp() - $current->getTimestamp()) / 3600)),
            'policy' => $policy,
        ];
    }
}
