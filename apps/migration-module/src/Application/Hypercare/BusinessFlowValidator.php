<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class BusinessFlowValidator
{
    /** @param list<array<string,mixed>> $flows
     * @return array<string,mixed>
     */
    public function validate(array $flows): array
    {
        $checks = [];
        $failed = [];
        foreach ($flows as $flow) {
            $ok = (bool) ($flow['triggered'] ?? false) && (bool) ($flow['completed'] ?? false);
            $checks[] = ['name' => (string) ($flow['name'] ?? 'unknown'), 'ok' => $ok];
            if (!$ok) {
                $failed[] = ['name' => (string) ($flow['name'] ?? 'unknown'), 'reason' => (string) ($flow['reason'] ?? 'not_triggered')];
            }
        }

        return ['checks' => $checks, 'failed' => $failed, 'flow_health_score' => round((count($checks) - count($failed)) / max(1, count($checks)), 4)];
    }
}
