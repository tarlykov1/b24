<?php

declare(strict_types=1);

namespace MigrationModule\Application\Security;

use DateInterval;
use DateTimeImmutable;

final class SecurityGovernanceService
{
    /** @var array<string,list<string>> */
    private array $rolePermissions = [
        'PlatformSuperAdmin' => ['*'],
        'TenantAdmin' => ['tenancy.workspace.manage', 'security.grants.manage', 'security.audit.view', 'jobs.view', 'jobs.operate', 'jobs.rollback.approve'],
        'SecurityAuditor' => ['security.audit.view', 'security.audit.export', 'incident.review.view', 'jobs.view', 'logs.view.masked'],
        'MigrationAdmin' => ['jobs.create', 'jobs.operate', 'jobs.rollback.execute', 'mappings.edit', 'diff.resolve', 'integrity.repair.run', 'logs.view'],
        'MigrationOperator' => ['jobs.view', 'jobs.start', 'jobs.pause', 'jobs.resume', 'jobs.retry', 'logs.view.masked', 'diff.view', 'integrity.view'],
        'ReadOnlyObserver' => ['jobs.view', 'logs.view.masked', 'diff.view', 'integrity.view', 'dashboard.view'],
        'Approver' => ['approvals.submit', 'approvals.approve', 'approvals.reject', 'jobs.rollback.approve', 'integrity.repair.approve'],
        'SupportEngineer' => ['jobs.view', 'logs.view.sanitized', 'incident.review.view', 'sessions.view'],
        'IncidentResponder' => ['incident.review.view', 'incident.review.export', 'breakglass.activate', 'security.audit.view', 'logs.view'],
    ];

    /** @var array<string,list<string>> */
    private array $highRiskActionPermissions = [
        'job.rollback' => ['jobs.rollback.execute'],
        'job.replay.overwrite' => ['jobs.replay.overwrite'],
        'integrity.repair.destructive' => ['integrity.repair.run'],
        'job.execute.production' => ['jobs.operate'],
        'mapping.force_remap_ids' => ['mappings.edit'],
        'runtime.throttle.override' => ['workers.runtime.manage'],
        'audit.export.sensitive' => ['security.audit.export'],
    ];

    /** @var array<string,array<string,mixed>> */
    private static array $approvalRequests = [];

    /** @var list<array<string,mixed>> */
    private static array $auditEvents = [];

    /** @var array<string,array<string,mixed>> */
    private static array $locks = [];

    /** @var array<string,array<string,mixed>> */
    private static array $breakGlassSessions = [];

    /** @var array<string,mixed> */
    private array $policy = [
        'mandatoryApprovalForProd' => true,
        'mandatoryApprovalForRollback' => true,
        'restrictedHoursWindow' => ['start' => '07:00', 'end' => '22:00'],
        'quorumForCritical' => 2,
        'maxConcurrencyByRole' => ['MigrationOperator' => 2, 'MigrationAdmin' => 5, 'PlatformSuperAdmin' => 20],
    ];

    public function authorize(SecurityContext $context, string $permission, array $resourceScope, bool $isHighRisk = false): AuthorizationDecision
    {
        if (($resourceScope['tenantId'] ?? $context->tenantId) !== $context->tenantId && !$this->hasPermission($context, '*')) {
            return new AuthorizationDecision(false, ['Tenant isolation policy denied access.'], false, 'tenant_isolation', 100);
        }

        if (($resourceScope['workspaceId'] ?? $context->workspaceId) !== $context->workspaceId && !$this->hasPermission($context, '*')) {
            return new AuthorizationDecision(false, ['Workspace scope denied access.'], false, 'workspace_scope', 80);
        }

        if ($this->isDeniedByRule($context, $permission)) {
            return new AuthorizationDecision(false, ['Explicit deny rule matched request.'], false, 'deny_rule', 90);
        }

        if (!$this->hasPermission($context, $permission) && !$this->hasPermission($context, '*')) {
            return new AuthorizationDecision(false, ['Role matrix does not grant requested permission.'], false, 'rbac_matrix', 70);
        }

        $approvalRequired = $isHighRisk || $this->requiresApprovalByPolicy($permission, (string) ($resourceScope['environment'] ?? $context->environment));

        return new AuthorizationDecision(true, ['Access granted by RBAC and scope guards.'], $approvalRequired, 'rbac_scope_policy', $approvalRequired ? 75 : 15);
    }

    public function requiresApprovalByPolicy(string $permission, string $environment): bool
    {
        if ($environment === 'production' && $this->policy['mandatoryApprovalForProd'] === true) {
            return in_array($permission, ['jobs.operate', 'jobs.rollback.execute', 'integrity.repair.run'], true);
        }

        return $permission === 'jobs.rollback.execute' && $this->policy['mandatoryApprovalForRollback'] === true;
    }

