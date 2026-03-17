# MySQL-first Safe Installation Wizard

The installer is available at `apps/migration-module/ui/admin/install.php` and maps to CLI/API validation primitives.

## Safety defaults
- Target write mode is disabled by default.
- Platform state must use a dedicated MySQL schema (`bitrix_migration` recommended).
- Source/target DB identity overlap is blocked.
- Risky filesystem paths (`/`, `/etc`, Bitrix core directories) are blocked.

## Step model
1. welcome/safety notice
2. environment checks
3. platform DB validation
4. source connection
5. target connection + write-acknowledgement
6. filesystem mapping
7. policy bootstrap
8. resource throttling profile
9. preflight summary
10. apply
11. post-install checks

## CLI parity
- `install:check`
- `install:validate`
- `install:init-db`
- `install:generate-config`
- `install:apply`
- `install:report`
- `config:lint`
- `db:migrate`
- `db:status`

All return JSON and non-zero code on blockers.

## MySQL-only requirements
- Installer accepts only MySQL settings (`host`, `port`, `db`, `user`, `password`, `charset`, `collation`).
- Installer verifies: TCP reachability, `SELECT 1`, and install-time DDL rights (`CREATE TABLE`, `ALTER TABLE`, `CREATE INDEX`).
- Runtime/state storage uses MySQL only; SQLite backend is removed from installer flow.
