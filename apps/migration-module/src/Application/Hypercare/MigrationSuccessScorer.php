<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class MigrationSuccessScorer
{
    /** @param array<string,float> $scores
     * @return array<string,mixed>
     */
    public function score(array $scores): array
    {
        $final = (($scores['data_integrity'] ?? 0.0) * 0.3) + (($scores['adoption'] ?? 0.0) * 0.25) + (($scores['system_health'] ?? 0.0) * 0.2) + (($scores['performance'] ?? 0.0) * 0.15) + ((1 - ($scores['issue_severity'] ?? 1.0)) * 0.1);
        $bucket = match (true) {
            $final < 0.4 => 'FAILED',
            $final < 0.55 => 'RISKY',
            $final < 0.7 => 'ACCEPTABLE',
            $final < 0.85 => 'SUCCESS',
            default => 'EXCELLENT',
        };

        return ['migration_success_score' => round($final, 4), 'result' => $bucket];
    }
}
