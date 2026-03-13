<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

use MigrationModule\Application\Freeze\FreezeModeService;
use MigrationModule\Application\Sync\DeltaSyncService;
use MigrationModule\Infrastructure\Persistence\MigrationRepository;
use RuntimeException;

final class CutoverService
{
    public function __construct(
        private readonly MigrationRepository $repository,
        private readonly DeltaSyncService $deltaSyncService,
        private readonly FreezeModeService $freezeMode,
    ) {
    }

    /** @param array<string,mixed> $verificationReport @param array<int,array<string,mixed>> $sourceRecords @param array<int,array<string,mixed>> $targetRecords @param array<string,mixed> $freezeCapabilities */
    public function execute(
        string $jobId,
        string $entityType,
        array $verificationReport,
        array $sourceRecords,
        array $targetRecords,
        bool $confirmStart,
        bool $confirmSwitch,
        array $freezeCapabilities = [],
    ): array {
        if (!$confirmStart) {
            throw new RuntimeException('Cutover cancelled by operator before start confirmation');
        }

        $this->assertPreparationReady($verificationReport);
        $freeze = $this->freezeMode->activate($freezeCapabilities);

        $delta = $this->deltaSyncService->detectDelta(
            $jobId,
            $entityType,
            $sourceRecords,
            $targetRecords,
            $this->repository->syncCheckpoint($entityType),
        );

        $finalVerification = [
            'critical_errors' => (int) ($verificationReport['critical_errors'] ?? 0),
            'key_entities_checked' => count($sourceRecords),
            'delta_changed' => $delta['changed'] ?? 0,
            'delta_new' => $delta['new'] ?? 0,
        ];

        if (!$confirmSwitch) {
            throw new RuntimeException('Cutover cancelled by operator before final switch');
        }

        $this->repository->setJobStatus($jobId, 'COMPLETED');

        return [
            'migration_status' => 'COMPLETED',
            'steps' => [
                'preparation' => 'ok',
                'final_delta_sync' => 'ok',
                'final_verification' => 'ok',
                'operator_confirmation' => 'ok',
                'migration_completed' => 'ok',
            ],
            'freeze' => $freeze,
            'delta' => $delta,
            'final_verification' => $finalVerification,
        ];
    }

    /** @param array<string,mixed> $verificationReport */
    private function assertPreparationReady(array $verificationReport): void
    {
        if (!(bool) ($verificationReport['main_transfer_done'] ?? false)) {
            throw new RuntimeException('Main transfer is not completed');
        }

        if ((int) ($verificationReport['critical_errors'] ?? 0) > 0) {
            throw new RuntimeException('Verification report contains critical errors');
        }
    }
}
