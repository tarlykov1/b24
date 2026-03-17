# Troubleshooting installer/runtime (offline MySQL-only)

## deployment:check returns fail
Run:
```bash
php bin/migration-module deployment:check
```
Output is always machine-readable JSON with `status`, `checks`, `errors`, `code`.
No fatal stack-trace path is expected for DB connection errors.

## Installer completed but runtime still asks installer
Verify canonical config exists:
- `config/generated-install-config.json`
- contains top-level `mysql`.

Effective config source priority:
1. explicit override;
2. `DB_*` env;
3. generated config artifact.

## MySQL auth/permission errors
Use installer check-connection first, then validate user has create/alter/index/drop rights for schema bootstrap.

## SQLite references
Runtime is MySQL-only; SQLite artifacts in tests/legacy paths are not deployment backends.
