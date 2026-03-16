# Инструментарий миграции Bitrix24

Исполняемый прототип для сценариев миграции Bitrix24 (CLI + состояние выполнения в SQLite + admin API/UI).

## Статус проекта / зрелость

**Текущий уровень зрелости:** **stabilized prototype baseline (truth-hardened), не production-ready**.

- **Реализовано:** прототип рантайма (`validate` → `dry-run` → `execute`/`resume` → `verify`/`report`/`status`) с персистентностью в SQLite и stub-адаптерами.
- **Реализовано (укрепление пилота):** `system:check`, вход в админку + CSRF, эндпоинты `/health` и `/ready`, раздельные конфигурационные файлы.
- **Частично реализовано:** реальный Bitrix REST-адаптер (автовключение через env), движки consistency/delta/reconciliation как сервисы приложения.
- **Прототип / на заглушках:** большинство «продвинутых» функций consistency/snapshot/reconciliation существуют как сервисные блоки и документация, но **не доступны как top-level CLI-команды** в `bin/migration-module`.
- **Пока не production-ready:** нет распределённых воркеров, ограниченное покрытие source/target, best-effort-семантика конфликтов/ручных правок.

## Обзор архитектуры

```text
Источник (StubSourceAdapter | BitrixRestAdapter)
        |
        v
Извлечение батчей -> plan/diff -> enqueue (очередь SQLite)
        |
        v
PrototypeRuntime execute/resume
  - политика ID
  - политика пользователей
  - retry / checkpoint / logs
        |
        v
Цель (StubTargetAdapter | BitrixRestAdapter)
        |
        v
Verify/report/status

Состояние рантайма в SQLite:
  jobs, queue, entity_map, user_map, checkpoint, diff, logs, integrity_issues, state
```

Дополнительные компоненты consistency существуют как PHP-сервисы (`SnapshotConsistencyService`, `DeltaSyncEngine`, `ConflictDetectionEngine` и др.), но сейчас они **не подключены к основному CLI entrypoint как отдельные команды**.

## Deterministic execution engine (новый слой)

В прототип добавлен детерминированный execution engine с:

- стабильным `plan_id`/`plan_hash`;
- dependency-aware графом и стабильным батчингом;
- строгим state store (`migration_plans`, `execution_steps`, `id_reservations`, `replay_guard`, `checkpoint_state` и др.);
- checkpoint/resume/retry/reconcile семантикой;
- replay-protection и идемпотентностью write-операций.

См. `docs/deterministic-execution-engine.md`.

## Что реально работает

### Реализовано

- CLI entrypoint: `bin/migration-module`.
- Команды, доступные в `help`:
  `validate`, `create-job`, `plan`, `dry-run`, `execute`, `pause`, `resume`, `verify`, `verify-only`, `report`, `status`, `system:check` (для mutating/read команд обязателен существующий `job_id`), а также хелперы `migration <subcommand>` (`pause`, `resume`, `retry`, `repair`, `diff`).
- Конвейер прототип-рантайма:
  - загрузка конфигурации из `migration.config.yml`
  - инициализация схемы + создание job
  - расчёт plan/diff
  - обработка очереди с retry и постоянными ошибками
  - checkpoint + mapping сущностей + структурированные логи
  - summary верификации и снимки status/report
- Хранилище SQLite с реальной схемой в `db/prototype_schema.sql`.
- Базовое укрепление Admin API:
  - вход по сессии
  - CSRF-проверка для POST
  - `/health` и `/ready`
  - security-заголовки
- Команда `system:check` и API-эндпоинт.

### Частично реализовано

- **Автовключение реального Bitrix REST-адаптера:** включается только если заданы и `BITRIX_WEBHOOK_URL`, и `BITRIX_WEBHOOK_TOKEN`; иначе используются заглушки.
- **Покрытие сущностей Bitrix:** адаптер включает конкретные методы/маппинги и upsert-логику, ориентированную на обновления; это не полное production-покрытие миграции.
- **Компоненты слоя consistency:** классы snapshot/watermark/conflict/reconciliation существуют и тестируемы как кодовые единицы, но пока не доступны в `bin/migration-module` как заявленные `snapshot:*`, `baseline:*`, `delta:*`, `conflicts:*` и т.д.

### Прототип / на заглушках

