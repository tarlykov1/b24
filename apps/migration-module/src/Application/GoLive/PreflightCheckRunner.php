<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

use DateTimeImmutable;

final class PreflightCheckRunner
{
    /** @param array<int,array{name:string,severity:string,result:bool,message:string}> $checks
     * @return array<string,mixed>
     */
    public function evaluate(array $checks, ?string $overrideReason = null, ?string $actorId = null): array
    {
        $failedCritical = array_values(array_filter($checks, static fn (array $c): bool => $c['severity'] === 'critical' && $c['result'] === false));
        $failedNonCritical = array_values(array_filter($checks, static fn (array $c): bool => $c['severity'] !== 'critical' && $c['result'] === false));

        $status = 'PASS';
        if ($failedCritical !== []) {
            $status = 'FAIL';
        } elseif ($failedNonCritical !== []) {
            $status = 'PASS_WITH_WARNINGS';
        }

        $override = null;
        if ($status === 'FAIL' && $overrideReason !== null && $actorId !== null) {
            $status = 'PASS_WITH_WARNINGS';
            $override = [
                'actorId' => $actorId,
                'reason' => $overrideReason,
                'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];
        }

        return [
            'status' => $status,
            'failedCritical' => $failedCritical,
            'failedWarnings' => $failedNonCritical,
            'override' => $override,
            'checkedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
