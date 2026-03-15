<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

use DateTimeImmutable;

final class RunbookGenerator
{
    /** @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public function generate(array $plan): array
    {
        $timeline = [
            ['minute' => 'T-60', 'action' => 'pre-start checklist'],
            ['minute' => 'T-30', 'action' => 'freeze checklist'],
            ['minute' => 'T-20', 'action' => 'final delta sync'],
            ['minute' => 'T-05', 'action' => 'switch actions'],
            ['minute' => 'T+05', 'action' => 'smoke tests'],
            ['minute' => 'T+30', 'action' => 'stabilization tasks'],
        ];

        return [
            'version' => (int) ($plan['currentVersion'] ?? 1),
            'generatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'goal' => $plan['goal'] ?? 'Production cutover',
            'scope' => $plan['scope'] ?? [],
            'roles' => $plan['operatorRoles'] ?? [],
            'timeline' => $timeline,
            'checklists' => [
                'beforeStart' => ['backups confirmed', 'approvals confirmed', 'preflight pass'],
                'freeze' => ['freeze notice', 'freeze confirmation', 'exception log open'],
                'switch' => ['switch DNS/integration', 'toggle target write mode'],
            ],
            'rollback' => [
                'triggers' => ['critical smoke failure', 'integrity hard fail'],
                'steps' => ['stop writes to target', 'activate source primary', 'reconcile divergence'],
            ],
            'communicationMessages' => ['T-1 day notice', 'freeze-start', 'go-live started', 'go-live completed'],
            'exportFormats' => ['json', 'pdf', 'docx'],
        ];
    }
}
