# Bitrix24 Migration Toolkit (Executable Prototype)

Это **честный executable prototype**, а не production-ready инструмент.

## Что реально работает

- CLI команды: `validate`, `dry-run`, `plan`, `execute`, `pause`, `resume`, `verify`, `report`, `status`.
- Минимальный end-to-end pipeline: config -> job -> queue -> processing -> checkpoint/state -> diff/log/report.
- Режимы runtime: dry-run / execute / resume / verify-only (`verify`).
- SQLite storage (`.prototype/migration.sqlite`) со схемой прототипа.
- Stub adapters для `users`, `crm`, `tasks`, `files` с batch/chunk семантикой и симуляцией transient error/retry.
- Рераны и delta-контракт: skip для неизменённых, create для новых, update для изменённых.
- ID conflict policy (preserve source id + fallback).
- Базовая user policy (cutoff + стратегии).
- Простая operational admin demo (`apps/migration-module/ui/admin/index.php`) с реальными данными из SQLite.

## Быстрый запуск

```bash
php bin/migration-module validate
php bin/migration-module dry-run
php bin/migration-module execute
php bin/migration-module resume <config> <job_id>
php bin/migration-module verify
php bin/migration-module report
```

По умолчанию используется `migration.config.yml`.

## Команды CLI

```text
help
validate
plan
dry-run
execute
pause
resume
verify
report
status
```

## Что работает через stub/mock

- Источник и целевая система — `StubSourceAdapter` и `StubTargetAdapter`.
- Конфликты/ошибки/retry управляются предсказуемо (prototype-логика).

## Ограничения prototype

- Нет реального Bitrix REST/DB/filesystem adapter.
- Нет распределённого worker runtime.
- Нет production auth/security hardening.
- Нет полноценной UI локализации.

## Тесты

```bash
composer test
```

Проверяет: config loading, CLI/runtime smoke, dry-run/execute/resume, rerun/delta, id conflict, verify summary, user policy, schema/runtime consistency.

## Web UI

- [Migration Operations Console](docs/migration-operations-console.md)


## Production hardening additions
- Real Bitrix REST adapter is auto-enabled via `BITRIX_WEBHOOK_URL` + `BITRIX_WEBHOOK_TOKEN`.
- New command: `php bin/migration-module system:check`.
- Admin API now requires login + CSRF and exposes `/health` and `/ready`.
- Config split added under `config/migration.php`, `config/runtime.php`, `config/bitrix.php`.

## Snapshot Consistency, Delta Sync и Reconciliation Engine

Добавлен центральный consistency-layer, который отделяет baseline миграцию от последующей дельта-синхронизации и reconciliation-проходов.

### Новые runtime-моды
- `baseline` — перенос только сущностей до `source_cutoff_time`.
- `reconciliation` — дозакрытие зависимостей, orphan references, delayed links/files.
- `delta` — обработка изменений после snapshot cutoff.
- `verify` — многоуровневая проверка counts/mappings/relations/files.
- `repair` — controlled fix для обнаруженных integrity issues.

### Snapshot model
- `snapshot_id`, `snapshot_started_at`, `source_cutoff_time`, `snapshot_status`.
- Per-entity watermarks: `last_extracted_source_marker`, `last_reconciled_source_marker`, `last_verified_source_marker`, `last_target_sync_marker`.
- Поддержка timestamp/id/page/composite cursor marker.

### New commands/API surface (application handlers)
- `snapshot:create`, `snapshot:show`
- `baseline:plan`, `baseline:execute`
- `reconciliation:run`
- `delta:plan`, `delta:execute`
- `verify:relations`, `verify:files`
- `conflicts:list`, `conflicts:resolve`
- `watermarks:show`, `state:inspect`, `orphans:list`, `repair:relations`

### Conflict/policy guarantees
- Политики sync: `create_only`, `create_or_update`, `update_if_source_newer`, `update_if_target_untouched`, `conflict_on_both_changed`, `skip_if_target_exists`, `manual_review_required`.
- Нет silent overwrite ручных изменений в target (best-effort через target change markers + conflict detector).
- `created` не считается fully done до `linked/files/reconciled/verified`.

### Что теперь консистентнее
- Первый run стал snapshot-aware: baseline фиксируется по cutoff, а всё после cutoff уходит в delta.
- Rerun идемпотентен: повторный запуск использует map/policy/conflict-engine и не создаёт silent duplicates.
- Проверка “здоровья” больше не только по counts — учитывается relation/file integrity.

### Ограничения прототипа
- Manual target edit detection и delete semantics пока best-effort (без полного аудит-трейла source/target).
- File verification и reconcile логика реализованы как runtime слой прототипа, без реального object-storage backend.
