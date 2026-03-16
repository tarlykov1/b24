# Operator Safety Guide

- Start with `install:check` and `install:validate`.
- Keep `target.write_enabled=false` until dry-run + verify are green.
- Explicitly confirm any elevated DB permissions.
- Do not install inside Bitrix core directories.
- Treat warnings as required review before cutover prep.
