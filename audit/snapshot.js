export function buildSnapshot(targetState) {
  return {
    users_count: (targetState.users ?? []).length,
    tasks_count: (targetState.tasks ?? []).length,
    comments_count: (targetState.comments ?? []).length,
    groups_count: (targetState.groups ?? []).length,
    migration_timestamp: new Date().toISOString(),
  };
}

export function diffSnapshots(previousSnapshot, currentSnapshot) {
  if (!previousSnapshot) {
    return {
      first_run: true,
      delta: {
        users: currentSnapshot.users_count,
        tasks: currentSnapshot.tasks_count,
        comments: currentSnapshot.comments_count,
        groups: currentSnapshot.groups_count,
      },
    };
  }

  return {
    first_run: false,
    delta: {
      users: currentSnapshot.users_count - (previousSnapshot.users_count ?? 0),
      tasks: currentSnapshot.tasks_count - (previousSnapshot.tasks_count ?? 0),
      comments: currentSnapshot.comments_count - (previousSnapshot.comments_count ?? 0),
      groups: currentSnapshot.groups_count - (previousSnapshot.groups_count ?? 0),
    },
  };
}
