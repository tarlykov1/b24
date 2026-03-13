# Migration Assistant (локальный интеллектуальный помощник)

## Назначение
`Migration Assistant` добавляет автопланирование, оценку рисков и объяснимые рекомендации для оператора миграции Bitrix24 в **закрытом контуре**.

Ключевой принцип: критичный функционал работает без внешнего AI и без интернет-доступа.

## Трёхуровневая архитектура интеллектуальности

### Уровень A — Rule-based engine (обязательный)
- Детерминированные правила и эвристики в `apps/migration-module/config/assistant/rule-pack.json`.
- Explainable decision engine в `MigrationAssistantService`.
- Локальные шаблоны remediation и checklists.

### Уровень B — Local ML / local scoring (опционально)
- Включается флагом `localMlEnabled`.
- Использует историю запусков (`history`) для корректировки рекомендаций и `next_best_action`.
- При отсутствии истории работает в режиме `cold_start`.

### Уровень C — Local LLM / on-prem inference (опционально)
- Включается флагом `localLlmEnabled`.
- Не обязателен, имеет строгий fallback на rule-based.
- Никакие внешние API не используются.

## Что делает ассистент
- Выполняет pre-flight assessment: readiness score, risk score, blockers, warnings.
- Предлагает режим запуска: full migration / incremental sync / dry-run-first.
- Формирует адаптивную очередность фаз миграции.
- Рекомендует профиль нагрузки: `safe` / `balanced` / `aggressive` с workers/batch/parallelism.
- Дает рекомендации по mapping, healing policy, verification.
- Формирует `operator checklist` и `why this recommendation` с входными факторами и ожидаемым эффектом.

## Деградация до deterministic режима
Если локальный ML/LLM недоступен, сервис продолжает работу:
- `external_ai_calls=false`;
- `deterministic_fallback=true`;
- решения строятся только из `rule-pack.json` + локальной телеметрии.

## Локальная база знаний
Файл `rule-pack.json` содержит:
- risk weights;
- пороги для high-risk факторов;
- шаблон фаз миграции;
- remediation templates.

Это versioned-конфигурация, пригодная для изменения в закрытом контуре.

## Режимы работы
Поддерживаются:
- `advisory` — только рекомендации;
- `guided` — рекомендации + действия под подтверждение оператора;
- `semi-auto` — авто-применение безопасных рекомендаций в разрешенных рамках.

По умолчанию безопасно использовать `advisory` или `guided`.

## Интеграция в UI
В `apps/migration-module/ui/admin/index.php` добавлен блок `Migration Assistant`:
- Overall readiness score;
- Recommended load profile;
- Next best action;
- Operator checklist;
- Why this recommendation.

## Тестовые сценарии
Покрыты тестами:
- режим без LLM (deterministic fallback);
- использование локальной истории запусков для корректировки рекомендаций.

Рекомендуемые ручные сценарии:
- без AI вообще;
- только rule-based;
- local ML scoring;
- local LLM enabled/disabled;
- пустая история и накопленная история;
- high-risk миграция с большим объемом файлов;
- массовые ошибки self-healing и сложный mapping.
