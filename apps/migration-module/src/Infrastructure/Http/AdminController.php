<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Http;

use MigrationModule\Application\Mapping\AutoMappingService;
use MigrationModule\Infrastructure\Persistence\Log\MigrationLogRepositoryInterface;

final class AdminController
{
    public function __construct(
        private readonly MigrationLogRepositoryInterface $logRepository,
        private readonly ?AutoMappingService $autoMappingService = null,
    ) {
    }

    /** @param array{status?:string,entity_type?:string,date_from?:string,date_to?:string} $filters
     * @return array<int, array<string,mixed>>
     */
    public function logs(array $filters): array
    {
        return $this->logRepository->findByFilters($filters);
    }

    /** @return array<string,mixed> */
    public function auditConfig(): array
    {
        return [
            'batch_size' => 50,
            'delay_ms' => 300,
            'supports_rerun' => true,
            'exports' => ['json', 'csv', 'html'],
        ];
    }

    /** @param array<string,mixed> $sourceSchema @param array<string,mixed> $targetSchema @param array<int,array<string,mixed>> $sampleData
     * @return array<string,mixed>
     */
    public function autoMappingPreview(string $jobId, array $sourceSchema, array $targetSchema, array $sampleData = []): array
    {
        if ($this->autoMappingService === null) {
            return [
                'error' => 'auto_mapping_service_not_configured',
                'field_mappings' => [],
                'stage_mappings' => [],
                'enum_mappings' => [],
            ];
        }

        return $this->autoMappingService->generate($jobId, $sourceSchema, $targetSchema, $sampleData);
    }

    public function index(): string
    {
        return 'Migration admin UI: use filters by type, date and entity. Audit tab supports Run Audit and Export Report. Auto mapping tab supports schema scan, confidence and manual correction.';
    }
}
