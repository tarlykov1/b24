import { RecoveryQueue } from './recovery_queue.js';
import { RelationRecoveryService } from './relation_recovery.js';
import { EntityRetryService } from './entity_retry.js';
import { IdConflictResolver } from './id_conflict_resolver.js';
import { buildRecoveryStrategies } from './recovery_strategies.js';

const ERROR_PRIORITY = {
  migration_interrupted: 100,
  missing_task: 95,
  missing_user: 90,
  missing_relation: 80,
  missing_comment: 75,
  missing_group: 70,
  duplicate_id: 65,
  data_conflict: 50,
};

const ALIASES = {
  'missing_entity:user': 'missing_user',
  'missing_entity:task': 'missing_task',
  'missing_entity:comment': 'missing_comment',
  'missing_entity:group': 'missing_group',
};

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export class RecoveryEngine {
  constructor({
    errorRegistry,
    logger = console,
    batch_size = 20,
    delay_ms = 500,
    retry_limit = 3,
    auto_recovery = false,
    system_user_id = '0',
    inactive_user_policy = 'create_deactivated_user',
  } = {}) {
    this.errorRegistry = errorRegistry;
    this.logger = logger;
    this.batch_size = batch_size;
    this.delay_ms = delay_ms;
    this.auto_recovery = auto_recovery;
    this.system_user_id = String(system_user_id);
    this.inactive_user_policy = inactive_user_policy;

    this.queue = new RecoveryQueue({ logger });
    this.retryService = new EntityRetryService({ retry_limit, logger });
    this.idConflictResolver = new IdConflictResolver({ logger });
    this.relationRecovery = new RelationRecoveryService({ system_user_id: this.system_user_id, logger });
    this.strategies = buildRecoveryStrategies({
      relationRecovery: this.relationRecovery,
      idConflictResolver: this.idConflictResolver,
    });
    this.history = [];
  }

  classifyError(error) {
    const direct = String(error.type ?? '');
    if (this.strategies[direct]) {
      return direct;
    }

    const entityAlias = ALIASES[`${direct}:${error.entity}`];
    if (entityAlias) {
      return entityAlias;
    }

    if (direct === 'field_mismatch') {
      return 'data_conflict';
    }

    return 'data_conflict';
  }

  enqueueFromRegistry() {
    const errors = this.errorRegistry?.all?.() ?? [];
    for (const error of errors) {
      if ((error.recovery_status ?? 'pending') === 'resolved') {
        continue;
      }
      const recoveryType = this.classifyError(error);
      this.queue.enqueue({
        id: error.id ? `registry-${error.id}` : undefined,
        type: recoveryType,
        entity: error.entity,
        entity_id: error.entity_id,
        priority: ERROR_PRIORITY[recoveryType] ?? 40,
        payload: { error },
        status: 'pending',
        max_retries: this.retryService.retry_limit,
      });
      this.errorRegistry?.setRecoveryStatus?.(error.id, 'pending', { recovery_type: recoveryType });
    }
  }

  async run({ sourcePortal, targetPortal, last_checkpoint } = {}) {
    this.enqueueFromRegistry();

    const context = {
      sourcePortal: sourcePortal ?? {},
      targetPortal: targetPortal ?? {},
      inactive_user_policy: this.inactive_user_policy,
      system_user_id: this.system_user_id,
      last_checkpoint,
    };

    while (true) {
      const batch = this.queue.dequeueBatch(this.batch_size);
      if (batch.length === 0) {
        break;
      }

      for (const operation of batch) {
        const error = operation.payload.error ?? {};
        const strategy = this.strategies[operation.type];

        if (!strategy) {
          this.queue.updateStatus(operation.id, 'skipped', { last_error: 'strategy_not_found' });
          this.errorRegistry?.setRecoveryStatus?.(error.id, 'skipped', { reason: 'strategy_not_found' });
          this.#recordHistory(operation, { status: 'skipped', reason: 'strategy_not_found' });
          continue;
        }

        try {
          const result = await strategy({ error, context, operation });
          if (result.status === 'resolved') {
            this.queue.updateStatus(operation.id, 'resolved', { result });
            this.errorRegistry?.setRecoveryStatus?.(error.id, 'resolved', { result });
          } else if (result.status === 'skipped') {
            this.queue.updateStatus(operation.id, 'skipped', { result, last_error: result.reason ?? null });
            this.errorRegistry?.setRecoveryStatus?.(error.id, 'skipped', { result });
          } else {
            this.queue.updateStatus(operation.id, 'failed', { result, last_error: result.reason ?? 'recovery_failed' });
            this.errorRegistry?.setRecoveryStatus?.(error.id, 'failed', { result });
            this.retryService.scheduleRetry(this.queue, operation, result.reason ?? 'recovery_failed');
          }
          this.#recordHistory(operation, result);
        } catch (recoveryError) {
          this.queue.updateStatus(operation.id, 'failed', { last_error: recoveryError.message });
          this.errorRegistry?.setRecoveryStatus?.(error.id, 'failed', { reason: recoveryError.message });
          this.retryService.scheduleRetry(this.queue, operation, recoveryError.message);
          this.#recordHistory(operation, { status: 'failed', reason: recoveryError.message });
        }
      }

      await delay(this.delay_ms);
    }

    return {
      queue: this.queue.all(),
      queue_stats: this.queue.stats(),
      mappings: this.idConflictResolver.allMappings(),
      history: [...this.history],
      target_portal: context.targetPortal,
    };
  }

  async retryFailed({ sourcePortal, targetPortal, last_checkpoint } = {}) {
    for (const failedItem of this.queue.retryableFailedItems()) {
      this.queue.updateStatus(failedItem.id, 'pending');
    }
    return this.run({ sourcePortal, targetPortal, last_checkpoint });
  }

  #recordHistory(operation, result) {
    this.history.push({
      operation_id: operation.id,
      type: operation.type,
      entity: operation.entity,
      entity_id: operation.entity_id,
      status: result.status,
      reason: result.reason ?? null,
      timestamp: new Date().toISOString(),
    });
  }
}
