# Bitrix24 Migration Runtime

## Project purpose
This repository provides a **stable migration runtime** for one practical scenario: moving structured business data between Bitrix24 on‑premise portals while preserving mapping, dependencies, and resumability.

## Supported migration scenario
- **Source:** production Bitrix24 On‑Premise portal.
- **Target:** new clean Bitrix24 On‑Premise portal.
- Target may contain a small amount of removable test data (test users/entities/tasks).
- Target infrastructure must remain intact (server/cluster/system modules are out of scope).
- Bulk file payload transfer can be performed outside this runtime (filesystem-level copy).
- Runtime scope focuses on structured data migration, mapping integrity, checkpoint/resume, delta sync, and verification.

## Architecture overview
Core runtime components:
- CLI runtime (`bin/migration-module`)
- Deterministic execution engine (`PrototypeRuntime` + execution graph/batches)
- SQLite runtime state (jobs, queue, mapping, checkpoints, logs, diff, integrity, state)
- Audit/discovery integration (`audit:*`)
- Delta planning/execution (`delta:*`)
- Verification (`verify:*` + report/status)
- Bitrix REST adapter support when webhook credentials are provided.

Future platform capabilities are intentionally isolated and not exposed in the runtime CLI.

## Quick start
```bash
php bin/migration-module validate
php bin/migration-module config:validate --config=migration.config.yml
JOB_ID=$(php bin/migration-module create-job | jq -r .job_id)
php bin/migration-module plan --job-id=$JOB_ID
php bin/migration-module dry-run --job-id=$JOB_ID
php bin/migration-module execute --job-id=$JOB_ID
```

## CLI commands
- Lifecycle: `validate`, `config:validate`, `create-job`, `plan`, `plan:show`, `plan:export`, `dry-run`, `execute`, `resume`, `pause`, `retry`, `status`, `verify`, `report`
- Audit/discovery: `audit:run`, `audit:summary`, `audit:report`, `audit:velocity`
- Target preparation: `target:inspect`, `target:cleanup-plan`, `target:cleanup-execute`
- Delta sync: `delta:plan`, `delta:execute`
- Verification: `verify:counts`, `verify:relations`, `verify:integrity`, `verify:files`

Unsupported platform commands are explicitly reserved for future extensions.

## Migration phases
1. Discovery and audit
2. Target inspection and cleanup planning
3. Baseline migration planning + dry run
4. Deterministic execution
5. Delta sync pass
6. Verification and reporting

## Delta sync
`delta:plan` computes changed/new entities after initial execution and queues deltas.
`delta:execute` applies pending delta operations and marks them applied.

## Verification
- `verify:counts`: runtime count consistency
- `verify:relations`: relation checks (task→user, deal→company/contact, comment→entity)
- `verify:integrity`: combined relation/integrity view
- `verify:files`: file metadata/reference checks only; no heavy payload transfer

## Limitations
- No destructive cleanup via platform SQL in this runtime; cleanup execution is intentionally safe metadata-only.
- Bulk file transfer is out of scope.
- Enterprise upgrade/reconciliation orchestration is moved to future platform scope.

## Documentation links
- `docs/architecture.md`
- `docs/migration-runtime.md`
- `docs/audit-discovery.md`
- `docs/delta-sync.md`
- `docs/verification.md`
- `docs/future-platform.md`
