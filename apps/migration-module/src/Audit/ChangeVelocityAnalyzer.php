<?php

declare(strict_types=1);

namespace MigrationModule\Audit;

use DateTimeImmutable;
use MigrationModule\Infrastructure\Bitrix\BitrixRestClient;
use PDO;

final class ChangeVelocityAnalyzer
{
    public function __construct(
        private readonly VelocitySampler $sampler = new VelocitySampler(),
        private readonly ConflictProbabilityCalculator $conflictCalculator = new ConflictProbabilityCalculator(),
        private readonly MigrationStrategyAdvisor $strategyAdvisor = new MigrationStrategyAdvisor(),
    ) {
    }

    /** @return array<string,mixed> */
    public function analyze(?PDO $pdo, ?BitrixRestClient $restClient, string $uploadRoot, int $days = 30, int $sampleSize = 1000, ?string $entity = null): array
    {
        $sampled = $this->sampler->sample($pdo, $restClient, $uploadRoot, $days, $sampleSize, $entity);
        $entities = (array) ($sampled['entities'] ?? []);

        $deltaEstimate = [];
        $mutationsPrediction = [];
        $totalEntities = 0;
        $dynamicEntities = 0;
        foreach ($entities as $entityKey => $metrics) {
            $hourlyRate = (float) ($metrics['avg_changes_per_hour'] ?? 0.0);
            $migrationHours = 4.0;
            $predicted = (int) ceil($hourlyRate * $migrationHours);
            $mutationsPrediction[$entityKey] = $predicted;
            $deltaEstimate[$entityKey] = ['changed_during_4h_migration' => $predicted];

            $totalEntities += (int) ($metrics['total_entities'] ?? 0);
            if (((int) ($metrics['changes_last_30d'] ?? 0)) > 0) {
                $dynamicEntities += (int) ($metrics['total_entities'] ?? 0);
            }
        }

        $conflicts = $this->conflictCalculator->calculate($entities, 4.0);
        $strategy = $this->strategyAdvisor->advise($entities, $conflicts);
        $staticPercentage = $totalEntities > 0 ? round((($totalEntities - $dynamicEntities) / $totalEntities) * 100, 2) : 100.0;
        $safetyScore = $this->safetyScore($conflicts, $staticPercentage);

        $report = [
            'generated_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            'entities' => $entities,
            'velocity_heatmap' => (array) ($sampled['velocity_heatmap'] ?? []),
            'delta_estimate' => $deltaEstimate,
            'conflict_probability' => $conflicts,
            'migration_strategy' => $strategy,
            'mutation_prediction' => $mutationsPrediction,
            'safety_score' => $safetyScore,
            'safety_level' => $safetyScore >= 80 ? 'LOW' : ($safetyScore >= 60 ? 'MEDIUM' : 'HIGH'),
            'static_data_percentage' => $staticPercentage,
            'dynamic_data_percentage' => round(100 - $staticPercentage, 2),
            'sources' => $sampled['sources'] ?? ['db' => false, 'rest' => false, 'filesystem' => false],
        ];

        $this->persist($report);

        return $report;
    }

    /** @param array<string,array<string,mixed>> $conflicts */
    private function safetyScore(array $conflicts, float $staticPercentage): int
    {
        if ($conflicts === []) {
            return (int) round(min(100, 50 + ($staticPercentage / 2)));
        }

        $avgProbability = array_sum(array_map(static fn (array $row): float => (float) ($row['probability'] ?? 0.0), $conflicts)) / count($conflicts);
        $score = 100 - (int) round($avgProbability * 60) - (int) round((100 - $staticPercentage) * 0.3);

        return max(0, min(100, $score));
    }

    /** @param array<string,mixed> $report */
    private function persist(array $report): void
    {
        if (!is_dir('reports')) {
            mkdir('reports', 0775, true);
        }

        file_put_contents('reports/change_velocity_report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        file_put_contents('reports/velocity_heatmap.json', json_encode(['velocity_heatmap' => $report['velocity_heatmap'] ?? []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
