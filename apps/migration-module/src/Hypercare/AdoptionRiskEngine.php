<?php

declare(strict_types=1);

namespace MigrationModule\Hypercare;

final class AdoptionRiskEngine
{
    public function analyze(array $departmentActivity): array
    {
        $reports = [];
        foreach ($departmentActivity as $department => $activity) {
            $crmDrop = (float) ($activity['crm_usage_drop_pct'] ?? 0.0);
            $taskDrop = (float) ($activity['task_activity_drop_pct'] ?? 0.0);
            if ($crmDrop < 20.0 && $taskDrop < 20.0) {
                continue;
            }

            $reports[] = [
                'report_id' => 'risk_' . substr(hash('sha256', $department), 0, 10),
                'department' => $department,
                'summary' => sprintf('%s — CRM usage dropped %.1f%%, tasks down %.1f%%', $department, $crmDrop, $taskDrop),
                'severity' => ($crmDrop >= 35.0 || $taskDrop >= 50.0) ? 'high' : 'medium',
                'generated_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
            ];
        }

        return ['adoption_risk_reports' => $reports];
    }
}
