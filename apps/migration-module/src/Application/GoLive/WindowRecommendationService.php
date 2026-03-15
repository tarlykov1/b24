<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

final class WindowRecommendationService
{
    /** @param array<int,array{day:string,hour:int,activity:int}> $activity
     * @return array<string,mixed>
     */
    public function recommend(array $activity, int $deltaEtaMin, int $windowMin): array
    {
        usort($activity, static fn (array $a, array $b): int => $a['activity'] <=> $b['activity']);

        $best = array_slice($activity, 0, 5);
        $worst = array_slice(array_reverse($activity), 0, 5);
        $riskLateStart = $deltaEtaMin > $windowMin ? 'high' : ($deltaEtaMin > (int) ($windowMin * 0.7) ? 'medium' : 'low');

        return [
            'bestWindows' => $best,
            'worstWindows' => $worst,
            'safestSuggestions' => array_values(array_filter($best, static fn (array $s): bool => in_array($s['day'], ['Sat', 'Sun'], true) || $s['hour'] < 7 || $s['hour'] > 21)),
            'riskIfStartingTooLate' => $riskLateStart,
            'riskIfDeltaExceedsWindow' => $deltaEtaMin > $windowMin ? 'critical' : 'acceptable',
        ];
    }
}
