# Architecture Overview

Автономный orchestrator строится как локальный deterministic control-plane вокруг migration workers. Он не зависит от внешнего AI/облаков и управляет жизненным циклом run-а как конечный автомат с восстановлением после падений.

Ключевые принципы:
- **Crash-safe runtime**: каждый phase change, checkpoint, decision и mapping пишутся в локальный state store.
- **First-run / Re-run split**: первый запуск оптимизирован под throughput; повторные — под delta detection/reconciliation.
- **Dependency-safe execution**: planner формирует DAG стадий и запрещает задачи с неразрешёнными ссылками.
- **Low source impact**: adaptive load governor снижает RPS, batch-size и concurrency по метрикам деградации.
- **Deterministic autonomy**: decision engine rule-based и explainable, без ML/AI.
- **Safe operator control**: Start/Pause/Resume/Safe Stop/Dry Run/Delta Sync/Reconciliation.

---

# Module Breakdown

```text
apps/migration-module/src/
  Application/
    Orchestrator/
      AutonomousMigrationOrchestrator.php
      AutonomousDecisionEngine.php
      SelfHealingLayer.php
      AdaptiveLoadGovernor.php
      StateMachine/
        MigrationStateMachine.php
      Contracts/
        PlannerInterface.php
        QueueManagerInterface.php
        WorkerPoolInterface.php
        MapperInterface.php
        ValidatorInterface.php
        ReconcilerInterface.php
        StateStoreInterface.php
        ControlApiInterface.php
  Domain/
    Orchestrator/
      OrchestratorState.php
```

Логические блоки и ответственность:
- **Orchestrator Core**: state machine, orchestration loop, retry/self-healing orchestration.
- **Migration Planner**: построение primary/delta execution plan по DAG.
- **Discovery & Schema Analyzer**: snapshot схемы source/target, detect incompatibilities.
- **State Store**: migrations/jobs/tasks/mapping/checkpoints/errors/decisions/reconciliation.
- **Queue Manager**: priority + dependency-aware scheduling, delayed retries, DLQ.
- **Worker Pool**: bounded concurrency + throttling-aware execution.
- **Dependency Resolver**: блокирует child tasks до готовности parent/mapping/stage refs.
- **ID Preservation & Remap Engine**: preserve ID first, conflict => remap with full link rewrite.
- **Validation & Reconciliation Engine**: batch validation + final consistency checks.
- **Autonomous Decision Engine**: deterministic rules for continue/pause/throttle/manual review.
- **Self-Healing Layer**: auto-fix transient failures and quarantine non-safe cases.
- **Control API/UI**: локальный CLI/TUI/API.
- **Audit & Logging**: event, decision, performance, error timeline, export reports.

---

# State Machine

Состояния:
`INIT, PRECHECK, DISCOVERY, PLAN_GENERATION, DRY_RUN, WAITING_CONFIRMATION, EXECUTING, THROTTLED, PAUSED, DELTA_SYNC, RECONCILIATION, SELF_HEALING, PARTIAL_BLOCK, COMPLETED, COMPLETED_WITH_WARNINGS, FAILED, SAFE_STOPPED, ROLLBACK_PARTIAL`.

Базовые переходы:
- INIT → PRECHECK → DISCOVERY → PLAN_GENERATION
- PLAN_GENERATION → DRY_RUN (если dry-run)
- PLAN_GENERATION/DRY_RUN → WAITING_CONFIRMATION
- WAITING_CONFIRMATION → EXECUTING | SAFE_STOPPED
- EXECUTING → THROTTLED | SELF_HEALING | PAUSED | DELTA_SYNC | RECONCILIATION | PARTIAL_BLOCK | SAFE_STOPPED | FAILED
- THROTTLED → EXECUTING | PAUSED | FAILED
- SELF_HEALING → EXECUTING | PARTIAL_BLOCK | FAILED
- DELTA_SYNC → RECONCILIATION
- RECONCILIATION → COMPLETED | COMPLETED_WITH_WARNINGS | PARTIAL_BLOCK
- PARTIAL_BLOCK → EXECUTING | ROLLBACK_PARTIAL | FAILED
- ROLLBACK_PARTIAL → COMPLETED_WITH_WARNINGS | FAILED

