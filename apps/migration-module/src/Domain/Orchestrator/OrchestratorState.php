<?php

declare(strict_types=1);

namespace MigrationModule\Domain\Orchestrator;

final class OrchestratorState
{
    public const INIT = 'INIT';
    public const PRECHECK = 'PRECHECK';
    public const DISCOVERY = 'DISCOVERY';
    public const PLAN_GENERATION = 'PLAN_GENERATION';
    public const DRY_RUN = 'DRY_RUN';
    public const WAITING_CONFIRMATION = 'WAITING_CONFIRMATION';
    public const EXECUTING = 'EXECUTING';
    public const THROTTLED = 'THROTTLED';
    public const PAUSED = 'PAUSED';
    public const DELTA_SYNC = 'DELTA_SYNC';
    public const RECONCILIATION = 'RECONCILIATION';
    public const SELF_HEALING = 'SELF_HEALING';
    public const PARTIAL_BLOCK = 'PARTIAL_BLOCK';
    public const SAFE_STOPPED = 'SAFE_STOPPED';
    public const ROLLBACK_PARTIAL = 'ROLLBACK_PARTIAL';
    public const COMPLETED = 'COMPLETED';
    public const COMPLETED_WITH_WARNINGS = 'COMPLETED_WITH_WARNINGS';
    public const FAILED = 'FAILED';

    /** @return array<int,string> */
    public static function terminal(): array
    {
        return [
            self::COMPLETED,
            self::COMPLETED_WITH_WARNINGS,
            self::FAILED,
            self::SAFE_STOPPED,
            self::ROLLBACK_PARTIAL,
        ];
    }
}
