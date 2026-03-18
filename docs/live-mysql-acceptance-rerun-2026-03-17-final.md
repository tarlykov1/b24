# Final live MySQL acceptance rerun (focused success-path proof, canonical endpoint)

## 1) Summary
- Performed a focused acceptance rerun on **2026-03-18** only for live MySQL success-path proof, using canonical endpoint `10.10.10.10:3306`.
- Executed installer endpoints (`generate-config`, `check-connection`, `init-schema`), CLI flow (`deployment:check`, `create-job`, `status`, `report`), and runtime/admin readiness (`GET /api.php/ready`).
- **Final result: NOT ACCEPTED**. All relevant paths resolve to canonical endpoint and fail consistently with `Network is unreachable`; no evidence of placeholder masking or precedence regression.

## 2) What was verified
1. Canonical installer artifact can be generated with live endpoint values.
2. Installer path effective resolution uses canonical endpoint even when placeholder payload is provided.
3. CLI path effective resolution uses canonical endpoint even when `migration.config.yml` contains placeholders.
4. Runtime/admin API readiness path resolves to the same canonical endpoint.
5. Remaining failure is connectivity to `10.10.10.10:3306`, not config precedence/masking.

## 3) Exact commands/endpoints executed

### A. Installer server
- `php -S 127.0.0.1:18081 -t web`

### B. Installer generate-config
- `POST /api.php/install/generate-config`
- Command:
  - `curl -sS -D /tmp/gen_headers.txt -o /tmp/gen_body.json -X POST http://127.0.0.1:18081/api.php/install/generate-config -H 'Content-Type: application/json' --data '{"config":{"mysql":{"host":"10.10.10.10","port":3306,"name":"live_acceptance_db","user":"live_acceptance_user","password":"live_acceptance_pass"}}}'`

### C. Installer check-connection (placeholder probe)
- `POST /api.php/install/check-connection`
- Command:
  - `curl -sS -D /tmp/check_headers.txt -o /tmp/check_body.json -X POST http://127.0.0.1:18081/api.php/install/check-connection -H 'Content-Type: application/json' --data '{"config":{"mysql":{"host":"127.0.0.1","port":3306,"name":"bitrix_migration","user":"migration_user","password":"change_me"}}}'`

### D. Installer init-schema (placeholder probe)
- `POST /api.php/install/init-schema`
- Command:
  - `curl -sS -D /tmp/init_headers.txt -o /tmp/init_body.json -X POST http://127.0.0.1:18081/api.php/install/init-schema -H 'Content-Type: application/json' --data '{"config":{"mysql":{"host":"127.0.0.1","port":3306,"name":"bitrix_migration","user":"migration_user","password":"change_me"}}}'`

### E. Runtime/admin API path
- `GET /api.php/ready`
- Command:
  - `curl -sS -D /tmp/ready_headers.txt -o /tmp/ready_body.json http://127.0.0.1:18081/api.php/ready`

### F. CLI path
- `php bin/migration-module deployment:check`
- `php bin/migration-module create-job --config=migration.config.yml`
- `php bin/migration-module status --config=migration.config.yml --job-id=live-rerun-20260318`
- `php bin/migration-module report --config=migration.config.yml --job-id=live-rerun-20260318`

### G. External TCP reachability probe
- `timeout 5 bash -c 'echo > /dev/tcp/10.10.10.10/3306'`

## 4) Effective DB endpoint resolution evidence

### Installer path evidence
- `generate-config` returned `ok=true` and wrote canonical artifact with:
  - `host=10.10.10.10`
  - `port=3306`
  - `name=live_acceptance_db`
  - `user=live_acceptance_user`
- `check-connection` was intentionally called with placeholders (`127.0.0.1`, `bitrix_migration`, `migration_user`, `change_me`) but response errors still show:
  - `host=10.10.10.10`
  - `port=3306`
  - `message=Network is unreachable`

### CLI path evidence
- `migration.config.yml` contains placeholder storage values (`127.0.0.1`, `bitrix_migration`, `migration_user`, `change_me`), but CLI responses for `deployment:check`, `create-job`, `status`, `report` all report:
  - `host=10.10.10.10`
  - `port=3306`
  - SQLSTATE/network-unreachable failure

### Runtime/admin API evidence
- `GET /api.php/ready` returns `503` with same error payload:
  - `host=10.10.10.10`
  - `port=3306`
  - `message=Network is unreachable`

## 5) Observed results/errors
- `generate-config`: HTTP `200`, `ok=true`, canonical config generated.
- `check-connection`: HTTP `200`, `ok=false`, `system_check_failed`, network failure to `10.10.10.10:3306`.
- `init-schema`: HTTP `200`, `ok=false`, `installer_mysql_bootstrap_failed`, SQLSTATE network unreachable.
- `deployment:check`: exit `2`, `ok=false`, network failure to `10.10.10.10:3306`.
- `create-job`: exit `2`, `ok=false`, `mysql_connection_or_permission_failed`, host/port `10.10.10.10:3306`.
- `status`: exit `2`, same failure class and endpoint.
- `report`: exit `2`, same failure class and endpoint.
- TCP probe to `10.10.10.10:3306`: `Network is unreachable`.

## 6) Acceptance verdict
- **NOT ACCEPTED**.

## 7) Single remaining blocker
- **External connectivity to canonical live MySQL endpoint is unavailable**: `10.10.10.10:3306` returns `Network is unreachable` from installer path, CLI path, runtime/admin path, and direct TCP probe.
- No remaining evidence of internal blocker in precedence/placeholder resolution.
