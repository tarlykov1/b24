<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Http;

use MigrationModule\Infrastructure\Persistence\Log\MigrationLogRepositoryInterface;

final class AdminController
{
    public function __construct(private readonly MigrationLogRepositoryInterface $logRepository)
    {
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
            'profiles' => ['safe', 'balanced', 'aggressive'],
            'modes' => ['dry_run', 'initial_full_migration', 'incremental_sync', 'repeat_verification'],
            'exports' => ['json', 'csv', 'html'],
        ];
    }

    public function index(): string
    {
        return 'Migration admin UI: use filters by type, date and entity. Audit tab supports Run Audit and Export Report.';
    }
}
