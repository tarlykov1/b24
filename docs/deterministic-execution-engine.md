# Deterministic Execution Engine

Новый слой deterministic runtime добавлен поверх prototype storage/CLI.

## Компоненты

- `ExecutionPlanBuilder` — стабильный `plan_id`/`plan_hash` из конфига, идентичностей source/target, scope, cutoff и режима.
- `ExecutionGraphBuilder` — dependency-aware граф с детерминированной сортировкой.
- `DeterministicBatchScheduler` — стабильные батчи `batch_id` + `stable_order`.
- `ExecutionEngine` — выполнение write-фазы с replay-protection, id-reservation, verification.
- `CheckpointManager` — checkpoint/resume на таблице `checkpoint_state`.
- `StateStore` — запись плана и батчей в state store.
- `IdReservationService` — deterministic policy (`preserve_if_possible`, `preserve_strict`, `allocate_on_conflict`, `fail_on_conflict`).
- `RelationResolver` — relation-safe attach c unresolved-очередью в `relation_map`.
- `ReplayProtectionService` — stable idempotency key и защита от повторной записи.
- `FailureClassifier` + `RetryPolicy` — классификация и bounded retries.
- `FilesystemTransferEngine` — staged transfer + checksum verify + resume-ориентированное состояние в `file_transfer_map`.
- `DbReadFacade` / `RestWriteFacade` — явное разделение read/write обязанностей.

## Семантика запуска

- `execute` — запускает deterministic план (создаёт, если отсутствует).
- `resume --job=<id>` — продолжает тот же job + тот же plan.
- `retry` — повтор execution в resume-режиме c reuse mapping/replay guard.
- `reconcile` — повтор с тем же детерминированным планом + state reuse.

## Таблицы state store

Добавлены:

- `migration_jobs`, `migration_plans`
- `execution_batches`, `execution_steps`
- `id_reservations`, `relation_map`, `file_transfer_map`
- `verification_results`, `failure_events`
- `checkpoint_state`, `replay_guard`
- `run_locks`, `source_snapshots`, `job_metrics`

## CLI команды

Новые top-level команды:

- `config:validate`
- `plan:show`, `plan:export`
- `retry`, `reconcile`
- `checkpoint:list`, `checkpoint:show`
- `failures:list`
- `files:verify`
- `mapping:export`

## Гарантии/invariants

- Stable plan hash и stable batch order при одинаковом входе.
- Resume не создаёт новый job автоматически.
- Повторная запись блокируется `replay_guard`.
- ID-conflict policy полностью детерминирована и фиксируется в `id_reservations`.
- Ошибки классифицируются и фиксируются в `failure_events`.
