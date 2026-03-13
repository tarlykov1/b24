# Bitrix24 Enterprise Migration Toolkit (Skeleton)

Production-grade, resumable migration toolkit skeleton for migrating a live on-premise Bitrix24 Enterprise portal to a new on-premise Bitrix24 Enterprise portal.

## Scope of this step

This commit delivers architecture scaffolding only:
- directory structure
- namespaces and module boundaries
- database schema for migration state
- service skeletons
- CLI skeleton commands
- basic admin UI skeleton
- known Bitrix-specific uncertainties

No full migration logic is implemented yet.

## Architecture

## Part A: Export Agent (source portal)
Path: `apps/export-agent`

Responsibilities:
- safe/throttled batch export
- checkpoint-aware delta extraction
- filesystem export manifests
- source-safe behavior (read-only operations)

Design principles:
- isolate Bitrix calls in adapters
- stateless workers + persisted checkpoints
- explicit batch contracts and idempotent export chunks

## Part B: Migration Module (target portal)
Path: `apps/migration-module`

Responsibilities:
- queue lifecycle and worker orchestration
- idempotent upsert/mapping strategy
- diff-first behavior for repeated runs
- pause/resume/soft-stop controls
- verification and reporting

Design principles:
- mapping-first relation restoration
- stable job lifecycle state machine
- automations imported disabled by default
- source ID preservation attempt with conflict-safe remap fallback

## Run modes
- `initial_load`
- `incremental_sync`
- `delta_sync`
- `reconciliation`
- `verification`

## Job lifecycle
- `draft`
- `ready`
- `running`
- `pausing`
- `paused`
- `resuming`
- `stopping`
- `stopped`
- `completed`
- `failed`
- `verification_required`
- `verified`

## Database schema
See `db/migration_schema.sql` for:
- `migration_entity_map`
- `migration_user_map`
- `migration_queue`
- `migration_job`
- `migration_log`
- `migration_checkpoint`
- `migration_diff`

## CLI skeleton commands
### Export agent
- `export:preflight`
- `export:audit`
- `export:batch`
- `export:delta`

### Migration module
- `migration:preflight`
- `migration:audit`
- `migration:job:create`
- `migration:job:start`
- `migration:job:pause`
- `migration:job:resume`
- `migration:job:stop`
- `migration:diff`
- `migration:verify`

## Admin UI skeleton
Path: `apps/migration-module/ui/admin/index.php`

Provides placeholders for:
- preflight status
- audit summary
- job control actions
- diff approval gate
- verification status

## Safety and idempotency strategy
- enqueue immutable work units with deterministic deduplication keys
- checkpoint progression only after durable writes
- retries with backoff and adaptive throttling signals
- no destructive source operations
- target writes guarded by mapping + conflict handling

## Next step (implementation)
1. implement adapters for Bitrix REST/DB/filesystem
2. implement preflight checks and report writers
3. implement audit inventory collectors
4. implement queue workers + throttler feedback loop
5. implement per-entity migrators with mapping enforcement
6. implement verification suite and markdown/json reports

## Known uncertainties
See `docs/bitrix-uncertainties.md`.
