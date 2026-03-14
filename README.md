# Bitrix24 Migration Toolkit

Executable prototype for Bitrix24 migration workflows (CLI + SQLite runtime state + admin API/UI).

## Project Status / Maturity

**Current maturity:** **prototype with working end-to-end CLI flow**.

- **Implemented:** prototype runtime (`validate` → `dry-run` → `execute`/`resume` → `verify`/`report`/`status`) with SQLite persistence and stub adapters.  
- **Implemented (pilot hardening):** `system:check`, admin login + CSRF, `/health` and `/ready` endpoints, split config files.  
- **Partially implemented:** real Bitrix REST adapter (auto-enable via env), consistency/delta/reconciliation engines as application services.  
- **Prototype / stub-backed:** most “advanced” consistency/snapshot/reconciliation features are service-level building blocks and docs, but are **not exposed as top-level CLI commands** in `bin/migration-module`.  
- **Not production-ready yet:** no distributed workers, limited source/target coverage, best-effort conflict/manual-edit semantics.

## Architecture Overview

```text
Source (StubSourceAdapter | BitrixRestAdapter)
        |
        v
Extract batches -> plan/diff -> enqueue (SQLite queue)
        |
        v
PrototypeRuntime execute/resume
  - ID policy
  - user policy
  - retry / checkpoint / logs
        |
        v
Target (StubTargetAdapter | BitrixRestAdapter)
        |
        v
Verify/report/status

SQLite runtime state:
  jobs, queue, entity_map, user_map, checkpoint, diff, logs, integrity_issues, state
```

Additional consistency components exist as PHP services (`SnapshotConsistencyService`, `DeltaSyncEngine`, `ConflictDetectionEngine`, etc.), but they are currently **not wired into the primary CLI entrypoint as standalone commands**.

## What Actually Works

### Implemented

- CLI entrypoint: `bin/migration-module`.  
- Commands available in `help`:  
  `validate`, `plan`, `dry-run`, `execute`, `pause`, `resume`, `verify`, `verify-only`, `report`, `status`, `system:check`, plus `migration <subcommand>` helpers (`pause`, `resume`, `retry`, `repair`, `diff`).
- Prototype runtime pipeline:
  - config load from `migration.config.yml`
  - schema init + job creation
  - plan/diff calculation
  - queue processing with retry/permanent failure handling
  - checkpoint + entity mapping + structured logs
  - verification summary and status/report snapshots
- SQLite storage with real schema in `db/prototype_schema.sql`.
- Admin API hardening basics:
  - session login
  - CSRF check for POST
  - `/health` and `/ready`
  - security headers
- `system:check` command and API endpoint.

### Partially implemented

- **Real Bitrix REST adapter auto-enable:** enabled only when both `BITRIX_WEBHOOK_URL` and `BITRIX_WEBHOOK_TOKEN` are set; otherwise stubs are used.
- **Bitrix entity coverage:** adapter includes specific methods/maps and update-oriented upsert behavior; this is not full production migration coverage.
- **Consistency layer components:** snapshot/watermark/conflict/reconciliation classes exist and are testable as code units, but are not currently exposed in `bin/migration-module` as the advertised `snapshot:*`, `baseline:*`, `delta:*`, `conflicts:*`, etc.

### Prototype / Stub-backed

- Default source/target are stub adapters with deterministic fixture-like data.
- Many operations console endpoints return synthetic/demo data when no DB-backed path is implemented.
- Advanced reconciliation and policy orchestration is primarily service-level logic and docs-driven at this stage.

## Quick Start

```bash
php bin/migration-module validate
php bin/migration-module dry-run
php bin/migration-module execute
php bin/migration-module resume
php bin/migration-module verify
php bin/migration-module report
php bin/migration-module status
php bin/migration-module system:check
```

Default config file: `migration.config.yml`.

## CLI Commands

### Top-level commands (actually available)

```text
help
validate
plan
dry-run
execute
pause
resume
verify
verify-only
report
status
system:check
migration pause
migration resume
migration retry <entity_type>:<source_id>
migration repair
migration diff <entity_type>:<source_id>
```

### Commands often mentioned in docs but **not available** in `bin/migration-module`

These currently return `Unknown command` at CLI level:

- `snapshot:create`, `snapshot:show`
- `baseline:plan`, `baseline:execute`
- `reconciliation:run`
- `delta:plan`, `delta:execute`
- `verify:relations`, `verify:files`
- `conflicts:list`, `conflicts:resolve`
- `watermarks:show`, `state:inspect`, `orphans:list`, `repair:relations`

Related logic exists in `MigrationModule\Cli\MigrationCommands` and consistency services, but not wired into the default executable CLI yet.

## Runtime Modes / Phases

- `validate`: schema/bootstrap checks.
- `plan`: computes create/update/skip/conflict summary + diff rows.
- `dry-run`: plan + risk summary, no target writes.
- `execute`: queues and processes entities.
- `resume`: reruns executor with resume flag.
- `verify` / `verify-only`: summary checks (missing/changed + basic relation/file placeholders).
- `report` / `status`: aggregated counts from SQLite.

