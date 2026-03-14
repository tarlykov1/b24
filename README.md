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
