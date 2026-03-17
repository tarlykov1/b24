# Installation Troubleshooting

## Common blockers
- `platform_schema_overlaps_bitrix_operational_schema`: set dedicated MySQL schema.
- `source_target_identical_detected`: verify endpoints/DSNs and diagnostic intent.
- `*_unsafe_path`: move install/log/temp dirs outside Bitrix core paths.

## Diagnostics
- Run `bin/migration-module install:report --install-config=<file>`.
- Run `bin/migration-module db:status --install-config=<file>`.

## MySQL connectivity checklist
- Ensure migration host can reach remote MySQL `host:port` over network and firewall rules.
- Verify PDO MySQL extension is installed (`pdo_mysql`).
- Verify DB user has install-time rights: `CREATE`, `ALTER`, `INDEX` on migration schema.
