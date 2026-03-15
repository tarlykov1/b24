<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class OptimizationAdvisor
{
    /** @param list<array<string,mixed>> $regressions
     * @return list<array<string,mixed>>
     */
    public function recommend(array $regressions): array
    {
        $out = [];
        foreach ($regressions as $regression) {
            $metric = (string) ($regression['metric'] ?? 'unknown');
            $out[] = [
                'area' => $this->area($metric),
                'recommendation' => $this->message($metric),
                'impact_score' => min(100, (int) round(((float) ($regression['delta'] ?? 0.2)) * 220)),
                'risk_level' => in_array($metric, ['query_time_ms', 'search_response_ms'], true) ? 'medium' : 'low',
                'implementation_effort' => in_array($metric, ['query_time_ms'], true) ? 'M' : 'S',
            ];
        }

        return $out;
    }

    private function area(string $metric): string
    {
        return match ($metric) {
            'query_time_ms' => 'database',
            'file_access_ms' => 'storage',
            'api_latency_ms', 'automation_exec_ms' => 'workers',
            default => 'bitrix_specifics',
        };
    }

    private function message(string $metric): string
    {
        return match ($metric) {
            'query_time_ms' => 'Create compound indexes and remove heavy joins.',
            'file_access_ms' => 'Rebalance large file clusters and optimize storage layout.',
            'api_latency_ms' => 'Increase worker count and tune queue backpressure.',
            'automation_exec_ms' => 'Inspect automation loops and smart process load.',
            default => 'Review CRM stage transitions and timeline overload.',
        };
    }
}
