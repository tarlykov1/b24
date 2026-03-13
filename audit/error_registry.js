export class ErrorRegistry {
  constructor(logger = console, clock = () => new Date()) {
    this.logger = logger;
    this.clock = clock;
    this.entries = [];
    this.sequence = 1;
  }

  register({ type, entity, entity_id, problem, suggested_fix, timestamp, details }) {
    const entry = {
      id: this.sequence++,
      type,
      entity,
      entity_id: String(entity_id ?? ''),
      problem,
      suggested_fix,
      details: details ?? {},
      timestamp: timestamp ?? this.clock().toISOString(),
      recovery_status: 'pending',
      recovery_type: null,
      recovery_result: null,
      recovery_history: [],
    };

    this.entries.push(entry);
    this.logger?.warn?.('[migration-audit]', `${entry.type}:${entry.entity}:${entry.entity_id}`, entry.problem, '→', entry.suggested_fix);

    return entry;
  }

  setRecoveryStatus(entryId, status, payload = {}) {
    const entry = this.entries.find((item) => item.id === entryId);
    if (!entry) {
      return null;
    }

    entry.recovery_status = status;
    if (payload.recovery_type) {
      entry.recovery_type = payload.recovery_type;
    }

    const historyEvent = {
      status,
      payload,
      timestamp: this.clock().toISOString(),
    };

    entry.recovery_result = payload;
    entry.recovery_history.push(historyEvent);
    return entry;
  }

  all() {
    return [...this.entries];
  }

  since(isoTimestamp) {
    const sinceTime = new Date(isoTimestamp).getTime();
    return this.entries.filter((entry) => new Date(entry.timestamp).getTime() > sinceTime);
  }

  toJSON() {
    return JSON.stringify(this.entries, null, 2);
  }

  toCSV() {
    const headers = ['id', 'type', 'entity', 'entity_id', 'problem', 'suggested_fix', 'timestamp', 'recovery_status', 'recovery_type'];
    const rows = this.entries.map((entry) =>
      headers
        .map((header) => `"${String(entry[header] ?? '').replaceAll('"', '""')}"`)
        .join(',')
    );

    return [headers.join(','), ...rows].join('\n');
  }
}
