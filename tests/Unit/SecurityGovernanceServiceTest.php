<?php

declare(strict_types=1);

use MigrationModule\Application\Security\SecurityContext;
use MigrationModule\Application\Security\SecurityGovernanceService;
use PHPUnit\Framework\TestCase;

final class SecurityGovernanceServiceTest extends TestCase
{
    public function testTenantIsolationIsEnforced(): void
    {
        $service = new SecurityGovernanceService();
        $ctx = new SecurityContext('operator-1', 'tenant-alpha', 'ws-core', 'project', 'production', ['TenantAdmin']);
        $decision = $service->authorize($ctx, 'jobs.view', ['tenantId' => 'tenant-bravo', 'workspaceId' => 'ws-green'], false);

        self::assertFalse($decision->allowed);
    }

    public function testRollbackRequiresApprovalAndPayloadBinding(): void
    {
        $service = new SecurityGovernanceService();
        $requester = new SecurityContext('operator-1', 'tenant-alpha', 'ws-prod', 'project', 'production', ['MigrationAdmin']);
        $approver = new SecurityContext('approver-1', 'tenant-alpha', 'ws-prod', 'project', 'production', ['Approver']);

        $approval = $service->submitApprovalRequest($requester, 'job.rollback', 'critical rollback', ['jobId' => 'job-1', 'mode' => 'destructive'], 'high');
        $decided = $service->decideApproval($approver, $approval['approvalId'], 'approve', 'approved');

        $valid = $service->validateApprovalToken($approval['approvalId'], $decided['approvalToken'], 'job.rollback', ['jobId' => 'job-1', 'mode' => 'destructive']);
        self::assertTrue($valid['valid']);

        $invalid = $service->validateApprovalToken($approval['approvalId'], $decided['approvalToken'], 'job.rollback', ['jobId' => 'job-1', 'mode' => 'changed']);
        self::assertFalse($invalid['valid']);
        self::assertSame('payload_hash_mismatch', $invalid['reason']);
    }

    public function testFourEyesPrincipleBlocksSelfApproval(): void
    {
        $service = new SecurityGovernanceService();
        $ctx = new SecurityContext('operator-1', 'tenant-alpha', 'ws-prod', 'project', 'production', ['Approver']);

        $approval = $service->submitApprovalRequest($ctx, 'integrity.repair.destructive', 'need repair', ['issueId' => 'iss-1'], 'critical');
        $result = $service->decideApproval($ctx, $approval['approvalId'], 'approve');

        self::assertSame('four_eyes_violation', $result['error']);
    }

    public function testResourceLockProvidesReadOnlyModeForSecondOperator(): void
    {
        $service = new SecurityGovernanceService();
        $first = new SecurityContext('operator-1', 'tenant-alpha', 'ws-core', 'project', 'staging', ['MigrationOperator']);
        $second = new SecurityContext('operator-2', 'tenant-alpha', 'ws-core', 'project', 'staging', ['MigrationOperator']);

        $a = $service->acquireLock($first, 'mapping', 'crm-contact');
        $b = $service->acquireLock($second, 'mapping', 'crm-contact');

        self::assertTrue($a['acquired']);
        self::assertFalse($b['acquired']);
        self::assertSame('read-only', $b['mode']);
    }
}
