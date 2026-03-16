# Disaster Recovery

Commands:

- `php bin/migration-module system:repair --job-id=<job_id>`
- `php bin/migration-module system:recover --job-id=<job_id>`
- `php bin/migration-module uninstall --root=/opt/bitrix-migration`

`system:repair` rebuilds retry queue and reports mapping issues.

`system:recover` restores runtime state markers for resume flow.

`uninstall` removes runtime jobs/logs/queue and preserves backups/snapshots.
