<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

final class SmokeTestRunner
{
    /** @param array<int,array{name:string,severity:string,pass:bool}> $checks
     * @return array<string,mixed>
     */
    public function evaluate(array $checks): array
    {
        $criticalFails = 0;
        $majorFails = 0;
        $minorFails = 0;

        foreach ($checks as $check) {
            if ((bool) $check['pass']) {
                continue;
            }

            match ($check['severity']) {
                'critical' => ++$criticalFails,
                'major' => ++$majorFails,
                default => ++$minorFails,
            };
        }

        $decision = 'accept_go_live';
        if ($criticalFails > 0) {
            $decision = 'rollback_candidate';
        } elseif ($majorFails > 0) {
            $decision = 'stabilize_or_partial_rollback';
        } elseif ($minorFails > 0) {
            $decision = 'accept_with_issues';
        }

        return [
            'criticalFails' => $criticalFails,
            'majorFails' => $majorFails,
            'minorFails' => $minorFails,
            'decision' => $decision,
        ];
    }
}
