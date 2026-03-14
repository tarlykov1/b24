<?php

declare(strict_types=1);

namespace MigrationModule\Application\AuditDiscovery;

final class RiskEngine
{
    public function analyze(array $profile): array
    {
        $reasons = [];
        $score = 0;

        $tasks = (int) ($profile['tasks']['total'] ?? 0);
        $filesGb = (float) ($profile['files']['total_size_gb'] ?? 0.0);
        $orphanFiles = (int) ($profile['files']['orphan_files'] ?? 0);
        $inactiveOwners = (int) ($profile['permissions']['inactive_owners'] ?? 0);
        $attachments = (int) ($profile['tasks']['with_files'] ?? 0);

        if ($tasks > 200000) {
            $score += 4;
            $reasons[] = 'very large task volume';
        } elseif ($tasks > 50000) {
            $score += 2;
            $reasons[] = 'large task volume';
        }

        if ($filesGb > 500) {
            $score += 5;
            $reasons[] = 'very large file storage';
        } elseif ($filesGb > 100) {
            $score += 3;
            $reasons[] = 'large file storage';
        }

        if ($orphanFiles > 5000) {
            $score += 3;
            $reasons[] = 'many orphan files';
        }

        if ($inactiveOwners > 1000) {
            $score += 3;
            $reasons[] = 'many inactive owners detected';
        }

        if ($attachments > 30000) {
            $score += 2;
            $reasons[] = 'many task attachments';
        }

        $risk = match (true) {
            $score >= 8 => 'CRITICAL',
            $score >= 6 => 'HIGH',
            $score >= 3 => 'MEDIUM',
            default => 'LOW',
        };

        return ['risk_level' => $risk, 'risks' => $reasons, 'score' => $score];
    }
}
