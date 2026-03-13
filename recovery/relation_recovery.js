export class RelationRecoveryService {
  constructor({ system_user_id = '0', placeholder_group_factory, logger = console } = {}) {
    this.system_user_id = String(system_user_id);
    this.placeholder_group_factory = placeholder_group_factory ?? (() => ({ id: `placeholder-${Date.now()}`, title: 'Migration Placeholder Group' }));
    this.logger = logger;
  }

  recoverTaskRelations({ task, sourcePortal, targetPortal, inactive_user_policy = 'create_deactivated_user' }) {
    const updates = {};
    const usersById = new Map((targetPortal.users ?? []).map((user) => [String(user.id), user]));
    const groupsById = new Map((targetPortal.groups ?? []).map((group) => [String(group.id), group]));
    const sourceUsersById = new Map((sourcePortal.users ?? []).map((user) => [String(user.id), user]));

    updates.responsible_id = this.#ensureUser({ userId: task.responsible_id, usersById, sourceUsersById, targetPortal, inactive_user_policy });
    updates.created_by = this.#ensureUser({ userId: task.created_by, usersById, sourceUsersById, targetPortal, inactive_user_policy, allowEmpty: true });

    const groupId = String(task.group_id ?? '');
    if (!groupsById.has(groupId) && groupId) {
      const placeholder = this.placeholder_group_factory(groupId);
      targetPortal.groups = [...(targetPortal.groups ?? []), placeholder];
      updates.group_id = String(placeholder.id);
      this.logger?.warn?.('[recovery-relations] placeholder group created', groupId, '->', placeholder.id);
    }

    return updates;
  }

  #ensureUser({ userId, usersById, sourceUsersById, targetPortal, inactive_user_policy, allowEmpty = false }) {
    const normalized = String(userId ?? '');
    if (!normalized && allowEmpty) {
      return '';
    }

    if (usersById.has(normalized)) {
      return normalized;
    }

    const sourceUser = sourceUsersById.get(normalized);
    if (!sourceUser) {
      return allowEmpty ? '' : this.system_user_id;
    }

    if (sourceUser.active === false && inactive_user_policy === 'assign_system_user') {
      return this.system_user_id;
    }

    const imported = {
      ...sourceUser,
      active: sourceUser.active === false ? inactive_user_policy !== 'create_deactivated_user' : true,
    };

    targetPortal.users = [...(targetPortal.users ?? []), imported];
    usersById.set(String(imported.id), imported);
    this.logger?.info?.('[recovery-relations] user imported for relation', imported.id);

    return String(imported.id);
  }
}
