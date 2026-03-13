export class EntityRetryService {
  constructor({ retry_limit = 3, logger = console } = {}) {
    this.retry_limit = retry_limit;
    this.logger = logger;
  }

  scheduleRetry(queue, operation, reason = 'unknown') {
    if ((operation.retry_count ?? 0) >= (operation.max_retries ?? this.retry_limit)) {
      queue.updateStatus(operation.id, 'failed', { last_error: reason });
      this.logger?.warn?.('[recovery-retry] retry limit exceeded', operation.id, reason);
      return { status: 'failed', reason: 'retry_limit_exceeded' };
    }

    const retried = queue.updateStatus(operation.id, 'pending', {
      retry_count: Number(operation.retry_count ?? 0) + 1,
      last_error: reason,
    });

    this.logger?.info?.('[recovery-retry] scheduled', retried?.id, `attempt=${retried?.retry_count}`);
    return { status: 'pending', retry_count: retried?.retry_count ?? 0 };
  }
}