Условия stop/go:
- **Safe continue**: transient errors в пределах retry budget, integrity >= threshold.
- **Require stop**: schema-breaking mismatch, массовая loss of references, exceeded DLQ threshold.

---

# Config Example

```yaml
orchestrator:
  job:
    mode: initial_load # initial_load|delta_sync|reconciliation
    dry_run: false
    profile: balanced # safe|balanced|aggressive

  source:
    portal_url: "http://old-b24.local"
    auth:
      type: local_token
      token_file: "/etc/b24/source.token"

  target:
    portal_url: "http://new-b24.local"
    auth:
      type: local_token
      token_file: "/etc/b24/target.token"

  first_run_strategy:
    expensive_existence_scan: false
    preserve_all_checkpoints: true
    mapping_mode: preserve_id_with_remap

  rerun_strategy:
    delta_detection: high_watermark_and_hash
    include_failed_from_previous_runs: true
    reconcile_before_commit: true
    deduplicate_by: [entity_type, source_id, payload_hash]

  inactive_users:
    cutoff_date: "2023-01-01"
    mode: exclude_before_cutoff # exclude_before_cutoff|include_all
    tasks_policy: reassign_to_system # delete|reassign_to_system|keep_owner
    system_user_id: 1

  id_preservation:
    try_keep_original_id: true
    on_conflict: remap
    collision_registry: "state/entity_mapping"

  load_control:
    source_limits:
      rps: 8
      rpm: 420
    target_limits:
      rps: 15
      rpm: 900
    heavy_entities:
      names: [files, comments]
      rps: 2
      batch_size: 20
    dynamic:
      min_rps: 1
      max_rps: 25
      cooldown_sec: 60
      backoff_multiplier: 2.0
      burst_protection_window_sec: 15

  queue:
    default_batch_size: 100
    max_batch_size: 500
    concurrency: 12
    delayed_retry_ms: [5000, 15000, 60000, 180000]
    dead_letter_threshold: 5

  retry:
    max_attempts_transient: 8
    max_attempts_dependency: 20
    max_attempts_validation: 3

  reconciliation:
    compare_counts: true
    compare_checksums: true
    verify_relationships: true
    verify_required_fields: true
    verify_stage_mapping: true
    verify_attachments: true
```

---

# State Store Schema

Рекомендуемый локальный SQLite/PostgreSQL schema:
- `migrations(id, mode, status, current_state, started_at, ended_at, profile, is_rerun)`
- `jobs(id, migration_id, entity_type, phase, status, priority, depends_on, attempts, last_error, updated_at)`
- `tasks(id, job_id, dedup_key, payload, status, attempts, scheduled_at, worker_id, latency_ms)`
- `entity_mapping(id, migration_id, entity_type, source_id, target_id, preserved_original, remap_reason, created_at)`
- `checkpoints(id, migration_id, scope, marker, snapshot_hash, created_at)`
- `error_registry(id, migration_id, task_id, error_class, error_code, retryable, quarantined, details, created_at)`
- `decision_log(id, migration_id, state, action, reason, input_metrics, created_at)`
- `reconciliation_results(id, migration_id, entity_type, source_count, target_count, checksum_match, integrity_ok, warnings, created_at)`
- `manual_review_queue(id, migration_id, entity_type, source_id, issue_type, payload, status)`
- `dead_letter_queue(id, migration_id, task_id, reason, payload, attempts, created_at)`

---

# Main Orchestrator Loop Pseudocode

