export function generateMigrationReport({ migration, integrity, portalDiff, errors, snapshot, stats }) {
  return {
    migration_info: {
      migration_date: migration.migration_date,
      duration_ms: migration.duration_ms,
      entity_counts: stats,
    },
    users: migration.users,
    tasks: migration.tasks,
    comments: migration.comments,
    integrity,
    portal_diff: portalDiff,
    errors,
    snapshot,
  };
}

export function reportToJSON(report) {
  return JSON.stringify(report, null, 2);
}

export function reportToCSV(report) {
  const rows = [
    ['section', 'metric', 'value'],
    ['migration_info', 'migration_date', report.migration_info.migration_date],
    ['migration_info', 'duration_ms', report.migration_info.duration_ms],
    ['users', 'total_migrated', report.users.total_migrated],
    ['users', 'skipped', report.users.skipped],
    ['users', 'deactivated', report.users.deactivated],
    ['tasks', 'migrated', report.tasks.migrated],
    ['tasks', 'updated', report.tasks.updated],
    ['tasks', 'errors', report.tasks.errors],
    ['comments', 'migrated', report.comments.migrated],
    ['comments', 'skipped', report.comments.skipped],
    ['errors', 'count', report.errors.length],
  ];

  return rows
    .map((row) => row.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(','))
    .join('\n');
}

export function reportToHTML(report) {
  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Migration Audit Report</title>
  <style>
    body { font-family: sans-serif; margin: 24px; }
    .ok { color: #0a7f2e; }
    .warn { color: #a76600; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 8px; }
  </style>
</head>
<body>
  <h1>Migration Audit Report</h1>
  <p><b>Migration date:</b> ${report.migration_info.migration_date}</p>
  <p><b>Duration:</b> ${report.migration_info.duration_ms} ms</p>

  <h2>Portal Diff Summary</h2>
  <pre>${escapeHtml(report.portal_diff.text_report ?? '')}</pre>

  <h2>Error Registry (${report.errors.length})</h2>
  <table>
    <thead><tr><th>Type</th><th>Entity</th><th>ID</th><th>Problem</th><th>Suggested fix</th><th>Timestamp</th></tr></thead>
    <tbody>
      ${report.errors.map((item) => `<tr><td>${escapeHtml(item.type)}</td><td>${escapeHtml(item.entity)}</td><td>${escapeHtml(item.entity_id)}</td><td>${escapeHtml(item.problem)}</td><td>${escapeHtml(item.suggested_fix)}</td><td>${escapeHtml(item.timestamp)}</td></tr>`).join('')}
    </tbody>
  </table>
</body>
</html>`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
