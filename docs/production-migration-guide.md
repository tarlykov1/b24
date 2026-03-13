# Production migration guide

## Production readiness checklist
Перед запуском выполняется автоматический checklist:
- api_available
- permissions_ok
- config_valid
- dry_run_successful
- critical_conflicts_absent
- backup_available
- free_disk_space
- connection_stable

При провале любого пункта запуск блокируется.

## Cutover procedure
1. Проверка готовности (`main_transfer_done=true`, `critical_errors=0`).
2. Freeze mode (или fallback на continuous delta sync).
3. Final delta sync.
4. Final verification.
5. Двойное подтверждение оператора.
6. `migration_status = COMPLETED`.

## Rollback procedure
### SAFE
- останавливает процесс;
- не удаляет данные;
- пишет `rollback_report.json`.

### FULL
- пытается удалить созданные сущности;
- очищает mapping/checkpoints;
- пишет `rollback_report.json` с количеством удаленных/неудаленных сущностей.

## Delta sync usage
Поддерживается запуск через CLI:
```bash
bin/migration-module migration delta-sync <jobId>
```

## Troubleshooting
- Частые `429/500/network timeout`: снижайте профиль до `safe` и повторяйте запуск.
- Невозможен freeze: система автоматически включит `continuous delta sync`.
- Ошибка cutover confirmation: выполните rollback в `safe` режиме и повторите финальную сверку.
