# Recovery System для миграции Bitrix24

## Назначение
Recovery System автоматически обрабатывает ошибки из `audit/error_registry`, выполняет безопасные исправления и позволяет продолжать миграцию без ручного перебора проблем.

Основной pipeline:
1. Чтение ошибок из Error Registry.
2. Классификация ошибки.
3. Подбор стратегии восстановления.
4. Выполнение восстановления (с retry и throttling).
5. Запись результата в очередь, историю и статус ошибки.

Поддерживаемые статусы операций восстановления:
- `pending`
- `processing`
- `resolved`
- `failed`
- `skipped`

## Типы ошибок и стратегии
Поддерживаются типы:
- `missing_user`
- `missing_relation`
- `missing_task`
- `missing_comment`
- `missing_group`
- `duplicate_id`
- `data_conflict`
- `migration_interrupted`

Дополнительно учитываются алиасы аудита:
- `missing_entity:user` → `missing_user`
- `missing_entity:task` → `missing_task`
- `missing_entity:comment` → `missing_comment`
- `missing_entity:group` → `missing_group`
- `field_mismatch` → `data_conflict`

### Missing User
Recovery проверяет пользователя на старом портале:
- если найден и активен — переносит в target;
- если неактивен — применяет policy:
  - `create_user`
  - `create_deactivated_user`
  - `assign_system_user`

### Missing Task
Recovery:
- проверяет задачу на source;
- повторно переносит задачу;
- восстанавливает связи (`responsible`, `creator`, `group`, `comments`, `subtasks`*);
- переносит только отсутствующие комментарии без дубликатов.

> 
> `subtasks` помечены как расширение на стороне реального адаптера Bitrix API, в каркасе заложена точка расширения.

### Missing Comment
Recovery:
- находит parent task;
- загружает комментарий с source;
- добавляет комментарий только если его ещё нет на target.

### Missing Relation
Recovery:
- валидирует связанные сущности;
- если сущность есть — восстанавливает reference;
- если нет — применяет fallback:
  - system user;
  - placeholder group;
  - пустое поле (для разрешённых полей).

### Duplicate ID
Recovery:
1. сравнивает payload source/target;
2. если эквивалентны — использует существующий объект;
3. если конфликтуют — создаёт новый объект и пишет mapping `old_id -> new_id`.

Mapping хранится в `IdConflictResolver` и используется в relation recovery.

### Data Conflict
Небезопасные/двусмысленные конфликты помечаются как `skipped` для ручной проверки.

### Migration Interrupted
Запись в `resolved` с фиксацией resume-checkpoint.

## Recovery Queue
Структура операции очереди:
- `type`
- `entity`
- `entity_id`
- `priority`
- `retry_count`
- `created_at`

Дополнительно сохраняются `status`, `updated_at`, `last_error`, `max_retries`.

Очередь:
- выполняет операции пакетами (`batch_size`, по умолчанию 20);
- сортирует по priority;
- между пакетами выдерживает задержку (`delay_ms`, по умолчанию 500ms), чтобы не перегружать source-портал.

## Retry Migration
`EntityRetryService`:
- переводит неуспешные операции обратно в `pending`;
- ограничивает число повторов (`retry_limit`, по умолчанию 3);
- после превышения лимита оставляет `failed`.

Кнопка `Retry Failed` в UI перезапускает только retryable failed операции.

## Интеграция с Audit
- Recovery читает ошибки из `ErrorRegistry::all()`.
- После обработки обновляет `recovery_status` через `setRecoveryStatus`.
- В ошибке сохраняется `recovery_history` и `recovery_result`.
- `MigrationAuditModule` предоставляет API:
  - `runRecovery(...)`
  - `retryFailedRecovery(...)`
  - `ignoreError(errorId, reason)`

## Ручной запуск Recovery
Через UI:
1. Выполнить `Run Audit`.
2. Открыть вкладку `Recovery`.
3. Нажать `Run Recovery`.
4. При необходимости нажать `Retry Failed`.
5. Для non-actionable ошибки использовать `Ignore Error`.

## Анализ ошибок
Рекомендуемый порядок:
1. Сортировать по `recovery_status`.
2. Проверять `failed` с `last_error`.
3. Сверять `mapping` при `duplicate_id`.
4. Для `skipped` проводить ручной change-review.

## Безопасность
- Recovery не выполняет опасные деструктивные операции автоматически.
- Все изменения логируются (`history`, queue status transitions, `error_registry` history).
- История восстановления сохраняется в памяти каркаса и включается в `finalizeMigration()`.
- Для потенциально рискованных конфликтов применяется `skipped` + manual review.
