<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Config;

final class JobSettings
{
    public function __construct(
        public readonly string $mode,
        public readonly bool $dryRun,
        public readonly string $speedProfile,
        public readonly ?string $inactiveUserCutoffDate,
        public readonly string $inactiveUserPolicy,
        public readonly ?string $systemAccountId,
        public readonly int $batchSize,
        public readonly int $pauseBetweenBatchesMs,
        public readonly int $sourceRpm,
        public readonly int $targetRpm,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        return new self(
            (string) ($input['mode'] ?? RunMode::INITIAL_IMPORT),
            (bool) ($input['dry_run'] ?? false),
            (string) ($input['speed_profile'] ?? 'balanced'),
            isset($input['inactive_user_cutoff_date']) ? (string) $input['inactive_user_cutoff_date'] : null,
            (string) ($input['inactive_user_policy'] ?? InactiveUserPolicy::KEEP_USER),
            isset($input['system_account_id']) ? (string) $input['system_account_id'] : null,
            (int) ($input['batch_size'] ?? 50),
            (int) ($input['pause_between_batches_ms'] ?? 750),
            (int) ($input['source_rpm'] ?? 30),
            (int) ($input['target_rpm'] ?? 30),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'dry_run' => $this->dryRun,
            'speed_profile' => $this->speedProfile,
            'inactive_user_cutoff_date' => $this->inactiveUserCutoffDate,
            'inactive_user_policy' => $this->inactiveUserPolicy,
            'system_account_id' => $this->systemAccountId,
            'batch_size' => $this->batchSize,
            'pause_between_batches_ms' => $this->pauseBetweenBatchesMs,
            'source_rpm' => $this->sourceRpm,
            'target_rpm' => $this->targetRpm,
        ];
    }
}
