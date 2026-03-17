# Live MySQL acceptance rerun checklist

## Preconditions
1. Reachable MySQL endpoint is available.
2. Canonical config artifact path is `config/generated-install-config.json`.
3. Effective resolution semantics remain: `override > DB_* env > generated canonical config`.

## Steps
1. **Installer-compatible canonical config generation**
   - `POST /api.php/install/generate-config` with target MySQL payload.
   - PASS: response `ok=true`, file `config/generated-install-config.json` exists and includes `mysql` block.
   - FAIL: no file, malformed JSON, or `ok=false`.

2. **Readiness parity check**
   - `php bin/migration-module deployment:check`
   - `POST /api.php/install/check-connection`
   - PASS: both report same effective endpoint and both pass (`ok=true`, exit code `0` for CLI).
   - FAIL: mismatch in endpoint/effective config, or failing checks.

3. **Schema bootstrap**
   - `POST /api.php/install/init-schema`
   - PASS: structured `ok=true` and no HTTP 500.
   - FAIL: PDO/fatal path or non-structured error.

4. **Lifecycle smoke (CLI)**
   - `php bin/migration-module create-job --config=migration.config.yml`
   - `php bin/migration-module status --config=migration.config.yml --job-id=<job_id>`
   - `php bin/migration-module report --config=migration.config.yml --job-id=<job_id>`
   - PASS: `create-job` returns `job_id`, commands return structured JSON without fatal traces.
   - FAIL: raw fatal/exit 255 or missing structured contract.

5. **Vendor-less/offline web entrypoint smoke**
   - Ensure `vendor/autoload.php` is absent in package layout.
   - `GET /` (served by `web/index.php`).
   - PASS: controlled installer/admin response page, no `Class not found` fatal.
   - FAIL: PHP fatal startup due autoload/class resolution.

## Acceptance verdict
- PASS only when all steps above pass on live reachable MySQL.
- FAIL if any step fails or if success-path cannot be executed.
