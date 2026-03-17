# Remediation note (post live MySQL acceptance, 2026-03-17)

## What was remediated
- Fixed `web/index.php` bootstrap to support vendor-less/offline package layout using the same fallback autoload pattern as admin API/CLI (`apps/migration-module/bootstrap.php`).
- Added controlled HTML fallback (HTTP 503) in `web/index.php` for bootstrap/runtime startup failures instead of raw PHP fatal startup.
- Normalized CLI early MySQL bootstrap failures (`create-job`, `status`, `report`, `validate`, `dry-run`, `execute`, `resume`, `pause`, `verify`) to structured JSON with deterministic exit code `2` and classified `error.error_code`.

## Out of scope
- Live MySQL endpoint availability itself (environment blocker) remains external to code changes.
- End-to-end success-path proof still requires rerun against reachable MySQL.
