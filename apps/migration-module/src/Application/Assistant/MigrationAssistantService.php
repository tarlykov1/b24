<?php

declare(strict_types=1);

namespace MigrationModule\Application\Assistant;

final class MigrationAssistantService
{
    public function __construct(private readonly KnowledgeBaseRepository $knowledgeBase)
    {
    }

    /**
     * @param array<string,mixed> $snapshot
     * @param array<int,array<string,mixed>> $history
     * @return array<string,mixed>
     */
    public function assess(array $snapshot, array $history = [], string $mode = 'advisory', bool $localMlEnabled = false, bool $localLlmEnabled = false): array
    {
        $rules = $this->knowledgeBase->load();
        $thresholds = (array) ($rules['thresholds'] ?? []);
        $weights = (array) ($rules['risk_weights'] ?? []);
        $templates = (array) ($rules['remediation_templates'] ?? []);

        $factors = [
            'source_available' => (bool) ($snapshot['source_available'] ?? false),
            'target_available' => (bool) ($snapshot['target_available'] ?? false),
            'custom_fields_count' => (int) ($snapshot['custom_fields_count'] ?? 0),
            'files_count' => (int) ($snapshot['files_count'] ?? 0),
            'relation_density' => (float) ($snapshot['relation_density'] ?? 0.0),
            'mapping_coverage' => (float) ($snapshot['mapping_coverage'] ?? 0.0),
            'stage_mapping_coverage' => (float) ($snapshot['stage_mapping_coverage'] ?? 0.0),
            'unresolved_conflicts' => (int) ($snapshot['unresolved_conflicts'] ?? 0),
            'inactive_users' => (int) ($snapshot['inactive_users'] ?? 0),
            'id_conflict_risk' => (float) ($snapshot['id_conflict_risk'] ?? 0.0),
            'heavy_endpoint_pressure' => (float) ($snapshot['heavy_endpoint_pressure'] ?? 0.0),
        ];

        $riskScore = 0;
        $blockers = [];
        $warnings = [];

        if ($factors['source_available'] === false) {
            $riskScore += (int) ($weights['source_unavailable'] ?? 40);
            $blockers[] = 'Source недоступен.';
        }
        if ($factors['target_available'] === false) {
            $riskScore += (int) ($weights['target_unavailable'] ?? 40);
            $blockers[] = 'Target недоступен.';
        }

        $riskScore += $factors['custom_fields_count'] > (int) ($thresholds['high_custom_fields'] ?? 100) ? (int) ($weights['custom_fields'] ?? 10) : 0;
        $riskScore += $factors['files_count'] > (int) ($thresholds['high_file_volume'] ?? 30000) ? (int) ($weights['files'] ?? 20) : 0;
        $riskScore += $factors['relation_density'] > (float) ($thresholds['high_relation_density'] ?? 0.6) ? (int) ($weights['relation_density'] ?? 10) : 0;
        $riskScore += $factors['mapping_coverage'] < 0.90 ? (int) ($weights['mapping_coverage'] ?? 20) : 0;
        $riskScore += $factors['stage_mapping_coverage'] < 0.95 ? (int) ($weights['stage_mapping_coverage'] ?? 15) : 0;
        $riskScore += $factors['unresolved_conflicts'] > (int) ($thresholds['high_unresolved_conflicts'] ?? 10) ? (int) ($weights['unresolved_conflicts'] ?? 20) : 0;
        $riskScore += $factors['id_conflict_risk'] >= 0.5 ? (int) ($weights['id_conflict_risk'] ?? 10) : 0;
        $riskScore += $factors['heavy_endpoint_pressure'] >= 0.7 ? (int) ($weights['heavy_endpoints'] ?? 10) : 0;
        $riskScore = min(100, $riskScore);

        if ($factors['mapping_coverage'] < 0.85) {
            $warnings[] = 'Низкое покрытие mapping; возможны quarantine и ручные исправления.';
        }
        if ($factors['stage_mapping_coverage'] < 0.90) {
            $warnings[] = 'Неполное stage mapping; требуется ручная валидация воронок.';
        }
        if ($factors['inactive_users'] > (int) ($thresholds['high_inactive_users'] ?? 80)) {
            $warnings[] = 'Высокое число inactive users до даты отсечения.';
        }

        $readinessScore = max(0, 100 - $riskScore);

        $loadProfile = $this->recommendLoadProfile($riskScore, $thresholds);
        $plan = $this->buildAdaptivePlan($snapshot, (array) ($rules['phase_template'] ?? []));
        $recommendations = $this->buildRecommendations($factors, $templates, $riskScore, $loadProfile, $localMlEnabled, $localLlmEnabled, $history);

        return [
            'mode' => in_array($mode, ['advisory', 'guided', 'semi-auto'], true) ? $mode : 'advisory',
            'operation_mode' => [
                'local_only' => true,
                'external_ai_calls' => false,
                'deterministic_fallback' => true,
                'local_ml_enabled' => $localMlEnabled,
                'local_llm_enabled' => $localLlmEnabled,
                'llm_optional' => true,
            ],
            'preflight' => [
                'overall_readiness_score' => $readinessScore,
                'risk_score' => $riskScore,
                'blockers' => $blockers,
                'warnings' => $warnings,
            ],
            'recommended_run_mode' => $riskScore >= 70 ? 'dry_run_then_incremental' : 'full_migration_then_incremental',
            'recommended_load_profile' => $loadProfile,
            'recommended_phase_order' => $plan,
            'mapping_review_recommendations' => $recommendations['mapping'],
            'healing_recommendations' => $recommendations['healing'],
            'verification_recommendations' => $recommendations['verification'],
            'next_best_action' => $recommendations['next_best_action'],
            'operator_checklist' => $recommendations['checklist'],
            'why_this_recommendation' => $recommendations['why'],
            'history_learning' => $this->buildHistoryInsights($history),
        ];
    }

