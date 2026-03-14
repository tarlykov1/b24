# Enterprise Security & Governance Plane

## Backend architecture

Implemented modules:
- `SecurityContext` and `AuthorizationDecision` for immutable auth context and explainable policy decision.
- `SecurityGovernanceService` with:
  - strict tenant/workspace scope guards
  - RBAC role-permission matrix
  - explicit deny rules and direct grants support in context
  - approval workflow with quorum/four-eyes/TTL
  - payload fingerprinting for approval invalidation on parameter change
  - append-only hash-chained audit events
  - break-glass sessions with TTL and labels
  - resource locks with handoff support
  - capability map endpoint for UI route/action guards.

## API surface

Added endpoints in `ui/admin/api.php`:
- `GET /security/governance`
- `GET /security/capabilities`
- `POST /security/authorize`
- `POST /approvals/submit`
- `POST /approvals/decide`
- `GET /audit/search`
- `POST /break-glass/activate`
- `POST /locks/acquire`
- `POST /locks/handoff`

`POST /jobs/action` now enforces authorization and approval token validation for risky actions (e.g., rollback).

## UI additions

Added governance screens in migration console:
- Security Hub
- Role Matrix
- Approval Queue
- Audit Explorer
- Session Security
- Policy Simulator
- Incident Review

Added sticky security context header with tenant/workspace/role state and workspace switcher.

## Database schema

Added enterprise schema `db/security_governance_schema.sql` with tables:
`tenants`, `workspaces`, `operators`, `roles`, `permissions`, `role_permissions`, `grants`, `policies`, `approval_requests`, `approval_decisions`, `audit_events`, `sessions`, `break_glass_sessions`, `resource_locks`, `security_incidents`.

Added seed file `db/security_seed.sql` with two tenants and multiple operator personas.

## Scenario coverage (A-H)

A. `MigrationOperator` can view jobs/logs but rollback now requires approval token and permission.

B. `Approver` receives request via approval queue and can approve/reject with four-eyes validation.

C. `SecurityAuditor` has audit/search capabilities but lacks runtime-changing permissions in RBAC matrix.

D. `TenantAdmin` scope is enforced by tenant/workspace guards.

E. `PlatformSuperAdmin` has wildcard permissions and all actions are still emitted into privileged audit trail.

F. Break-glass endpoint creates temporary elevated session with TTL and labels.

G. Resource locks allow first editor edit mode and concurrent operators read-only mode; supports handoff.

H. Approval token validation checks payload hash, invalidating approvals when action payload changes.

## Integration with migration control center/runtime

The security layer is integrated at API command boundary used by control center UI (`jobs/action` and governance endpoints), enabling consistent authorization, approvals, and audit traceability across migration operations and operator workflows.
