# Co-located Deployment on Target Bitrix Server

## Recommended layout
- install dir: `/opt/bitrix-migration`
- config dir: `/etc/bitrix-migration`
- logs dir: `/var/log/bitrix-migration`
- work/tmp: `/var/lib/bitrix-migration/tmp`

## Isolation rules
- Never reuse Bitrix application schema for migration runtime state.
- Bind migration API/UI to a dedicated route/port and proxy via Nginx.
- Use dedicated service user and systemd units.
- Keep conservative worker and batch defaults to protect target DB.
