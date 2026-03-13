const asMap = (items) => new Map((items ?? []).map((item) => [String(item.id), item]));

export function runDataIntegrityCheck({ source = {}, target = {}, errorRegistry }) {
  const checks = {
    users: checkUsers(source.users ?? [], target.users ?? [], errorRegistry),
    tasks: checkTasks(source.tasks ?? [], target.tasks ?? [], source.comments ?? [], target.comments ?? [], errorRegistry),
    groups: checkGroups(source.groups ?? [], target.groups ?? [], errorRegistry),
    comments: checkComments(source.comments ?? [], target.comments ?? [], target.tasks ?? [], errorRegistry),
  };

  return {
    status: errorRegistry.all().length === 0 ? 'ok' : 'warning',
    checks,
    relation_check: checkRelations(target, errorRegistry),
    errors: errorRegistry.all(),
  };
}

function checkUsers(sourceUsers, targetUsers, errorRegistry) {
  const sourceById = asMap(sourceUsers);
  const targetById = asMap(targetUsers);

  for (const [id, sourceUser] of sourceById.entries()) {
    const targetUser = targetById.get(id);
    if (!targetUser) {
      errorRegistry.register({ type: 'missing_entity', entity: 'user', entity_id: id, problem: 'user missing in target portal', suggested_fix: 're-run user migration batch for missing IDs' });
      continue;
    }

    if ((sourceUser.email ?? '') !== (targetUser.email ?? '')) {
      errorRegistry.register({ type: 'field_mismatch', entity: 'user', entity_id: id, problem: 'email mismatch', suggested_fix: 'synchronize email from source portal' });
    }

    if (Boolean(sourceUser.active) !== Boolean(targetUser.active)) {
      errorRegistry.register({ type: 'field_mismatch', entity: 'user', entity_id: id, problem: 'activity mismatch', suggested_fix: 'align user active flag with migration policy' });
    }
  }

  return { source_count: sourceUsers.length, target_count: targetUsers.length };
}

function checkTasks(sourceTasks, targetTasks, sourceComments, targetComments, errorRegistry) {
  const sourceById = asMap(sourceTasks);
  const targetById = asMap(targetTasks);
  const sourceCommentCountByTask = countBy(sourceComments, 'task_id');
  const targetCommentCountByTask = countBy(targetComments, 'task_id');

  for (const [id, sourceTask] of sourceById.entries()) {
    const targetTask = targetById.get(id);
    if (!targetTask) {
      errorRegistry.register({ type: 'missing_entity', entity: 'task', entity_id: id, problem: 'task missing in target portal', suggested_fix: 're-run failed task migration for this task ID' });
      continue;
    }

    for (const key of ['responsible_id', 'created_by', 'group_id']) {
      if (String(sourceTask[key] ?? '') !== String(targetTask[key] ?? '')) {
        errorRegistry.register({ type: 'field_mismatch', entity: 'task', entity_id: id, problem: `${key} mismatch`, suggested_fix: `sync ${key} using ID mapping table` });
      }
    }

    if ((sourceCommentCountByTask.get(id) ?? 0) !== (targetCommentCountByTask.get(id) ?? 0)) {
      errorRegistry.register({ type: 'missing_relation', entity: 'task', entity_id: id, problem: 'comment count mismatch', suggested_fix: 'replay comment migration for affected task' });
    }
  }

  return { source_count: sourceTasks.length, target_count: targetTasks.length };
}

function checkGroups(sourceGroups, targetGroups, errorRegistry) {
  const sourceById = asMap(sourceGroups);
  const targetById = asMap(targetGroups);

  for (const [id, sourceGroup] of sourceById.entries()) {
    const targetGroup = targetById.get(id);
    if (!targetGroup) {
      errorRegistry.register({ type: 'missing_entity', entity: 'group', entity_id: id, problem: 'group/project missing in target portal', suggested_fix: 're-migrate workgroup with members and owner' });
      continue;
    }

    if (String(sourceGroup.owner_id ?? '') !== String(targetGroup.owner_id ?? '')) {
      errorRegistry.register({ type: 'field_mismatch', entity: 'group', entity_id: id, problem: 'owner mismatch', suggested_fix: 'assign correct owner by mapped user ID' });
    }

    const sourceMembers = new Set((sourceGroup.member_ids ?? []).map(String));
    const targetMembers = new Set((targetGroup.member_ids ?? []).map(String));
    if (sourceMembers.size !== targetMembers.size || [...sourceMembers].some((memberId) => !targetMembers.has(memberId))) {
      errorRegistry.register({ type: 'field_mismatch', entity: 'group', entity_id: id, problem: 'members mismatch', suggested_fix: 'sync group membership using group roster resync' });
    }
  }

  return { source_count: sourceGroups.length, target_count: targetGroups.length };
}

function checkComments(sourceComments, targetComments, targetTasks, errorRegistry) {
  const tasks = asMap(targetTasks);
  const sourceById = asMap(sourceComments);
  const targetById = asMap(targetComments);

  for (const [id, sourceComment] of sourceById.entries()) {
    const targetComment = targetById.get(id);
    if (!targetComment) {
      errorRegistry.register({ type: 'missing_entity', entity: 'comment', entity_id: id, problem: 'comment missing in target portal', suggested_fix: 're-run comment migration for this entity ID' });
      continue;
    }

    if (!tasks.has(String(targetComment.task_id ?? ''))) {
      errorRegistry.register({ type: 'missing_relation', entity: 'comment', entity_id: id, problem: 'comment linked to missing task', suggested_fix: 'link comment to valid migrated task or archive comment' });
    }
  }

  return { source_count: sourceComments.length, target_count: targetComments.length };
}

function checkRelations(target, errorRegistry) {
  const users = asMap(target.users ?? []);
  const tasks = asMap(target.tasks ?? []);
  const groups = asMap(target.groups ?? []);

  for (const task of target.tasks ?? []) {
    if (!users.has(String(task.responsible_id ?? ''))) {
      errorRegistry.register({ type: 'missing_relation', entity: 'task', entity_id: task.id, problem: 'responsible user not found', suggested_fix: 'assign system user' });
    }
    if (!users.has(String(task.created_by ?? ''))) {
      errorRegistry.register({ type: 'missing_relation', entity: 'task', entity_id: task.id, problem: 'creator user not found', suggested_fix: 'replace created_by with migration operator user' });
    }
    if (!groups.has(String(task.group_id ?? ''))) {
      errorRegistry.register({ type: 'missing_relation', entity: 'task', entity_id: task.id, problem: 'group/project not found', suggested_fix: 'attach task to fallback migration project' });
    }
  }

  for (const comment of target.comments ?? []) {
    if (!tasks.has(String(comment.task_id ?? ''))) {
      errorRegistry.register({ type: 'missing_relation', entity: 'comment', entity_id: comment.id, problem: 'task for comment not found', suggested_fix: 're-link comment to existing migrated task' });
    }
  }

  return { checked_at: new Date().toISOString(), issue_count: errorRegistry.all().length };
}

function countBy(records, key) {
  const map = new Map();
  for (const record of records) {
    const value = String(record[key] ?? '');
    map.set(value, (map.get(value) ?? 0) + 1);
  }
  return map;
}
