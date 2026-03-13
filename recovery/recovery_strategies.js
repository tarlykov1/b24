const asMap = (items = []) => new Map(items.map((item) => [String(item.id), item]));

const withResolved = (changes = {}, details = {}) => ({ status: 'resolved', changes, details });

export function buildRecoveryStrategies({ relationRecovery, idConflictResolver }) {
  return {
    missing_user: async ({ error, context }) => {
      const userId = String(error.entity_id ?? error.details?.user_id ?? '');
      const sourceUser = asMap(context.sourcePortal.users).get(userId);
      if (!sourceUser) {
        return { status: 'failed', reason: 'user_not_found_in_source' };
      }

      if (sourceUser.active === false && context.inactive_user_policy === 'assign_system_user') {
        return withResolved({ assigned_system_user: context.system_user_id }, { strategy: 'assign_system_user' });
      }

      const cloned = {
        ...sourceUser,
        active: sourceUser.active === false ? context.inactive_user_policy !== 'create_deactivated_user' : true,
      };
      context.targetPortal.users = [...(context.targetPortal.users ?? []), cloned];

      return withResolved({ imported_user_id: cloned.id }, { strategy: context.inactive_user_policy ?? 'create_user' });
    },

    missing_task: async ({ error, context }) => {
      const taskId = String(error.entity_id);
      const sourceTask = asMap(context.sourcePortal.tasks).get(taskId);
      if (!sourceTask) {
        return { status: 'failed', reason: 'task_not_found_in_source' };
      }

      const task = { ...sourceTask };
      const relationUpdates = relationRecovery.recoverTaskRelations({
        task,
        sourcePortal: context.sourcePortal,
        targetPortal: context.targetPortal,
        inactive_user_policy: context.inactive_user_policy,
      });
      Object.assign(task, relationUpdates);

      context.targetPortal.tasks = [...(context.targetPortal.tasks ?? []), task];

      const comments = (context.sourcePortal.comments ?? []).filter((comment) => String(comment.task_id) === taskId);
      const existingCommentIds = new Set((context.targetPortal.comments ?? []).map((comment) => String(comment.id)));
      const missingComments = comments.filter((comment) => !existingCommentIds.has(String(comment.id)));
      context.targetPortal.comments = [...(context.targetPortal.comments ?? []), ...missingComments.map((comment) => ({ ...comment }))];

      return withResolved({ imported_task_id: task.id, imported_comments: missingComments.length, relation_updates: relationUpdates });
    },

    missing_comment: async ({ error, context }) => {
      const commentId = String(error.entity_id);
      const sourceComment = asMap(context.sourcePortal.comments).get(commentId);
      if (!sourceComment) {
        return { status: 'failed', reason: 'comment_not_found_in_source' };
      }

      const taskId = String(sourceComment.task_id ?? '');
      const hasTask = asMap(context.targetPortal.tasks).has(taskId);
      if (!hasTask) {
        return { status: 'failed', reason: 'missing_parent_task' };
      }

      const exists = asMap(context.targetPortal.comments).has(commentId);
      if (exists) {
        return { status: 'skipped', reason: 'comment_already_exists' };
      }

      context.targetPortal.comments = [...(context.targetPortal.comments ?? []), { ...sourceComment }];
      return withResolved({ imported_comment_id: sourceComment.id });
    },

    missing_relation: async ({ error, context }) => {
      const taskId = String(error.entity_id);
      const tasksById = asMap(context.targetPortal.tasks);
      const task = tasksById.get(taskId);
      if (!task) {
        return { status: 'failed', reason: 'task_not_found_in_target' };
      }

      const relationUpdates = relationRecovery.recoverTaskRelations({
        task,
        sourcePortal: context.sourcePortal,
        targetPortal: context.targetPortal,
        inactive_user_policy: context.inactive_user_policy,
      });
      Object.assign(task, relationUpdates);

      return withResolved({ task_id: taskId, relation_updates: relationUpdates });
    },

    missing_group: async ({ error, context }) => {
      const groupId = String(error.entity_id);
      const sourceGroup = asMap(context.sourcePortal.groups).get(groupId);
      if (!sourceGroup) {
        return { status: 'failed', reason: 'group_not_found_in_source' };
      }

      context.targetPortal.groups = [...(context.targetPortal.groups ?? []), { ...sourceGroup }];
      return withResolved({ imported_group_id: sourceGroup.id });
    },

    duplicate_id: async ({ error, context }) => {
      const entityType = error.entity;
      const oldRecord = asMap(context.sourcePortal[`${entityType}s`] ?? []).get(String(error.entity_id));
      if (!oldRecord) {
        return { status: 'failed', reason: 'source_record_missing_for_duplicate' };
      }

      const targetCollectionKey = `${entityType}s`;
      const targetRecords = context.targetPortal[targetCollectionKey] ?? [];
      const existing = asMap(targetRecords).get(String(oldRecord.id));
      const resolved = idConflictResolver.resolve({
        entity: entityType,
        oldRecord,
        existingRecord: existing,
        createRecord: (payload) => {
          const nextId = Math.max(0, ...targetRecords.map((item) => Number(item.id) || 0)) + 1;
          const created = { ...payload, id: nextId };
          context.targetPortal[targetCollectionKey] = [...targetRecords, created];
          return created;
        },
      });

      return withResolved({ action: resolved.action, mapped_to: resolved.mapped_to ?? resolved.record?.id });
    },

    data_conflict: async ({ error }) => ({ status: 'skipped', reason: `manual_review_required:${error.problem}` }),

    migration_interrupted: async ({ context }) => withResolved({ resumed_from_checkpoint: context.last_checkpoint ?? null }),
  };
}