    /** @param array<string,mixed> $thresholds @return array<string,mixed> */
    private function recommendLoadProfile(int $riskScore, array $thresholds): array
    {
        if ($riskScore >= 70) {
            return ['profile' => 'safe', 'workers' => (int) ($thresholds['max_safe_workers'] ?? 4), 'batch_size' => (int) ($thresholds['max_safe_batch'] ?? 50), 'parallelism' => false];
        }
        if ($riskScore >= 40) {
            return ['profile' => 'balanced', 'workers' => (int) ($thresholds['max_balanced_workers'] ?? 8), 'batch_size' => (int) ($thresholds['max_balanced_batch'] ?? 120), 'parallelism' => true];
        }

        return ['profile' => 'aggressive', 'workers' => (int) ($thresholds['max_aggressive_workers'] ?? 16), 'batch_size' => (int) ($thresholds['max_aggressive_batch'] ?? 250), 'parallelism' => true];
    }

    /** @param array<string,mixed> $snapshot @param array<int,string> $template @return array<int,string> */
    private function buildAdaptivePlan(array $snapshot, array $template): array
    {
        $plan = $template === [] ? ['users', 'reference_data_and_custom_fields', 'companies_and_contacts', 'leads_and_deals', 'files', 'incremental_sync', 'reconciliation'] : $template;
        if ((int) ($snapshot['files_count'] ?? 0) < 1000) {
            $plan = array_values(array_filter($plan, static fn (string $phase): bool => $phase !== 'files'));
        }
        if ((float) ($snapshot['mapping_coverage'] ?? 0) < 0.9) {
            array_unshift($plan, 'mapping_hardening');
        }

        return array_values(array_unique($plan));
    }