- Источник и цель по умолчанию — stub-адаптеры с детерминированными fixture-подобными данными.
- В real mode (`demo_mode=false`) synthetic telemetry отключена: API возвращает `not_available`/`demo_only`, если нет runtime truth.
- Synthetic панели доступны только в явном demo mode (`demo_mode=true`).
- Продвинутая reconciliation и policy-оркестрация на этом этапе — в основном сервисная логика и документационно-ориентированный слой.

## Быстрый старт

```bash
php bin/migration-module validate
php bin/migration-module create-job execute
# команда возвращает job_id, далее используйте его явно
php bin/migration-module plan migration.config.yml <job_id>
php bin/migration-module dry-run migration.config.yml <job_id>
php bin/migration-module execute migration.config.yml <job_id>
php bin/migration-module resume migration.config.yml <job_id>
php bin/migration-module verify migration.config.yml <job_id>
php bin/migration-module report migration.config.yml <job_id>
php bin/migration-module status migration.config.yml <job_id>
php bin/migration-module system:check
```

Файл конфигурации по умолчанию: `migration.config.yml`.

## CLI-команды

### Top-level команды (фактически доступны)

```text
help
validate
plan
dry-run
execute
pause
resume
verify
verify-only
report
status
system:check
migration pause
migration resume
migration retry <entity_type>:<source_id>
migration repair
migration diff <entity_type>:<source_id>
```

### Команды, часто упоминаемые в документации, но **недоступные** в `bin/migration-module`

Сейчас на уровне CLI они возвращают `Unknown command`:

- `snapshot:create`, `snapshot:show`
- `baseline:plan`, `baseline:execute`
- `reconciliation:run`
- `delta:plan`, `delta:execute`
- `verify:relations`, `verify:files`
- `conflicts:list`, `conflicts:resolve`
- `watermarks:show`, `state:inspect`, `orphans:list`, `repair:relations`

Связанная логика есть в `MigrationModule\Cli\MigrationCommands` и сервисах consistency, но пока не подключена к исполняемому CLI по умолчанию.

## Режимы / фазы рантайма

- `validate`: проверки схемы/bootstrap.
- `plan`: вычисляет summary по create/update/skip/conflict + строки diff.
- `dry-run`: план + сводка рисков, без записи в target.
- `execute`: постановка сущностей в очередь и их обработка.
- `resume`: повторный запуск executor с флагом resume.
- `verify` / `verify-only`: summary-проверки (missing/changed + базовые заглушки relation/file).
- `report` / `status`: агрегированные счётчики из SQLite.

## Хранилище прототипа

Путь к SQLite по умолчанию: `.prototype/migration.sqlite`.

Таблицы схемы:

- `jobs`
- `queue`
- `entity_map`
- `user_map`
- `logs`
- `checkpoint`
- `diff`
- `integrity_issues`
- `state`

> Примечание: таблицы `snapshots`, `snapshot_watermarks`, `conflicts`, `reconciliation_queue` и расширенные модели верификации **отсутствуют в SQLite-схеме прототипа**. Эти концепции сейчас реализованы в in-memory repository/services и документации.

## Адаптеры

### Stub-адаптеры (по умолчанию)

- `StubSourceAdapter`: сущности `users`, `crm`, `tasks`, `files`.
- `StubTargetAdapter`: in-memory upsert + проверки существования.

### Реальный Bitrix REST-адаптер (автовключение)

Активируется при наличии обеих env-переменных:

- `BITRIX_WEBHOOK_URL`
- `BITRIX_WEBHOOK_TOKEN`

Текущие характеристики адаптера:

- Использует REST-клиент с retry/backoff для повторяемых API-ошибок.
- Поддерживает mapped list/update-методы для выбранных типов сущностей.
- Лучше рассматривать как пилотный интеграционный слой, а не полный production-адаптер миграции.

## Web UI (Admin Console)

Путь: `apps/migration-module/ui/admin/index.php`.

Что реально:

- форма входа (хэш пароля из env)
- аутентификация на базе сессии
- выдача и валидация CSRF-токена для POST в API
- простые runtime-счётчики из SQLite (`jobs`, `queue`, `entity_map`, `diff`, `integrity_issues`)
- ссылки на `system:check`, `/health`, `/ready`

Что является прототипом/демо:

- API-поверхность включает несколько endpoint'ов мониторинга/управления, но многие ответы синтетические/mock-стиля.
- Это операционная прототип-консоль, а не полноценный production UI-продукт.

## Добавления для production hardening