```text
run(job):
  transition INIT -> PRECHECK
  run prechecks (config, creds, capacity, cutoff policy)

  transition PRECHECK -> DISCOVERY
  source_schema, target_schema = discovery.scan()
  incompatibilities = schema_analyzer.diff(source_schema, target_schema)
  if blocking incompatibilities: fail

  transition DISCOVERY -> PLAN_GENERATION
  plan = planner.build(mode, schemas, dependencies, is_rerun)
  queue.seed(plan)

  if dry_run:
    transition -> DRY_RUN
    simulate(plan)

  transition -> WAITING_CONFIRMATION
  await operator confirmation

  transition -> EXECUTING
  while queue.not_empty():
    if control.pause: transition -> PAUSED; persist; return
    if control.safe_stop: transition -> SAFE_STOPPED; persist; return

    task = queue.pull_ready()
    if dependency_resolver.blocked(task): queue.defer(task); continue

    load_governor.apply_limits(task.entity_type)
    result = worker_pool.execute(task)

    validation = validator.batch_validate(result)
    if validation.ok:
      mapper.persist_links(result)
      checkpoint.save(task.cursor)
      queue.ack(task)
      continue

    decision = decision_engine.decide(metrics + error + task_context)
    decision_log.append(decision)

    if decision.action == SELF_HEAL:
      transition -> SELF_HEALING
      healing_result = self_healing.apply(task, validation.error)
      if healing_result.requeue: queue.retry(task, healing_result.delay)
      elif healing_result.quarantine: queue.quarantine(task)
      transition -> EXECUTING
    elif decision.action == THROTTLE:
      transition -> THROTTLED
      load_governor.decrease()
      transition -> EXECUTING
    elif decision.action == PARTIAL_BLOCK:
      transition -> PARTIAL_BLOCK
      persist + notify operator
      return
    else:
      transition -> FAILED
      return

  if mode in [delta_sync, rerun]: transition -> DELTA_SYNC
  transition -> RECONCILIATION
  recon = reconciler.run(full_or_delta)

  finalize status:
    COMPLETED / COMPLETED_WITH_WARNINGS / PARTIAL / BLOCKED / FAILED
  generate final report
```

---

# Decision Engine Rules

```text
Inputs: error_rate, rate_limit_hits, dlq_size, manual_queue_size, missing_links, pause_signal

1) if safe_stop_requested => SAFE_STOPPED
2) if blocking_schema_mismatch => FAILED
3) if rate_limit_hits >= threshold OR source_latency_p95 high => THROTTLED
4) if transient_error and retry_budget_left => SELF_HEALING + retry
5) if missing_dependency and parent expected soon => defer + delayed retry
6) if duplicate detected and deterministic merge exists => auto-resolve + continue
7) if non-retryable data corruption => quarantine + PARTIAL_BLOCK
8) if manual_queue_size exceeds threshold => PARTIAL_BLOCK
9) if reconciliation mismatch <= warning threshold => COMPLETED_WITH_WARNINGS
10) else => EXECUTING/COMPLETED
```

Каждое правило пишет explainable запись в `decision_log`.

---

# Self-Healing Rules

```text
rate_limit(429): cooldown + exponential backoff + reduce concurrency
transient_network/503: retry with jitter, keep attempt budget
missing_dependency: defer until dependency checkpoint appears
partial_import: idempotent replay of failed sub-operations only
schema_mismatch_non_blocking: apply safe fallback mapping + warning
duplicate_entity: detect by deterministic signature -> attach existing mapping
attachment_failure: move to heavy-entity retry lane with lower RPS
poison_message: move to DLQ + manual review ticket
```

Компенсирующие действия (где rollback невозможен):
- ставим `status=compensating` и создаём corrective task (update/unlink/reassign).
- сохраняем trace в audit trail + manual review action list.

---

# Adaptive Load Control

Алгоритм load governor:

```text
every control_window (e.g., 30s):
  collect source_p95_latency, source_5xx, source_429, queue_lag, worker_utilization

  health_score = weighted_sum(
    latency_norm,
    error_norm,
    rate_limit_norm,
    lag_norm
  )

  if health_score > 0.8:
    rps = max(min_rps, rps * 0.7)
    batch_size = max(min_batch, batch_size * 0.8)
    concurrency = max(1, concurrency - 1)
    state = THROTTLED
    cooldown_timer = now + cooldown
  elif health_score < 0.3 and cooldown_timer passed:
    rps = min(max_rps, rps + step)
    batch_size = min(max_batch, batch_size + step_batch)
    concurrency = min(max_concurrency, concurrency + 1)

  apply separate caps for heavy entities (files/comments)
  enforce burst protection token bucket per endpoint
```

---

# Interface Contracts

