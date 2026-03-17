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
- MySQL runtime state (jobs, queue, mapping, checkpoints, logs, diff, integrity, state)
- Audit/discovery integration (`audit:*`)
- Delta planning/execution (`delta:*`) including /upload baseline reuse and cutover checks
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
- Delta sync: `delta:plan`, `delta:execute`, `delta:scan`, `delta:report`, `delta:resume`, `delta:cutover-check`
- Baseline /upload reuse: `baseline:index`, `baseline:status`, `baseline:verify`
- Verification: `verify:counts`, `verify:relations`, `verify:integrity`, `verify:files`
- Cutover finalization: `cutover:prepare`, `cutover:readiness`, `cutover:arm`, `cutover:freeze:start`, `cutover:freeze:status`, `cutover:delta:final`, `cutover:verify`, `cutover:verdict`, `cutover:abort`, `cutover:resume`, `cutover:complete`

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


## Baseline /upload reuse and delta sync engine
For production migrations where `/upload` has already been copied once outside runtime, use:
1. `baseline:index` to register imported target `/upload` as baseline manifest.
2. `delta:scan` to classify NEW/MODIFIED/MISSING_ON_TARGET/TARGET_ONLY/CONFLICT/UNCHANGED_REUSABLE.
3. `delta:plan --scan-id=<id>` to generate transfer actions (REUSE/COPY/VERIFY/REPLACE/SKIP/QUARANTINE/MANUAL_REVIEW).
4. `delta:execute --plan-id=<id>` (or `delta:resume`) to apply resumable transfer batches.
5. `delta:cutover-check --scan-id=<id>` for final readiness verdict before go-live.

Safety defaults:
- No blanket overwrite of `/upload`.
- No deletion of target-only files by default.
- Referenced files are prioritized during cutover risk evaluation.

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
- `docs/cutover-finalization.md`
- `docs/future-platform.md`


## Cutover Finalization (Delta Freeze Window)
For final production switch from old source portal to new clean target portal, runtime now provides a persistent freeze-window orchestration layer.

Key guarantees:
- Deterministic state machine with durable transitions.
- Resume-safe phases (freeze, delta capture, verification).
- Structured readiness, mutation journal, verification, and verdict outputs.
- Honest freeze semantics: mutation detection + policy enforcement, not an implicit global Bitrix write lock.

See full operator guide: `docs/cutover-finalization.md`.

Compatibility boundary note: repository still contains legacy cutover/orchestrator artifacts for backward compatibility, but freeze-window finalization (`cutover:*`) is the authoritative path for final go-live gating.


## Runtime storage backend
- SQLite is no longer supported.
- Configure only MySQL via `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET`, `DB_COLLATION`.
- Runtime and installer both require PDO MySQL and external MySQL connectivity.


## Offline portable deployment
- Target mode: portable archive, no internet access.
- Deployment does **not** require runtime package installs (`composer install` / `npm install` are build-time only, outside target server).
- Runtime storage is MySQL-only (`pdo_mysql`), configured through:
  `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_CHARSET`, `DB_COLLATION`.
- Installer and runtime share the same DB config source and schema (`db/mysql_platform_schema.sql`).
- Readiness check command: `php bin/migration-module deployment:check`.