## Prototype Storage

Default SQLite path: `.prototype/migration.sqlite`.

Schema tables:

- `jobs`
- `queue`
- `entity_map`
- `user_map`
- `logs`
- `checkpoint`
- `diff`
- `integrity_issues`
- `state`

> Note: tables for `snapshots`, `snapshot_watermarks`, `conflicts`, `reconciliation_queue`, and extended verification models are **not present in the SQLite prototype schema**. Those concepts currently live in in-memory repository/services and documentation.

## Adapters

### Stub adapters (default)

- `StubSourceAdapter`: entities `users`, `crm`, `tasks`, `files`.
- `StubTargetAdapter`: in-memory upsert + existence checks.

### Real Bitrix REST adapter (auto-enable)

Activated when both env vars are present:

- `BITRIX_WEBHOOK_URL`
- `BITRIX_WEBHOOK_TOKEN`

Current adapter characteristics:

- Uses REST client with retry/backoff for retryable API errors.
- Supports mapped list/update methods for selected entity types.
- Best viewed as pilot integration layer, not full production migration adapter.

## Web UI (Admin Console)

Path: `apps/migration-module/ui/admin/index.php`.

What is real:

- login form (password hash from env)
- session-based auth
- CSRF token issuance and validation for POST in API
- simple runtime counters from SQLite (`jobs`, `queue`, `entity_map`, `diff`, `integrity_issues`)
- links to `system:check`, `/health`, `/ready`

What is prototype/demo:

- API surface includes multiple monitoring/control endpoints, but many responses are synthetic/mock-style.
- This is an operational prototype console, not a full production UI product.

## Production Hardening Additions

Implemented additions in current repo:

- `system:check` (CLI and admin API).
- Admin auth and CSRF guard.
- `/health` and `/ready` endpoints.
- Split config files:
  - `config/migration.php`
  - `config/runtime.php`
  - `config/bitrix.php`
- Security headers in admin API bootstrap.

## Snapshot Consistency / Delta Sync / Reconciliation

### What exists in code

- `SnapshotConsistencyService`
- `DeltaSyncEngine`
- `SyncPolicyEngine`
- `ConflictDetectionEngine`
- `ReconciliationQueueService`
- `RelationIntegrityEngine`
- `FileReconciliationService`
- `EntityStateMachine`

### Current integration level

- **Partially implemented:** these engines/services are present and describable.
- **Not fully wired to executable CLI runtime:** baseline/delta/reconciliation command workflow is not available via `bin/migration-module`.
- **Storage mismatch:** richer snapshot/conflict/reconciliation models are mostly in in-memory repository abstractions, not in the prototype SQLite schema.

## Conflict & Sync Policy Guarantees

- Conflict and policy engines provide explicit decisions/types in code.
- Manual target edit and conflict guarantees are **best-effort** in prototype semantics.
- No claim of strict no-overwrite guarantee should be treated as production-grade without additional audit trail/transactional controls.

## Tests

Run:

```bash
composer test
```

Current automated coverage (single prototype script) validates:

- config loading + `validate`
- plan/dry-run/execute/resume
- rerun skip behavior
- id conflict and user policy behavior
- verify summary shape
- schema/runtime consistency basics

It does **not** provide exhaustive production-level coverage of advanced consistency/reconciliation CLI flows.

## What changed recently

Verified recent additions reflected in codebase:

- production hardening primitives (`system:check`, admin auth/CSRF, `/health`, `/ready`)
- split runtime/migration/bitrix config files
- real Bitrix REST adapter auto-enable by env
- consistency-related service layer for snapshot/watermark/delta/reconciliation/conflict/policy

But also important:

- advanced command surface described in docs is still mostly service-level / not wired in the default CLI entrypoint.

## Known Prototype Limitations

- Not production-ready end-to-end.
- No distributed worker runtime.
- Limited real adapter coverage and migration semantics.
- Advanced consistency lifecycle is partially integrated.
- Some docs describe target architecture beyond currently executable CLI behavior.

## Documentation

- `STATUS.md`
- `docs/prototype-runtime.md`
- `docs/production.md`
- `docs/snapshot-delta-reconciliation.md`
- `docs/migration-operations-console.md`
- `docs/production-migration-guide.md`

Read docs as a mix of:

- current prototype behavior,
- pilot hardening guidance,
- and forward-looking architecture plans.

## Audit / Discovery (read-only)

Новый namespace CLI для безопасной pre-migration диагностики:

```bash
php bin/migration-module audit:run
php bin/migration-module audit:summary
php bin/migration-module audit:report
```

Артефакты:

- `.audit/migration_profile.json` (policy/planning input)
- `.audit/report.html` (human-readable report with risk flags/strategy hints)

Подробности: `docs/audit-discovery.md`.
