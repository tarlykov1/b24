<?php

declare(strict_types=1);

namespace MigrationModule\Infrastructure\Http;

use MigrationModule\Application\Assistant\MigrationAssistantService;
use MigrationModule\Infrastructure\Persistence\Log\MigrationLogRepositoryInterface;

final class AdminController
{
    public function __construct(
        private readonly MigrationLogRepositoryInterface $logRepository,
        private readonly ?MigrationAssistantService $assistant = null,
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


    /** @param array<string,mixed> $snapshot @param array<int,array<string,mixed>> $history
     * @return array<string,mixed>
     */
    public function migrationAssistant(array $snapshot, array $history = []): array
    {
        if ($this->assistant === null) {
            return [
                'status' => 'disabled',
                'message' => 'Migration Assistant не инициализирован.',
            ];
        }

        return $this->assistant->assess($snapshot, $history, 'guided');
    }

    public function index(): string
    {
        return 'Migration admin UI: use filters by type, date and entity. Audit tab supports Run Audit and Export Report.';
    }
}
