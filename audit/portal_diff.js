const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export async function runPortalDiffCheck({
  entities = ['users', 'tasks', 'comments', 'groups'],
  fetchOld,
  fetchNew,
  batch_size = 50,
  delay_ms = 300,
}) {
  const output = { generated_at: new Date().toISOString(), entities: {}, text_report: '' };
  const lines = [];

  for (const entity of entities) {
    const oldData = await batchedCollect(fetchOld, entity, batch_size, delay_ms);
    const newData = await batchedCollect(fetchNew, entity, batch_size, delay_ms);

    const oldIds = new Set(oldData.map((item) => String(item.id)));
    const newIds = new Set(newData.map((item) => String(item.id)));
    const missingInNew = [...oldIds].filter((id) => !newIds.has(id));

    const keyFieldMismatches = compareKeyFields(oldData, newData, entity);
    output.entities[entity] = {
      old_count: oldData.length,
      new_count: newData.length,
      missing_in_new: missingInNew,
      key_field_mismatches: keyFieldMismatches,
    };

    lines.push(capitalize(entity));
    lines.push(`Old portal: ${oldData.length}`);
    lines.push(`New portal: ${newData.length}`);
    lines.push(missingInNew.length > 0 ? `Difference: ${missingInNew.length} missing` : 'OK');
    lines.push('');
  }

  output.text_report = lines.join('\n').trim();
  return output;
}

async function batchedCollect(fetcher, entity, batchSize, delayMs) {
  let offset = 0;
  const data = [];

  while (true) {
    const chunk = await fetcher(entity, { offset, limit: batchSize });
    if (!Array.isArray(chunk) || chunk.length === 0) {
      break;
    }

    data.push(...chunk);
    offset += chunk.length;

    if (chunk.length < batchSize) {
      break;
    }

    await delay(delayMs);
  }

  return data;
}

function compareKeyFields(oldData, newData, entity) {
  const fieldsByEntity = {
    users: ['email', 'active'],
    tasks: ['responsible_id', 'created_by', 'group_id', 'status'],
    comments: ['task_id', 'author'],
    groups: ['owner_id'],
  };

  const fields = fieldsByEntity[entity] ?? [];
  const newById = new Map(newData.map((item) => [String(item.id), item]));
  const mismatches = [];

  for (const oldItem of oldData) {
    const id = String(oldItem.id);
    const newItem = newById.get(id);
    if (!newItem) {
      continue;
    }

    for (const field of fields) {
      if (String(oldItem[field] ?? '') !== String(newItem[field] ?? '')) {
        mismatches.push({ id, field, old: oldItem[field] ?? null, new: newItem[field] ?? null });
      }
    }
  }

  return mismatches;
}

function capitalize(value) {
  return value.charAt(0).toUpperCase() + value.slice(1);
}
