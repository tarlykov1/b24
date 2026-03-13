<?php

declare(strict_types=1);

use MigrationModule\Application\Assistant\KnowledgeBaseRepository;
use MigrationModule\Application\Assistant\MigrationAssistantService;
use PHPUnit\Framework\TestCase;

final class MigrationAssistantServiceTest extends TestCase
{
    public function testAssistantWorksInDeterministicModeWithoutLlm(): void
    {
        $service = new MigrationAssistantService(new KnowledgeBaseRepository(__DIR__ . '/../../apps/migration-module/config/assistant/rule-pack.json'));

        $snapshot = [
            'source_available' => true,
            'target_available' => true,
            'custom_fields_count' => 230,
            'files_count' => 90000,
            'relation_density' => 0.72,
            'mapping_coverage' => 0.82,
            'stage_mapping_coverage' => 0.84,
            'unresolved_conflicts' => 31,
            'inactive_users' => 120,
            'id_conflict_risk' => 0.7,
            'heavy_endpoint_pressure' => 0.75,
        ];

        $result = $service->assess($snapshot, [], 'guided', false, false);

        self::assertSame(false, $result['operation_mode']['local_llm_enabled']);
        self::assertSame(true, $result['operation_mode']['deterministic_fallback']);
        self::assertSame('safe', $result['recommended_load_profile']['profile']);
        self::assertContains('mapping_hardening', $result['recommended_phase_order']);
        self::assertNotEmpty($result['why_this_recommendation']['rule']);
        self::assertNotEmpty($result['operator_checklist']);
    }

    public function testAssistantUsesHistorySignalsForRetryStorms(): void
    {
        $service = new MigrationAssistantService(new KnowledgeBaseRepository(__DIR__ . '/../../apps/migration-module/config/assistant/rule-pack.json'));

        $snapshot = [
            'source_available' => true,
            'target_available' => true,
            'mapping_coverage' => 0.97,
            'stage_mapping_coverage' => 0.98,
            'files_count' => 100,
        ];

        $history = [
            ['status' => 'failed', 'error_groups' => ['429', 'timeout'], 'operator_overrides' => 1],
            ['status' => 'failed', 'error_groups' => ['timeout'], 'operator_overrides' => 0],
            ['status' => 'success', 'error_groups' => ['mapping'], 'operator_overrides' => 2],
        ];

        $result = $service->assess($snapshot, $history, 'advisory', true, false);

        self::assertSame('learning', $result['history_learning']['status']);
        self::assertSame(3, $result['history_learning']['total_runs']);
        self::assertSame('Повторяются неудачные запуски: зафиксируйте схему, уменьшите нагрузку и начните с dry-run.', $result['next_best_action']);
        self::assertContains('timeout', $result['history_learning']['common_error_groups']);
    }
}
