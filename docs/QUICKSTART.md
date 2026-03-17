# QUICKSTART (offline portable, MySQL-only)

1. Unpack archive into `/b24_migration`.
2. Open `http://<host>/web/`.
3. If `config/generated-install-config.json` is absent, installer opens automatically.
4. In installer:
   - check connection;
   - initialize schema;
   - generate config.
5. Open UI (`web/index.php`) and run health/readiness checks.
6. Create lifecycle job from CLI:
   - `php bin/migration-module create-job`
   - `php bin/migration-module status --job-id=<id>`

## Config source priority
Single canonical loader (`MigrationModule\Support\DbConfig::fromRuntimeSources`) uses:
1. explicit runtime override (installer/API/CLI provided mysql config);
2. environment (`DB_*`);
3. canonical file `config/generated-install-config.json`.

No SQLite fallback.
No internet required for runtime deployment.
