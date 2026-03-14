# Production Pilot Hardening Guide

## Deployment
- Set `BITRIX_WEBHOOK_URL` and `BITRIX_WEBHOOK_TOKEN` to enable real REST adapters.
- Set `MIGRATION_ADMIN_PASSWORD_HASH` (bcrypt/argon2 hash via `password_hash`).
- Start admin UI and API under HTTPS.

## Configuration
- `config/migration.php`: batch size, retry policy, file checksums.
- `config/runtime.php`: max RPS, workers, timeout and adaptive slowdown.
- `config/bitrix.php`: Bitrix REST and optional read-only DB params.

## Migration Workflow
1. `php bin/migration-module validate`
2. `php bin/migration-module system:check`
3. `php bin/migration-module dry-run`
4. `php bin/migration-module execute`
5. `php bin/migration-module resume` (if paused)
6. `php bin/migration-module verify`

## Recovery Procedures
- Runtime stores queue/checkpoint/entity_map in SQLite.
- Resume reads queue with `pending/retry` only and skips mapped IDs.
- Dangerous actions in API require typed confirmation.

## Troubleshooting
- Check JSON structured logs in `logs.message` payload.
- Use `/health` and `/ready` endpoints.
- If Bitrix returns 429, retry/backoff is automatic.
- For file migration errors, inspect checksum mismatch and retry records.
