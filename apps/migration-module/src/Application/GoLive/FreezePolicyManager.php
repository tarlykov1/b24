<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

use DateTimeImmutable;

final class FreezePolicyManager
{
    /** @param array<string,mixed> $policy @param array<string,bool> $capabilities
     * @return array<string,mixed>
     */
    public function activate(array $policy, array $capabilities, string $actorId): array
    {
        $technicalLockAvailable = (bool) ($capabilities['technical_lock'] ?? false);
        $mode = $technicalLockAvailable ? 'technical_freeze' : 'operational_freeze';

        return [
            'mode' => $mode,
            'freezeType' => $policy['freezeType'] ?? 'partial',
            'lockedDomains' => $policy['domains'] ?? [],
            'businessExceptionAllowlist' => $policy['allowlist'] ?? [],
            'emergencyBypass' => [
                'enabled' => true,
                'actor' => $actorId,
                'auditRequired' => true,
            ],
            'notice' => [
                'preFreezeNoticeSent' => true,
                'countdownMin' => (int) ($policy['countdownMin'] ?? 30),
            ],
            'postCutoverReconciliation' => [
                'enabled' => true,
                'queue' => 'freeze_exception_reconcile',
            ],
            'activatedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
