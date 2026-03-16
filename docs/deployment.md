# Production Deployment

Production path: `/opt/bitrix-migration`.

Required layout:

- `bin/migration-cli`
- `config/migration.yaml`, `config/workers.yaml`
- `runtime/jobs.db`, `runtime/logs/`, `runtime/queue/`
- `storage/backups/`, `storage/snapshots/`
- `web/index.php`, `web/installer.php`
- `src/`
- `upgrades/`

Use `php bin/migration-module system:check --root=/opt/bitrix-migration` before launch.

Web installer is exposed as `/migration/install` (nginx route to admin install page).
