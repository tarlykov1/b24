<?php

declare(strict_types=1);

namespace MigrationModule\ControlCenter\UI;

final class DashboardView
{
    /** @param array<string,mixed> $payload */
    public function render(array $payload): string
    {
        $timeline = '';
        foreach (($payload['timeline'] ?? []) as $stage) {
            $timeline .= sprintf('[%s] %s%s', strtoupper((string) $stage['stage']), (string) $stage['status'], PHP_EOL);
        }

        return sprintf(
            "JOB: %s\nPROGRESS: %s%%\nPROCESSED: %s\nREMAINING: %s\nERRORS: %s\nSPEED: %s\nETA: %s\nTIMELINE:\n%s",
            (string) ($payload['job'] ?? 'unknown'),
            (string) ($payload['progress'] ?? 0),
            (string) ($payload['processed'] ?? 0),
            (string) ($payload['remaining'] ?? 0),
            (string) ($payload['errors'] ?? 0),
            (string) ($payload['speed'] ?? '0 entities/min'),
            (string) ($payload['eta'] ?? '00:00:00'),
            $timeline,
        );
    }
}
