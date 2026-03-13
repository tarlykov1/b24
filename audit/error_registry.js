export class ErrorRegistry {
  constructor(logger = console, clock = () => new Date()) {
    this.logger = logger;
    this.clock = clock;
    this.entries = [];
  }

  register({ type, entity, entity_id, problem, suggested_fix, timestamp }) {
    const entry = {
      type,
      entity,
      entity_id: String(entity_id ?? ''),
      problem,
      suggested_fix,
      timestamp: timestamp ?? this.clock().toISOString(),
    };

    this.entries.push(entry);
    this.logger?.warn?.('[migration-audit]', `${entry.type}:${entry.entity}:${entry.entity_id}`, entry.problem, '→', entry.suggested_fix);

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
    const headers = ['type', 'entity', 'entity_id', 'problem', 'suggested_fix', 'timestamp'];
    const rows = this.entries.map((entry) =>
      headers
        .map((header) => `"${String(entry[header] ?? '').replaceAll('"', '""')}"`)
        .join(',')
    );

    return [headers.join(','), ...rows].join('\n');
  }
}
