# Upgrade Manager

Upgrade packages are stored in `upgrades/` and must include metadata:

- `version`
- `checksum` (sha256 of package)
- `migrations` (array of SQL files from `upgrades/migrations/`)

CLI:

- `php bin/migration-module upgrade:check --root=/opt/bitrix-migration`
- `php bin/migration-module upgrade:install --root=/opt/bitrix-migration --package=<file.json>`
- `php bin/migration-module upgrade:rollback --root=/opt/bitrix-migration`

Install flow: pre-upgrade backup, checksum validation, SQL migrations, upgrade state persistence.
