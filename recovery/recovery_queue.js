export class RecoveryQueue {
  constructor({ logger = console } = {}) {
    this.logger = logger;
    this.items = [];
  }

  enqueue(operation) {
    const now = new Date().toISOString();
    const item = {
      id: operation.id ?? `recovery-${operation.type}-${operation.entity}-${operation.entity_id}-${Date.now()}`,
      type: operation.type,
      entity: operation.entity,
      entity_id: String(operation.entity_id ?? ''),
      priority: Number(operation.priority ?? 50),
      retry_count: Number(operation.retry_count ?? 0),
      max_retries: Number(operation.max_retries ?? 3),
      payload: operation.payload ?? {},
      status: operation.status ?? 'pending',
      created_at: operation.created_at ?? now,
      updated_at: now,
      last_error: operation.last_error ?? null,
    };

    this.items.push(item);
    this.logger?.info?.('[recovery-queue] enqueued', item.id, item.type, item.entity, item.entity_id);
    return item;
  }

  dequeueBatch(batchSize = 20) {
    const queue = this.items
      .filter((item) => item.status === 'pending')
      .sort((a, b) => b.priority - a.priority || a.created_at.localeCompare(b.created_at));

    const batch = queue.slice(0, batchSize);
    const now = new Date().toISOString();

    for (const item of batch) {
      item.status = 'processing';
      item.updated_at = now;
    }

    return batch;
  }

  updateStatus(itemId, status, extra = {}) {
    const item = this.items.find((entry) => entry.id === itemId);
    if (!item) {
      return null;
    }

    item.status = status;
    item.updated_at = new Date().toISOString();
    Object.assign(item, extra);
    return item;
  }

  retryableFailedItems() {
    return this.items.filter((item) => item.status === 'failed' && item.retry_count < item.max_retries);
  }

  stats() {
    const counters = { pending: 0, processing: 0, resolved: 0, failed: 0, skipped: 0 };
    for (const item of this.items) {
      counters[item.status] = (counters[item.status] ?? 0) + 1;
    }
    return counters;
  }

  all() {
    return this.items.map((item) => ({ ...item }));
  }
}
