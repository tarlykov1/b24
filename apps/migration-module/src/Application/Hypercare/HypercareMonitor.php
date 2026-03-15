<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

use DateInterval;
use DateTimeImmutable;

final class HypercareMonitor
{
    /** @var list<string> */
    private const WINDOWS = ['15 minutes', '1 hour', '6 hours', '24 hours', '3 days', '7 days', '30 days'];

    /** @return array<string,mixed> */
    public function start(string $jobId, ?DateTimeImmutable $goLiveAt = null): array
    {
        $start = $goLiveAt ?? new DateTimeImmutable();
        $windows = [];
        foreach (self::WINDOWS as $window) {
            $windows[] = [
                'window' => $window,
                'start_at' => $start->format(DATE_ATOM),
                'end_at' => $this->windowEnd($start, $window)->format(DATE_ATOM),
                'status' => 'active',
            ];
        }

        return ['job_id' => $jobId, 'phase' => 'hypercare', 'windows' => $windows, 'started_at' => $start->format(DATE_ATOM)];
    }

    /** @param array<string,float|int> $signals
     * @return array<string,mixed>
     */
    public function evaluate(array $signals): array
    {
        $errorRate = (float) ($signals['error_rate'] ?? 0.0);
        $queueBacklog = (int) ($signals['queue_backlog'] ?? 0);
        $workerSat = (float) ($signals['worker_saturation'] ?? 0.0);
        $apiLatency = (float) ($signals['api_latency_ms'] ?? 0.0);

        $score = max(0.0, 1.0 - ($errorRate * 2.5) - min(0.3, $queueBacklog / 10000) - max(0.0, $workerSat - 0.75) - min(0.3, $apiLatency / 5000));

        return [
            'system_health_score' => round($score, 4),
            'alerts' => array_values(array_filter([
                $errorRate > 0.03 ? 'elevated_error_rate' : null,
                $queueBacklog > 2000 ? 'queue_backlog_growth' : null,
                $workerSat > 0.9 ? 'worker_saturation_high' : null,
                $apiLatency > 1200 ? 'api_latency_regression' : null,
            ])),
        ];
    }

    private function windowEnd(DateTimeImmutable $start, string $window): DateTimeImmutable
    {
        return match ($window) {
            '15 minutes' => $start->add(new DateInterval('PT15M')),
            '1 hour' => $start->add(new DateInterval('PT1H')),
            '6 hours' => $start->add(new DateInterval('PT6H')),
            '24 hours' => $start->add(new DateInterval('P1D')),
            '3 days' => $start->add(new DateInterval('P3D')),
            '7 days' => $start->add(new DateInterval('P7D')),
            default => $start->add(new DateInterval('P30D')),
        };
    }
}
