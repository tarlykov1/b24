<?php

declare(strict_types=1);

namespace MigrationModule\Application\Security;

final class AuthorizationDecision
{
    /** @param list<string> $reasons */
    public function __construct(
        public readonly bool $allowed,
        public readonly array $reasons,
        public readonly bool $approvalRequired,
        public readonly ?string $policyId = null,
        public readonly int $riskScore = 0,
    ) {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reasons' => $this->reasons,
            'approvalRequired' => $this->approvalRequired,
            'policyId' => $this->policyId,
            'riskScore' => $this->riskScore,
        ];
    }
}
