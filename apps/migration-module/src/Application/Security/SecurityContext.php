<?php

declare(strict_types=1);

namespace MigrationModule\Application\Security;

final class SecurityContext
{
    /**
     * @param list<string> $roles
     * @param array<string,bool> $directGrants
     * @param array<string,bool> $denyRules
     */
    public function __construct(
        public readonly string $actorId,
        public readonly string $tenantId,
        public readonly string $workspaceId,
        public readonly string $projectId,
        public readonly string $environment,
        public readonly array $roles,
        public readonly array $directGrants = [],
        public readonly array $denyRules = [],
        public readonly bool $breakGlassActive = false,
    ) {
    }
}
