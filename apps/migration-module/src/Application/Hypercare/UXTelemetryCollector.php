<?php

declare(strict_types=1);

namespace MigrationModule\Application\Hypercare;

final class UXTelemetryCollector
{
    /** @param list<array<string,mixed>> $events
     * @return array<string,mixed>
     */
    public function aggregate(array $events): array
    {
        $errors = [];
        $slow = [];
        foreach ($events as $event) {
            $type = (string) ($event['type'] ?? 'unknown');
            $errors[$type] = ($errors[$type] ?? 0) + 1;
            if (((int) ($event['duration_ms'] ?? 0)) > 1200) {
                $slow[] = $event;
            }
        }
        arsort($errors);

        return [
            'top_user_errors' => array_slice($errors, 0, 5, true),
            'top_slow_operations' => array_slice($slow, 0, 5),
            'event_count' => count($events),
        ];
    }
}
