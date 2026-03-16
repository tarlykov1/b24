# Migration Toolkit Status

- ✅ Stabilization baseline: strict job lifecycle contract with explicit `job_id` usage.
- ✅ Canonical runtime truth hardening in repository/storage APIs (`job_not_found`, transition validation).
- ✅ Real/demo separation for operations console (`demo_mode` gate, no fake telemetry in real mode).
- ✅ Verify semantics hardened (`verify_depth`, explicit checked/not_checked/limitations).
- ✅ Added integration smoke tests for lifecycle and verification depth semantics.
- ⏳ Production adapter coverage expansion (intentionally deferred until baseline hardening complete).
- ⏳ Distributed runtime and full orchestration hardening.


## 2026-Deterministic Engine Update

- Added deterministic execution engine core (plan builder, graph, scheduler, state store, checkpoint manager, replay guard, id reservation, failure classification, retry policy).
- Extended prototype schema with strict execution/state tables for resumable idempotent runs.
- Extended CLI with `config:validate`, `plan:show`, `plan:export`, `retry`, `reconcile`, `checkpoint:*`, `failures:list`, `files:verify`, `mapping:export`.
- Added unit/integration coverage for stable hashing/order, ID conflict policy, replay protection, checkpoint transitions and execute→resume semantics.

## 2026 MySQL-first Deployment & Installer Layer

- ✅ Added MySQL platform schema (`db/mysql_platform_schema.sql`) with control-plane/runtime/install/audit/delta/cutover/hypercare tables.
- ✅ Added migration runner with migration lock, checksum tracking, idempotent apply and status endpoints.
- ✅ Added install validation engine with hard safety guards (platform schema overlap, source/target identity, unsafe paths, aggressive resources warning).
- ✅ Added CLI install/database commands: `install:check`, `install:init-db`, `install:generate-config`, `install:validate`, `install:report`, `install:apply`, `config:lint`, `db:migrate`, `db:status`.
- ✅ Added safe installation wizard UI scaffold (`install.php`) wired to API validation and config generation endpoints.
- ✅ Added production/safe-lab MySQL-first config templates and co-located deployment examples (systemd + nginx).
- ✅ Added operator/deployment/troubleshooting/schema documentation for production-focused target-server install.
- ⚠️ SQLite-backed prototype runtime remains for backward compatibility paths and should be considered non-production.
