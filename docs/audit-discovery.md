# Audit / Discovery module (read-only)

Модуль discovery запускается перед baseline/delta фазами и собирает профиль портала без изменения данных.

## Гарантии безопасности

- Только read-only доступ к БД (только `SELECT`, плюс read-only session для MySQL).
- Файловая система только на чтение (сканирование `BITRIX_UPLOAD_PATH`, по умолчанию `/upload`).
- REST только retrieval-методы (`user.get`, `crm.*.list`, `tasks.task.list`, `disk.storage.getlist`).
- Не запускает migration runtime и не создает сущности.

## CLI команды

```bash
php bin/migration-module audit:portal
php bin/migration-module audit:users
php bin/migration-module audit:tasks
php bin/migration-module audit:files
php bin/migration-module audit:crm
php bin/migration-module audit:permissions
php bin/migration-module audit:linkage
php bin/migration-module audit:summary
php bin/migration-module audit:report
php bin/migration-module audit:run
```

`audit:run` выполняет полный аудит и формирует артефакты:

- `.audit/migration_profile.json`
- `.audit/report.html`

## Какие данные собираются

- Portal profile: версия, модули, объем БД/файлов, users/groups, smart processes.
- Users/tasks/files/crm/permissions с объемами и распределениями.
- Риски миграции (`LOW|MEDIUM|HIGH|CRITICAL`) и причины.
- Strategy hints для policy/planning/tuning.

## Как читать отчёт

- `summary.risk_level` — итоговый риск.
- `readiness_score` — готовность к миграции (0..100).
- `strategy_hints` — рекомендации по users/tasks/files pipeline.
- `sources` — какие источники реально использованы (`db/fs/rest`).

## Использование strategy hints

`migration_profile.json` предназначен как input для policy engine:

- users policy (`active + owners_only` / `all_users`)
- tasks strategy (`migrate_metadata_first` / `single_pipeline`)
- files strategy (`separate_bulk_transfer` / `inline_transfer`)
- cutoff hints (weekend/night)

## Пример

```json
{
  "users": {"total": 7000, "active": 1300},
  "tasks": {"total": 120000, "with_files": 32000},
  "files": {"total_size_gb": 180},
  "migration_strategy": {"files_separate_pipeline": true}
}
```

## Task / File Linkage and Attachment Semantics

Добавлен аудит семантики привязок:

```bash
php bin/migration-module audit:linkage
php bin/migration-module audit:linkage --deep
```

`audit:linkage` анализирует не только объёмы, но и граф связей между `tasks`, `task_comments`, `b_file`, `b_disk_object`, `b_disk_attached_object`.

Классифицируются типы привязок и источники:
- task direct attachment
- task comment attachment
- disk attached object
- legacy / orphan references

Проверяются риски:
- orphan attachment references
- disk object без attached context
- multi-linked files (один файл в нескольких контекстах)
- потеря comment attachments при миграции

Почему одних counts недостаточно:
- одинаковое число файлов может скрывать разную топологию привязок;
- файл может быть связан с task, comment и disk context одновременно;
- перенос бинарников без последующей привязки ломает логику задач/комментариев.

Strategy hints интерпретация:
- сначала перенос metadata, затем binary transfer;
- отдельный pass для comment attachments;
- reconciliation после миграции задач;
- для multi-link: copy binary once, затем rebind many.