- `PlannerInterface::buildPlan(context): plan`
- `QueueManagerInterface::seed/pullNext/ack/deadLetter`
- `WorkerPoolInterface::execute(task): result`
- `MapperInterface::mapId/remapReferences`
- `ValidatorInterface::validateBatch(jobId, result)`
- `ReconcilerInterface::reconcile(jobId, deltaOnly)`
- `StateStoreInterface::saveGlobalState/loadGlobalState/appendDecision/checkpoint`
- `ControlApiInterface::isPauseRequested/isSafeStopRequested/hasRunConfirmation`

Контракты добавлены в код как расширяемый каркас и должны реализовываться инфраструктурным слоем (CLI, REST, DB-backed store, queue backend).

---

# Failure Scenarios

| Error Class | Detection | Auto-Heal | Retry Strategy | Manual Review | Run Status Impact |
|---|---|---|---|---|---|
| Rate limit / 429 | HTTP status + headers | Да | exp backoff + cooldown | Нет | THROTTLED |
| Transient network | timeout/socket reset | Да | jitter retry up to N | Нет | EXECUTING |
| Missing dependency | mapper/dependency resolver miss | Да | delayed retry queue | Иногда | EXECUTING/PARTIAL_BLOCK |
| Duplicate ID/entity | target conflict check | Да (deterministic merge/remap) | immediate retry | Иногда | EXECUTING/WARNING |
| Schema mismatch (critical) | discovery + runtime validation | Нет | не ретраим | Да | FAILED/PARTIAL_BLOCK |
| Partial batch import | post-batch validator mismatch | Да | replay failed subset | Иногда | SELF_HEALING |
| Attachment transfer failure | checksum/size mismatch | Да | heavy-lane retries | Да после budget | COMPLETED_WITH_WARNINGS |
| Poison message | repeated deterministic failure | Нет | none -> DLQ | Да | PARTIAL_BLOCK |

---

# Logging & Audit

Структура логов (JSONL):
- `timestamp`, `job_id`, `state`, `phase`, `entity_type`, `entity_id`, `action`, `result`, `latency_ms`, `attempt`, `decision`, `error_class`, `error_code`, `mapping_ref`, `checkpoint_ref`.

Пример audit trail:
```json
{"ts":"2026-01-10T10:00:00Z","job":"m-42","state":"EXECUTING","entity":"deal","id":"d-1001","action":"upsert","result":"ok","mapping_ref":"deal:1001->1001"}
{"ts":"2026-01-10T10:00:04Z","job":"m-42","state":"SELF_HEALING","entity":"file","id":"f-98","action":"retry","decision":"cooldown_backoff","attempt":2}
{"ts":"2026-01-10T10:00:45Z","job":"m-42","state":"RECONCILIATION","action":"checksum_compare","result":"warning","details":"attachments_missing=3"}
```

---

# MVP vs Production

## MVP (обязательно)
- state machine + persistent state store + checkpoint resume
- planner + dependency order for ключевых сущностей
- queue + worker with retry/DLQ
- ID preserve/remap + reference rewrite
- first-run/rerun split (delta by watermark)
- CLI controls (start/pause/resume/safe-stop)
- structured logs + final reconciliation report

## Enterprise Production
- distributed worker pool и приоритетные очереди по классам сущностей
- advanced schema evolution/fallback rules
- deterministic conflict policies per entity type
- SLA-based adaptive throttling (multi-window control)
- rich TUI/web dashboard (diff/manual review screens)
- secure secrets vault on-prem (HSM/KMS локального контура)
- chaos testing, load testing на synthetic datasets, canary migration profile

---

# Code Skeleton

В кодовую базу добавлены:
- `AutonomousMigrationOrchestrator` — основной orchestrator loop с явными фазами, контролем pause/safe-stop, decision + self-healing обработкой.
- `MigrationStateMachine` + `OrchestratorState` — явные состояния и валидные переходы.
- `AutonomousDecisionEngine` — rule-based deterministic policy.
- `SelfHealingLayer` — стратегии автоисправления для типовых ошибок.
- `AdaptiveLoadGovernor` — адаптивная регулировка RPS/batch/concurrency.
- `Contracts/*` — интерфейсы planner/queue/worker/mapper/validator/reconciler/state store/control API.

Этот каркас уже пригоден как production blueprint и расширяется под конкретные Bitrix adapters, persistence backend и UI слой.
