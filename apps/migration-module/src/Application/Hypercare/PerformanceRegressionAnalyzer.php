<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class PerformanceRegressionAnalyzer
{
    /** @param array<string,float|int> $pre
     * @param array<string,float|int> $post
     * @return array<string,mixed>
     */
    public function analyze(array $pre, array $post): array
    {
        $keys = ['api_latency_ms', 'query_time_ms', 'entity_load_ms', 'file_access_ms', 'search_response_ms', 'automation_exec_ms'];
        $regressions = [];
        foreach ($keys as $key) {
            $before = (float) ($pre[$key] ?? 0.0);
            $after = (float) ($post[$key] ?? 0.0);
            $delta = $before > 0 ? ($after - $before) / $before : 0.0;
            if ($delta > 0.2) {
                $regressions[] = ['metric' => $key, 'delta' => round($delta, 4), 'hint' => $this->hintFor($key)];
            }
        }

        return ['regressions' => $regressions, 'performance_score' => round(max(0.0, 1.0 - (count($regressions) / 6)), 4)];
    }

    private function hintFor(string $metric): string
    {
        return match ($metric) {
            'query_time_ms' => 'check_missing_indexes',
            'api_latency_ms' => 'inspect_worker_bottlenecks',
            'search_response_ms' => 'optimize_search_index',
            default => 'review_entity_mapping_and_permissions',
        };
    }
}