    /**
     * @param array<string,mixed> $factors
     * @param array<string,mixed> $templates
     * @param array<string,mixed> $loadProfile
     * @param array<int,array<string,mixed>> $history
     * @return array<string,mixed>
     */
    private function buildRecommendations(array $factors, array $templates, int $riskScore, array $loadProfile, bool $localMlEnabled, bool $localLlmEnabled, array $history): array
    {
        $mapping = [];
        if ($factors['mapping_coverage'] < 0.90) {
            $mapping[] = 'Проведите ручной review ambiguous mapping и low confidence полей.';
            $mapping[] = (string) ($templates['mapping_low'] ?? 'Улучшите mapping.');
        }
        if ($factors['stage_mapping_coverage'] < 0.95) {
            $mapping[] = (string) ($templates['stage_mapping_low'] ?? 'Проверьте stage mapping.');
        }

        $healing = [];
        if ($riskScore >= 70 || $factors['unresolved_conflicts'] > 10) {
            $healing[] = 'Используйте conservative healing policy и grouped retry по типам ошибок.';
        } else {
            $healing[] = 'Используйте balanced healing policy с retry и quarantine review каждые 15 минут.';
        }

        $verification = [
            'Сначала sampling verification, затем полная certification verification.',
            'После первого полного прогона выполните incremental sync и reconciliation.',
        ];

        if ($factors['files_count'] > 50000) {
            $verification[] = 'Проверьте согласованность файлов отдельным verification-проходом.';
            $healing[] = (string) ($templates['files_heavy'] ?? 'Вынесите файлы в отдельную фазу.');
        }

        $historySuccessRate = $this->historySuccessRate($history);
        $nextBestAction = $riskScore >= 70
            ? 'Запустите dry-run в safe профиле и устраните блокеры до write-операций.'
            : 'Запустите controlled full migration в guided режиме.';

        if ($historySuccessRate < 0.5) {
            $nextBestAction = 'Повторяются неудачные запуски: зафиксируйте схему, уменьшите нагрузку и начните с dry-run.';
            $healing[] = (string) ($templates['retry_storm'] ?? 'Перейдите в conservative healing policy.');
        }

        $checklist = [
            'Подтвердить доступность source/target и неизменность учетных данных.',
            'Проверить unresolved conflicts и полноту stage mapping.',
            'Согласовать стратегию inactive users и duplicate handling.',
            'Зафиксировать профиль нагрузки и лимиты heavy endpoints.',
            'Утвердить план фаз и критерии остановки потока.',
        ];

        if ($localMlEnabled) {
            $checklist[] = 'Проверить актуальность локальной scoring-модели на истории запусков.';
        }
        if ($localLlmEnabled) {
            $checklist[] = 'Проверить health локального LLM endpoint и маскирование чувствительных данных.';
        }

        return [
            'mapping' => $mapping,
            'healing' => $healing,
            'verification' => $verification,
            'next_best_action' => $nextBestAction,
            'checklist' => $checklist,
            'why' => [
                'input_factors' => $factors,
                'rule' => sprintf('risk_score=%d => profile=%s', $riskScore, (string) $loadProfile['profile']),
                'confidence' => $localMlEnabled ? 0.82 : 0.74,
                'expected_effect' => 'Снижение вероятности 429/timeout, уменьшение quarantine и предсказуемый cutover.',
                'risk_if_ignored' => 'Рост конфликтов mapping, деградация source под нагрузкой и нестабильный repeat-run.',
                'llm_used' => $localLlmEnabled,
                'deterministic_fallback_used' => !$localLlmEnabled,
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $history @return array<string,mixed> */
    private function buildHistoryInsights(array $history): array
    {
        if ($history === []) {
            return ['status' => 'cold_start', 'message' => 'История запусков отсутствует; используются базовые rule packs.'];
        }

        return [
            'status' => 'learning',
            'total_runs' => count($history),
            'success_rate' => $this->historySuccessRate($history),
            'common_error_groups' => $this->topErrorGroups($history),
            'operator_overrides' => array_sum(array_map(static fn (array $run): int => (int) ($run['operator_overrides'] ?? 0), $history)),
        ];
    }

    /** @param array<int,array<string,mixed>> $history */
    private function historySuccessRate(array $history): float
    {
        if ($history === []) {
            return 0.5;
        }
        $successful = 0;
        foreach ($history as $run) {
            if (($run['status'] ?? '') === 'success') {
                $successful++;
            }
        }

        return round($successful / count($history), 2);
    }

    /** @param array<int,array<string,mixed>> $history @return array<int,string> */
    private function topErrorGroups(array $history): array
    {
        $counts = [];
        foreach ($history as $run) {
            foreach ((array) ($run['error_groups'] ?? []) as $group) {
                $groupKey = (string) $group;
                $counts[$groupKey] = ($counts[$groupKey] ?? 0) + 1;
            }
        }
        arsort($counts);

        return array_slice(array_keys($counts), 0, 3);
    }
}
