# Backup & Restore

CLI:

- `php bin/migration-module backup:create --root=/opt/bitrix-migration --type=runtime`
- `php bin/migration-module backup:list --root=/opt/bitrix-migration`
- `php bin/migration-module backup:restore --root=/opt/bitrix-migration --backup-id=<id>`
- `php bin/migration-module restore --root=/opt/bitrix-migration --backup-id=<id>`

Backups include runtime DB, queue, logs, snapshots, and config (depending on backup type).

Scheduler policy example in `config/migration.yaml`:

```yaml
backup:
  schedule: daily
  retention_days: 30
```
