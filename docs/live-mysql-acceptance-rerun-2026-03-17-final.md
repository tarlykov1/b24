# Final live MySQL acceptance rerun (acceptance-close attempt against canonical endpoint)

## 1) Summary
- Performed the requested final acceptance-close rerun on **2026-03-18** targeting canonical live MySQL endpoint `10.10.10.10:3306`.
- Reran installer (`generate-config`, `check-connection`, `init-schema`), runtime/admin readiness (`GET /api.php/ready`), and CLI flow (`deployment:check`, `create-job`, `status`, `report`).
- **Final result: NOT ACCEPTED** because live success-path could not be proven end-to-end: canonical endpoint resolution is correct everywhere, but real TCP/MySQL connectivity still fails with `Network is unreachable`.

## 2) What was verified
1. Installer config generation succeeds and persists canonical MySQL values (`10.10.10.10:3306`).
2. Installer connectivity and schema bootstrap checks resolve to the canonical endpoint and fail on real network reachability.
3. Runtime/admin readiness endpoint resolves to the canonical endpoint and fails on the same network reachability condition.
4. CLI commands resolve to the canonical endpoint and fail on the same real network reachability condition.
5. Direct TCP probe from this environment to `10.10.10.10:3306` fails with `Network is unreachable`.

## 3) Exact commands/endpoints executed

### A. Installer server
- `php -S 127.0.0.1:18081 -t web`

### B. Installer generate-config (canonical payload)
- Endpoint: `POST /api.php/install/generate-config`
- Command:
  - `curl -sS -D /tmp/gen_headers.txt -o /tmp/gen_body.json -X POST http://127.0.0.1:18081/api.php/install/generate-config -H 'Content-Type: application/json' --data '{"config":{"mysql":{"host":"10.10.10.10","port":3306,"name":"live_acceptance_db","user":"live_acceptance_user","password":"live_acceptance_pass"}}}'`

### C. Installer check-connection (canonical payload)
- Endpoint: `POST /api.php/install/check-connection`
- Command:
  - `curl -sS -D /tmp/check_headers.txt -o /tmp/check_body.json -X POST http://127.0.0.1:18081/api.php/install/check-connection -H 'Content-Type: application/json' --data '{"config":{"mysql":{"host":"10.10.10.10","port":3306,"name":"live_acceptance_db","user":"live_acceptance_user","password":"live_acceptance_pass"}}}'`

### D. Installer init-schema (canonical payload)
- Endpoint: `POST /api.php/install/init-schema`
- Command:
  - `curl -sS -D /tmp/init_headers.txt -o /tmp/init_body.json -X POST http://127.0.0.1:18081/api.php/install/init-schema -H 'Content-Type: application/json' --data '{"config":{"mysql":{"host":"10.10.10.10","port":3306,"name":"live_acceptance_db","user":"live_acceptance_user","password":"live_acceptance_pass"}}}'`

### E. Runtime/admin readiness
- Endpoint: `GET /api.php/ready`
- Command:
  - `curl -sS -D /tmp/ready_headers.txt -o /tmp/ready_body.json http://127.0.0.1:18081/api.php/ready`

### F. CLI flow
- `php bin/migration-module deployment:check`
- `php bin/migration-module create-job --config=migration.config.yml`
- `php bin/migration-module status --config=migration.config.yml --job-id=live-rerun-20260318-closeout`
- `php bin/migration-module report --config=migration.config.yml --job-id=live-rerun-20260318-closeout`

### G. External TCP reachability probe
- `timeout 5 bash -c 'echo > /dev/tcp/10.10.10.10/3306'`

## 4) Effective DB endpoint resolution evidence

### Installer evidence
- `generate-config` response shows canonical values in generated redacted payload:
  - `host=10.10.10.10`
  - `port=3306`
  - `name=live_acceptance_db`
  - `user=live_acceptance_user`
- `check-connection` failure payload explicitly references:
  - `host=10.10.10.10`
  - `port=3306`
  - `message=Network is unreachable`
- `init-schema` fails with MySQL connect error:
  - `SQLSTATE[HY000] [2002] Network is unreachable`

### Runtime/admin evidence
- `GET /api.php/ready` returns HTTP `503` and error payload references:
  - `host=10.10.10.10`
  - `port=3306`
  - `message=Network is unreachable`

### CLI evidence
- `deployment:check` output references:
  - `host=10.10.10.10`
  - `port=3306`
  - `message=Network is unreachable`
- `create-job` output references:
  - `error_code=mysql_connection_or_permission_failed`
  - `host=10.10.10.10`
  - `port=3306`
  - `message=SQLSTATE[HY000] [2002] Network is unreachable`
- `status` and `report` outputs show the same canonical endpoint and connectivity failure class.

## 5) Observed results
- `generate-config`: HTTP `200`, `ok=true` (canonical config generated).
- `check-connection`: HTTP `200`, `ok=false`, `system_check_failed`, network failure to `10.10.10.10:3306`.
- `init-schema`: HTTP `200`, `ok=false`, `installer_mysql_bootstrap_failed`, SQLSTATE network unreachable.
- `GET /api.php/ready`: HTTP `503`, `ok=false`, network failure to `10.10.10.10:3306`.
- `deployment:check`: exit `2`, `ok=false`, network failure to `10.10.10.10:3306`.
- `create-job`: exit `2`, `ok=false`, MySQL connectivity failure to `10.10.10.10:3306`.
- `status`: exit `2`, `ok=false`, same MySQL connectivity failure.
- `report`: exit `2`, `ok=false`, same MySQL connectivity failure.
- Direct TCP probe: failed with `Network is unreachable`.

## 6) Proof evaluation vs acceptance rule
Required proofs not achieved:
- ❌ Successful MySQL connection established.
- ❌ Schema initialization succeeds.
- ❌ `deployment:check` succeeds against live MySQL.
- ❌ `create-job` succeeds.
- ❌ `status` succeeds for a real created job.
- ❌ `report` succeeds for a real created job.

Proofs achieved:
- ✅ Effective DB resolution is canonical `10.10.10.10:3306` across installer, runtime/admin, and CLI.

## 7) Final verdict
- **NOT ACCEPTED**.

## 8) Single remaining blocker
- **Only remaining blocker:** external network connectivity from this execution environment to canonical live MySQL endpoint `10.10.10.10:3306` is still unavailable (`Network is unreachable`), preventing live success-path proof end-to-end.