    /** @param array<string,mixed> $payload */
    public function submitApprovalRequest(SecurityContext $context, string $actionType, string $reason, array $payload, string $risk = 'medium'): array
    {
        $payloadHash = $this->fingerprintPayload($actionType, $payload);
        $id = 'apr-' . substr(hash('sha256', $context->actorId . microtime(true) . $actionType), 0, 16);
        $expiresAt = (new DateTimeImmutable('+30 minutes'))->format(DATE_ATOM);
        $quorum = $risk === 'critical' ? (int) $this->policy['quorumForCritical'] : 1;

        self::$approvalRequests[$id] = [
            'approvalId' => $id,
            'tenantId' => $context->tenantId,
            'workspaceId' => $context->workspaceId,
            'actionType' => $actionType,
            'payloadHash' => $payloadHash,
            'risk' => $risk,
            'reason' => $reason,
            'status' => 'pending',
            'requestedBy' => $context->actorId,
            'quorumRequired' => $quorum,
            'decisions' => [],
            'createdAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'expiresAt' => $expiresAt,
        ];

        return self::$approvalRequests[$id];
    }

    public function decideApproval(SecurityContext $context, string $approvalId, string $decision, ?string $comment = null): array
    {
        $request = self::$approvalRequests[$approvalId] ?? null;
        if ($request === null) {
            return ['error' => 'not_found'];
        }

        if ($request['requestedBy'] === $context->actorId) {
            return ['error' => 'four_eyes_violation', 'message' => 'Initiator cannot approve own critical action.'];
        }

        if (!$this->hasPermission($context, 'approvals.approve') && !$this->hasPermission($context, '*')) {
            return ['error' => 'forbidden'];
        }

        $request['decisions'][] = [
            'actorId' => $context->actorId,
            'decision' => $decision,
            'comment' => $comment,
            'at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        $approvedCount = count(array_filter($request['decisions'], static fn (array $d): bool => $d['decision'] === 'approve'));
        if ($decision === 'reject') {
            $request['status'] = 'rejected';
        } elseif ($approvedCount >= (int) $request['quorumRequired']) {
            $request['status'] = 'approved';
            $request['approvalToken'] = 'apt-' . substr(hash('sha256', $approvalId . '-token'), 0, 24);
        }

        self::$approvalRequests[$approvalId] = $request;

        return $request;
    }

    /** @param array<string,mixed> $payload */
    public function validateApprovalToken(string $approvalId, ?string $token, string $actionType, array $payload): array
    {
        $request = self::$approvalRequests[$approvalId] ?? null;
        if ($request === null) {
            return ['valid' => false, 'reason' => 'approval_not_found'];
        }

        if (($request['status'] ?? '') !== 'approved') {
            return ['valid' => false, 'reason' => 'approval_not_approved'];
        }

        if (($request['approvalToken'] ?? '') !== $token) {
            return ['valid' => false, 'reason' => 'invalid_token'];
        }

        if ((new DateTimeImmutable($request['expiresAt'])) < new DateTimeImmutable()) {
            return ['valid' => false, 'reason' => 'expired'];
        }

        $payloadHash = $this->fingerprintPayload($actionType, $payload);
        if ($payloadHash !== $request['payloadHash']) {
            return ['valid' => false, 'reason' => 'payload_hash_mismatch'];
        }

        return ['valid' => true, 'approvalReference' => $approvalId, 'risk' => $request['risk']];
    }

    /** @param array<string,mixed> $event */
    public function appendAuditEvent(array $event): array
    {
        $prevHash = self::$auditEvents === [] ? 'GENESIS' : (string) end(self::$auditEvents)['hash'];
        $record = array_merge($event, [
            'eventId' => $event['eventId'] ?? 'aud-' . substr(hash('sha256', json_encode($event) . microtime(true)), 0, 20),
            'timestamp' => $event['timestamp'] ?? (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $payload = json_encode($record, JSON_THROW_ON_ERROR);
        $record['prevHash'] = $prevHash;
        $record['hash'] = hash('sha256', $prevHash . '|' . $payload);
        self::$auditEvents[] = $record;

        return $record;
    }

    /** @return list<array<string,mixed>> */
    public function searchAudit(array $filters = []): array
    {
        return array_values(array_filter(self::$auditEvents, static function (array $event) use ($filters): bool {
            if (($filters['actorId'] ?? null) !== null && $event['actorId'] !== $filters['actorId']) {
                return false;
            }
            if (($filters['tenantId'] ?? null) !== null && $event['tenantId'] !== $filters['tenantId']) {
                return false;
            }
            if (($filters['actionType'] ?? null) !== null && $event['actionType'] !== $filters['actionType']) {
                return false;
            }

            return true;
        }));
    }

    public function requestBreakGlass(SecurityContext $context, string $reason, int $ttlMinutes = 30): array
    {
        $id = 'bg-' . substr(hash('sha256', $context->actorId . $reason . microtime(true)), 0, 16);
        $record = [
            'sessionId' => $id,
            'actorId' => $context->actorId,
            'tenantId' => $context->tenantId,
            'reason' => $reason,
            'status' => 'active',
            'startedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            'expiresAt' => (new DateTimeImmutable())->add(new DateInterval('PT' . $ttlMinutes . 'M'))->format(DATE_ATOM),
            'labels' => ['break-glass', 'privileged'],
        ];
        self::$breakGlassSessions[$id] = $record;

        return $record;
    }

    public function expireBreakGlassSessions(): int
    {
        $expired = 0;
        foreach (self::$breakGlassSessions as $id => $session) {
            if ($session['status'] === 'active' && (new DateTimeImmutable($session['expiresAt'])) < new DateTimeImmutable()) {
                self::$breakGlassSessions[$id]['status'] = 'expired';
                ++$expired;
            }
        }

        return $expired;
    }

    public function acquireLock(SecurityContext $context, string $resourceType, string $resourceId): array
    {
        $key = $resourceType . ':' . $resourceId;
        $lock = self::$locks[$key] ?? null;
        if ($lock !== null && $lock['ownerActorId'] !== $context->actorId) {
            return ['acquired' => false, 'mode' => 'read-only', 'currentOwner' => $lock['ownerActorId'], 'lock' => $lock];
        }

        self::$locks[$key] = [
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'ownerActorId' => $context->actorId,
            'tenantId' => $context->tenantId,
            'workspaceId' => $context->workspaceId,
            'acquiredAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        return ['acquired' => true, 'mode' => 'edit', 'lock' => self::$locks[$key]];
    }

    public function handoffLock(SecurityContext $context, string $resourceType, string $resourceId, string $toActorId): array
    {
        $key = $resourceType . ':' . $resourceId;
        $lock = self::$locks[$key] ?? null;
        if ($lock === null || $lock['ownerActorId'] !== $context->actorId) {
            return ['ok' => false, 'reason' => 'not_lock_owner'];
        }

        self::$locks[$key]['ownerActorId'] = $toActorId;
        self::$locks[$key]['handoffAt'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return ['ok' => true, 'lock' => self::$locks[$key]];
    }

    /** @return array<string,mixed> */
    public function capabilityMap(SecurityContext $context, array $resourceScope): array
    {
        $permissions = [
            'jobs.rollback.execute', 'jobs.operate', 'logs.view', 'logs.view.masked', 'security.audit.view', 'integrity.repair.run', 'approvals.approve',
        ];

        $map = [];
        foreach ($permissions as $permission) {
            $map[$permission] = $this->authorize($context, $permission, $resourceScope, in_array($permission, ['jobs.rollback.execute', 'integrity.repair.run'], true))->toArray();
        }

        return [
            'actorId' => $context->actorId,
            'tenantId' => $context->tenantId,
            'workspaceId' => $context->workspaceId,
            'roles' => $context->roles,
            'breakGlassActive' => $context->breakGlassActive,
            'capabilities' => $map,
        ];
    }

    /** @return array<string,mixed> */
    public function governanceOverview(SecurityContext $context): array
    {
        return [
            'tenants' => [
                ['tenantId' => 'tenant-alpha', 'workspaces' => ['ws-core', 'ws-prod']],
                ['tenantId' => 'tenant-bravo', 'workspaces' => ['ws-green']],
            ],
            'roleMatrix' => $this->rolePermissions,
            'approvalQueue' => array_values(self::$approvalRequests),
            'activeLocks' => array_values(self::$locks),
            'breakGlassSessions' => array_values(self::$breakGlassSessions),
            'auditEventsTotal' => count(self::$auditEvents),
            'currentContext' => [
                'actorId' => $context->actorId,
                'tenantId' => $context->tenantId,
                'workspaceId' => $context->workspaceId,
                'environment' => $context->environment,
                'roles' => $context->roles,
            ],
        ];
    }

    /** @return list<string> */
    public function roles(): array
    {
        return array_keys($this->rolePermissions);
    }

    private function hasPermission(SecurityContext $context, string $permission): bool
    {
        if (($context->directGrants[$permission] ?? false) === true) {
            return true;
        }

        foreach ($context->roles as $role) {
            $permissions = $this->rolePermissions[$role] ?? [];
            if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    private function isDeniedByRule(SecurityContext $context, string $permission): bool
    {
        if (($context->denyRules[$permission] ?? false) === true) {
            return true;
        }

        return false;
    }

    /** @param array<string,mixed> $payload */
    private function fingerprintPayload(string $actionType, array $payload): string
    {
        ksort($payload);

        return hash('sha256', $actionType . '|' . json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