Реализованные дополнения в текущем репозитории:

- `system:check` (CLI и admin API).
- Admin auth и CSRF-защита.
- эндпоинты `/health` и `/ready`.
- Разделённые конфигурационные файлы:
  - `config/migration.php`
  - `config/runtime.php`
  - `config/bitrix.php`
- Security-заголовки в bootstrap admin API.

## Snapshot Consistency / Delta Sync / Reconciliation

### Что существует в коде

- `SnapshotConsistencyService`
- `DeltaSyncEngine`
- `SyncPolicyEngine`
- `ConflictDetectionEngine`
- `ReconciliationQueueService`
- `RelationIntegrityEngine`
- `FileReconciliationService`
- `EntityStateMachine`

### Текущий уровень интеграции

- **Частично реализовано:** эти движки/сервисы присутствуют и описаны.
- **Не полностью подключено к исполняемому CLI-рантайму:** workflow команд baseline/delta/reconciliation недоступен через `bin/migration-module`.
- **Несоответствие хранилища:** более богатые snapshot/conflict/reconciliation-модели в основном находятся в in-memory repository-абстракциях, а не в SQLite-схеме прототипа.

## Гарантии Conflict & Sync Policy

- Движки конфликтов и политик предоставляют явные решения/типы на уровне кода.
- Гарантии по ручным правкам target и конфликтам в прототипной семантике — **best-effort**.
- Любые заявления о строгой гарантии no-overwrite не должны считаться production-уровнем без дополнительного audit trail/транзакционных механизмов.

## Тесты

Запуск:

```bash
composer test
```

Текущее автоматизированное покрытие (один прототипный скрипт) проверяет:

- загрузку конфигурации + `validate`
- plan/dry-run/execute/resume
- skip-поведение при повторном запуске
- конфликт id и поведение user policy
- форму verify summary
- базовую согласованность схемы/рантайма

Оно **не** даёт исчерпывающего production-уровня покрытия продвинутых consistency/reconciliation CLI-флоу.

## Что изменилось недавно

Подтверждённые недавние добавления, отражённые в кодовой базе:

- примитивы production hardening (`system:check`, admin auth/CSRF, `/health`, `/ready`)
- разделённые runtime/migration/bitrix-конфиги
- автовключение реального Bitrix REST-адаптера через env
- consistency-ориентированный сервисный слой для snapshot/watermark/delta/reconciliation/conflict/policy

Но также важно:

- продвинутая поверхность команд, описанная в документации, всё ещё в основном сервисного уровня / не подключена в CLI entrypoint по умолчанию.

## Известные ограничения прототипа

- Не production-ready end-to-end.
- Нет распределённого worker-рантайма.
- Ограниченное покрытие реального адаптера и семантики миграции.
- Продвинутый consistency lifecycle интегрирован частично.
- Часть документации описывает целевую архитектуру шире текущего исполняемого поведения CLI.

## Документация

- `STATUS.md`
- `docs/prototype-runtime.md`
- `docs/production.md`
- `docs/snapshot-delta-reconciliation.md`
- `docs/migration-operations-console.md`
- `docs/production-migration-guide.md`

Документацию следует читать как смесь:

- текущего поведения прототипа,
- рекомендаций по укреплению пилота,
- и перспективных планов архитектуры.

## Audit / Discovery (только чтение)

Новый namespace CLI для безопасной pre-migration диагностики:

```bash
php bin/migration-module audit:run
php bin/migration-module audit:summary
php bin/migration-module audit:report
php bin/migration-module audit:velocity --days=30 --sample-size=1000 --output=json
```

Артефакты:

- `.audit/migration_profile.json` (вход policy/planning)
- `.audit/report.html` (человекочитаемый отчёт с risk flags/strategy hints)
- `reports/change_velocity_report.json` и `reports/velocity_heatmap.json` (артефакты audit change velocity)

Подробности: `docs/audit-discovery.md`.


## Lifecycle contract (stabilization phase)

Обязательные статусы job: `created -> validated -> planned -> dry_run_completed -> running -> paused/failed/completed -> verified -> reconciled`.

- Недопустимые переходы блокируются с machine-readable ошибкой `invalid_job_transition`.
- `status/report/verify/resume` не создают новый job неявно; для отсутствующего `job_id` возвращается `job_not_found`.
- Shallow/full verify разделены по `verify_depth`; output явно содержит `checked/not_checked/limitations`.
