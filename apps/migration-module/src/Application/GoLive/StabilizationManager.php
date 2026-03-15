<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

use DateTimeImmutable;

final class StabilizationManager
{
    /** @param array<int,array<string,mixed>> $issues
     * @return array<string,mixed>
     */
    public function track(array $issues): array
    {
        $clusters = [];
        foreach ($issues as $issue) {
            $key = (string) ($issue['type'] ?? 'unknown');
            $clusters[$key] = ($clusters[$key] ?? 0) + 1;
        }

        return [
            'monitoringWindows' => ['15m', '1h', '24h', '3d'],
            'issueClusters' => $clusters,
            'userImpactScore' => min(100, array_sum(array_map(static fn (array $i): int => (int) ($i['impact'] ?? 1), $issues))),
            'remainingDeltaReconciliation' => count(array_filter($issues, static fn (array $i): bool => ($i['type'] ?? '') === 'missed_write')),
            'capturedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
