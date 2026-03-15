<?php

declare(strict_types=1);

namespace MigrationModule\Simulation\Application;

use MigrationModule\Simulation\Domain\SimulationRun;

final class RecommendationEngine
{
    /** @return array<string,mixed> */
    public function build(SimulationRun $run): array
    {
        $risk = (float) ($run->riskScores['OverallSimulationRiskScore'] ?? 0.0);
        $sourceFs = (float) ($run->sourceLoadProfile['fs_scan_pressure'] ?? 0.0);

        $messages = [];
        if ($risk > 70) {
            $messages[] = 'Слишком опасно: снизьте workers и включите source-safe mode.';
        }
        if ($sourceFs > 9) {
            $messages[] = 'Source filesystem pressure высокое: рекомендуем files-last.';
        }
        if ($run->estimatedTotalDurationHours > 8) {
            $messages[] = 'В окно 8 часов не помещается: нужен предварительный catch-up.';
        }
        if ($messages === []) {
            $messages[] = 'Сценарий сбалансирован и пригоден для пилотного dry-run.';
        }

        return [
            'machine' => [
                'recommended_worker_count' => $sourceFs > 9 ? 6 : 8,
                'recommended_file_strategy' => $sourceFs > 9 ? 'files-last' : 'parallel',
                'recommended_verify_depth' => $risk > 65 ? 'staged' : 'normal',
            ],
            'human' => $messages,
        ];
    }
}
