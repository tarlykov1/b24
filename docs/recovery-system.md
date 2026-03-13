# Self-Healing Migration Engine

## Назначение
Self-healing engine расширяет migration pipeline: ошибки не просто логируются, а классифицируются, автоматически исправляются безопасными стратегиями и повторно исполняются, пока это безопасно.

Pipeline:
1. Error ingestion (runtime + audit + reconciliation).
2. Error taxonomy classification.
3. Strategy selection с учетом healing policy (`conservative|standard|aggressive`).
4. Safe sanitizer + dependency/mapping/duplicate/file healing.
5. Retry orchestration (backoff, jitter, cooldown, circuit breaker, retry queue, dead-letter, quarantine).
6. Reconciliation-driven repair cycle.
7. Audit + observability.

## 1) Error taxonomy
Для каждой категории задаются:
- `code`
- `category`
- `severity`
- `retryable`
- `healing_strategy`
- `escalation_policy`
- `max_attempts`

Поддерживаемые категории:
- network errors
- timeout
- rate limit
- temporary API failure
- validation error
- missing dependency
- missing user
- missing field
- missing stage
- missing enum value
- duplicate / conflict
- file transfer error
- permission error
- payload too large
- unsupported entity shape
- data corruption / malformed data
- reconciliation mismatch
- mapping error

## 2) Healing strategies
Движок поддерживает безопасные стратегии:
- retry with backoff
- retry with reduced concurrency
- recreate dependency
- refresh metadata
- auto-create missing enum/stage/field where allowed
- remap user to fallback account
- split payload
- trim oversize value
- sanitize invalid value
- skip optional field
- postpone entity to later phase
- move entity to quarantine
- downgrade bulk -> single-item
- re-read source / re-read target
- recalculate mapping
- rebuild relation references

## 3) Retry orchestration
Оркестратор:
- exponential backoff + jitter;
- max attempts per error class;
- cooldown after mass failures;
- circuit breaker;
- automatic load reduction;
- postponed items reprocessing;
- dedicated retry queue;
- dead-letter queue;
- quarantine queue.

## 4) Dependency auto-healing
Если ошибка из-за отсутствующей зависимости, engine делает:
1. Проверку фактического отсутствия;
2. Дозагрузку/создание зависимости (по policy);
3. Восстановление mapping, если зависимость уже есть;
4. Повтор исходной сущности.

Покрытие:
- missing responsible user;
- missing stage;
- missing enum value;
- missing related contact/company;
- missing pipeline;
- missing custom field.

## 5) Safe Data Sanitizer
Слой нормализации:
- trim строк;
- удаление недопустимых символов;
- email/phone normalization;
- boolean normalization;
- null-safe handling;
- empty arrays handling;
- trim oversize values;
- timezone normalization;
- money normalization;
- complex structure serialization;
- broken attachment links cleanup.

Принцип: сохранить максимум данных, не терять молча.

## 6) Mapping healing
При mapping errors:
- metadata refresh;
- mapping re-check;
- alternate mapping;
- confidence recalculation;
- fallback rule;
- retry after schema refresh;
- quarantine/manual attention, если безопасного решения нет.

## 7) Duplicate/conflict healing
Стратегии:
- exact match -> reuse target entity;
- safe merge -> update existing;
- ambiguous duplicate -> quarantine/manual review;
- ID conflict -> preserve old ID если возможно, иначе новый ID + mapping update.

## 8) File healing
Для файлов:
- re-download;
- re-upload;
- checksum verification;
- metadata/content split handling;
- skip irrecoverable broken file with explicit log;
- dedicated file retry queue;
- separate file recovery pass.

## 9) Reconciliation-driven healing
После основного прохода reconciliation выявляет:
- непренесенные сущности;
- partially migrated;
- key field mismatch;
- relation losses;
- stage/assignee/file/comment mismatch.

Далее:
- auto repair jobs;
- repair rerun;
- mapping updates;
- relation fixing;
- missing entities load;
- residual issue list для ручного разбора.

## 10) Quarantine + manual review
Quarantine item хранит:
- reason;
- attempted strategies;
- recommended next step;
- safe-to-retry flag;
- manual override eligibility.

UI manual review screen:
- filters by error type;
- recommendations;
- Retry;
- Apply Suggested Fix;
- Ignore;
- Export Error Report.

## 11) Observability и audit
Каждое healing-действие логируется:
- исходная ошибка;
- стратегия;
- число попыток;
- успех/неуспех;
- итоговые изменения;
- data loss indicator;
- degraded mode indicator.

Healing metrics:
- auto-fixed count;
- retried successfully;
- quarantined;
- unresolved;
- healed by category;
- unsafe-to-heal cases.

## 12) Safety policies
- `conservative`: минимум auto-create, больше quarantine.
- `standard`: auto-create для безопасных enum/stage/lookup.
- `aggressive`: максимально лечит безопасными обратимыми шагами.

Запрещено без явной policy:
- risky duplicate merge;
- data deletion;
- overwrite ambiguous entities;
- relation destruction;
- blind custom field creation.

## 13) Learning in reruns
Self-healing учитывает историю:
- error history;
- successful strategy reuse;
- skipping useless retries;
- manual override persistence;
- reuse of migration knowledge in next incremental runs.

## 14) Test matrix
Покрыты сценарии:
- API timeout;
- 429;
- missing stage;
- missing enum;
- missing user;
- duplicate;
- invalid payload;
- broken attachment;
- partial write;
- lost mapping;
- restart after crash;
- reconciliation mismatch.

## 15) Ограничения
Self-healing:
- не скрывает ошибок;
- не теряет данные молча;
- не делает небезопасные действия автоматически;
- отправляет неразрешимые случаи в quarantine/manual review.
