const normalize = (value) => JSON.stringify(value ?? {}, Object.keys(value ?? {}).sort());

export class IdConflictResolver {
  constructor({ logger = console } = {}) {
    this.logger = logger;
    this.mapping = new Map();
  }

  resolve({ entity, oldRecord, existingRecord, createRecord }) {
    if (!existingRecord) {
      return { action: 'create', record: createRecord(oldRecord) };
    }

    if (normalize(oldRecord) === normalize(existingRecord)) {
      this.mapping.set(`${entity}:${oldRecord.id}`, String(existingRecord.id));
      return { action: 'reuse_existing', record: existingRecord, mapped_to: existingRecord.id };
    }

    const created = createRecord({ ...oldRecord, id: undefined });
    this.mapping.set(`${entity}:${oldRecord.id}`, String(created.id));
    this.logger?.warn?.('[recovery-id-conflict]', `${entity}:${oldRecord.id} -> ${created.id}`);

    return { action: 'created_remap', record: created, mapped_to: created.id };
  }

  resolveMappedId(entity, oldId) {
    return this.mapping.get(`${entity}:${oldId}`) ?? String(oldId);
  }

  allMappings() {
    return Object.fromEntries(this.mapping.entries());
  }
}
