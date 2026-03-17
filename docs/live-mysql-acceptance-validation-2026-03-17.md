# Live MySQL acceptance validation (2026-03-17)

## Scope
Final acceptance validation for offline MySQL-only success-path after previous code-level fixes.

## Environment used
- Host: current CI/container runtime (`/workspace/b24`).
- PHP: `8.5.3-dev` with `pdo_mysql` available.
- Canonical runtime config generated at `config/generated-install-config.json` via installer-compatible endpoint `/install/generate-config`.
- MySQL target config used for validation:
  - host `127.0.0.1`
  - port `3306`
  - db `bitrix_migration_live`
  - user `bitrix_migration`

## Step 1 — canonical config + runtime/CLI coherence
Installer-compatible generation:
- `POST /api.php/install/generate-config` returned `ok: true` and wrote canonical config file.

Observed coherence:
- `deployment:check` and installer `/install/check-connection` both resolved same host/port (`127.0.0.1:3306`) and produced identical network failure class (`Connection refused`), confirming runtime and installer use the same effective MySQL config source.

## Step 2 — deployment:check success-path on live MySQL
Command:
- `php bin/migration-module deployment:check`

Result:
- Structured response returned (JSON) with checks map and errors.
- `ok=false`, `status=fail`, `code=system_check_failed`.
- Exit code: `2`.
- Checks:
  - `pdo_mysql=true`
  - `mysql_tcp_reachable=false`
  - `mysql_select_1=false`
  - `mysql_schema_permissions=false`

Conclusion:
- **Success-path NOT confirmed** because live reachable MySQL was not available.

## Step 3 — installer/runtime success-path
Installer calls:
- `/install/check-connection` returned structured fail (same MySQL refusal).
- `/install/init-schema` failed with HTTP 500 due PDO connect refusal before schema bootstrap.

Entrypoint reload check:
- `GET /` via `web/index.php` failed with fatal autoload error when `vendor/autoload.php` is absent:
  - `Class "MigrationModule\Support\DbConfig" not found`.

Conclusion:
- Installer success-path (check-connection → init-schema → run without installer) not completed in this environment.

## Step 4 — MySQL lifecycle smoke
Command:
- `php bin/migration-module create-job`

Result:
- Fatal PDO connection refusal, exit code `255`.
- `job_id` not created, therefore `status --job-id` smoke cannot proceed.

## Step 5 — schema/runtime coherence (live DB)
Could not execute due unavailable reachable MySQL.
Not validated live:
- init-schema idempotence
- runtime SQL over queue/checkpoints/entity_map/logs/delta/reconciliation/cutover summary paths

## Defects/blockers observed during validation
1. **BLOCKER (environment/runtime):** no reachable MySQL endpoint at configured `127.0.0.1:3306` for live acceptance checks.
2. **HIGH (code/package robustness):** `web/index.php` has no fallback autoloader (unlike `apps/migration-module/ui/admin/api.php`), causing fatal startup in environments where `vendor/autoload.php` is absent.
   - File: `web/index.php`
   - Symptom: `Class "MigrationModule\Support\DbConfig" not found`

## Final checklist (requested format)
A. Live MySQL environment used
- Intended live target: `127.0.0.1:3306` from canonical generated config.
- Reachability: failed (`Connection refused`).

B. deployment:check result
- Structured JSON present, but fail.
- Exit code `2`.

C. installer flow result
- `check-connection`: fail (structured).
- `init-schema`: fail (500 PDO connect refusal).
- Runtime after reload: not reached; entrypoint fatal without vendor autoload.

D. create-job -> status smoke result
- `create-job`: fail (PDO connect refusal), no `job_id`.
- `status`: not executable without created `job_id`.

E. remaining blockers
- Reachable live MySQL instance not available in environment.
- `web/index.php` autoload robustness issue in vendor-less/offline package layout.

F. final verdict
- **FAIL** (cannot mark acceptance PASS).
- Build is **not yet accepted for first real offline portable MySQL-only deployment** based on this run.

## Post-remediation follow-up
- See `docs/remediation-note-2026-03-17.md` for implemented targeted fixes.
- Use `docs/live-mysql-acceptance-rerun-checklist.md` for the next reproducible rerun.
