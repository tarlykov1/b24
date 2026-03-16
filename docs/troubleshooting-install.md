# Installation Troubleshooting

## Common blockers
- `platform_schema_overlaps_bitrix_operational_schema`: set dedicated MySQL schema.
- `source_target_identical_detected`: verify endpoints/DSNs and diagnostic intent.
- `*_unsafe_path`: move install/log/temp dirs outside Bitrix core paths.

## Diagnostics
- Run `bin/migration-module install:report --install-config=<file>`.
- Run `bin/migration-module db:status --install-config=<file>`.
