# Final live MySQL acceptance rerun (post-remediation, canonical precedence verification)

## 1) Summary
- Re-ran canonical installer config flow, installer/CLI endpoint parity checks, `deployment:check`, installer `check-connection` + `init-schema`, CLI lifecycle smoke (`create-job`, `status`, `report`), and vendor-less web entrypoint smoke.
- Installer bootstrap/path regressions were addressed before rerun (`web/api.php` bridge added, admin API bootstrap path corrected, `init-schema` now structured on bootstrap failure).
- **Rerun result: FAIL / NOT ACCEPTED** because live MySQL success-path is still not reachable in this environment (effective endpoint `10.10.10.10:3306` unreachable).

## 2) Evidence

### A. Canonical config / installer parity
Executed:
- `POST /api.php/install/generate-config` (via `php -S 127.0.0.1:18081 -t web`)

Observed:
- HTTP `200` with structured JSON `ok=true`.
- `config/generated-install-config.json` created and contains canonical live MySQL target (`10.10.10.10:3306`, `live_acceptance_db`, `live_acceptance_user`).
- Placeholder CLI override values (`127.0.0.1`, `bitrix_migration`, `migration_user`, `change_me`) were intentionally supplied and **did not** override canonical installer-generated values.

### B. deployment:check success-path
Executed:
- `php bin/migration-module deployment:check`

Observed:
- Structured JSON returned.
- `ok=false`, `status=fail`, `code=system_check_failed`.
- Exit code `2`.
- Errors show network failure to effective endpoint `10.10.10.10:3306`.

### C. Installer MySQL success-path
Executed:
- `POST /api.php/install/check-connection`
- `POST /api.php/install/init-schema`

Observed:
- `check-connection`: HTTP `200`, structured fail (`ok=false`) with same effective endpoint (`10.10.10.10:3306`).
- `init-schema`: HTTP `200`, structured fail (`ok=false`, `code=installer_mysql_bootstrap_failed`), no raw fatal and no HTTP 500.
- Schema init success not achieved due unreachable MySQL.

### D. Lifecycle smoke on live MySQL
Executed:
- `php bin/migration-module create-job --config=migration.config.yml`
- `php bin/migration-module status --config=migration.config.yml --job-id=rerun-smoke`
- `php bin/migration-module report --config=migration.config.yml --job-id=rerun-smoke`

Observed:
- All commands return structured JSON fail-safe (`error_code=mysql_connection_refused`).
- Exit code for each command: `2`.
- `create-job` did not produce a real `job_id` because MySQL is unreachable.

### E. Vendor-less/offline web entrypoint smoke
Executed:
- Verified `vendor/autoload.php` absent.
- `GET /` via `web/index.php`.

Observed:
- HTTP `200` controlled installer HTML.
- No `Class not found` / raw fatal at entrypoint startup.

### F. Consistency and precedence check
- Installer and CLI both resolve and report the same effective MySQL endpoint (`10.10.10.10:3306`).
- Placeholder CLI/template override values no longer mask canonical installer-generated configuration.
- Result is internally consistent but remains fail-path only; success-path is not proven on live MySQL.

## 3) Acceptance criteria matrix
- canonical config generated — **PASS**
  `/install/generate-config` returned `ok=true`; canonical file exists at `config/generated-install-config.json`.

- installer/CLI endpoint parity — **PASS (fail-path parity)**
  Installer `check-connection` and CLI `deployment:check` both resolve canonical `10.10.10.10:3306`.

- placeholder-masking regression check — **PASS**
  Placeholder CLI override values do not mask installer-generated canonical config.

- deployment:check success-path — **FAIL**
  Structured output returned, but `ok=false`; exit `2`.

- check-connection success — **FAIL**
  Structured response exists, but MySQL connect check is not successful.

- init-schema success — **FAIL**
  Structured response exists, but schema init cannot proceed without reachable MySQL.

- create-job returns job_id — **FAIL**
  Structured connection-refused response; no real `job_id`.

- status/report smoke success — **FAIL**
  Both commands return structured fail-safe due MySQL unreachability.

- vendor-less web entrypoint success — **PASS**
  Controlled installer page response from `GET /` without raw fatal.

## 4) Blockers
1. **BLOCKER / HIGH** — live MySQL endpoint unreachable at effective runtime target `10.10.10.10:3306`.
   Why it blocks acceptance: success-path cannot be proven for `deployment:check`, installer init-schema success, or lifecycle job operations.

## 5) Final verdict
- **not accepted** — **FAIL** because live MySQL success-path is still not demonstrated in this rerun.

## 6) Testing
Full executed commands / calls:
- `php -S 127.0.0.1:18081 -t web`
- `curl -X POST http://127.0.0.1:18081/api.php/install/generate-config ...`
- `curl -X POST http://127.0.0.1:18081/api.php/install/check-connection ...`
- `curl -X POST http://127.0.0.1:18081/api.php/install/init-schema ...`
- `curl http://127.0.0.1:18081/`
- `php bin/migration-module deployment:check`
- `php bin/migration-module create-job --config=migration.config.yml`
- `php bin/migration-module status --config=migration.config.yml --job-id=rerun-smoke`
- `php bin/migration-module report --config=migration.config.yml --job-id=rerun-smoke`
