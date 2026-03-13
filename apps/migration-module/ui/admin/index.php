<?php

declare(strict_types=1);

$validation = [
    'integrity' => 'Pending',
    'statistics' => 'Pending',
    'warnings' => 0,
    'errors' => 0,
    'problems' => [],
];
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bitrix24 Migration Admin</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; }
        .panel { border: 1px solid #ccc; padding: 1rem; margin-bottom: 1rem; border-radius: 8px; }
        .actions button { margin-right: .5rem; }
        form.filters { display: grid; gap: .5rem; grid-template-columns: repeat(4, minmax(160px, 1fr)); }
        .tabs { display: flex; gap: .5rem; margin-bottom: 1rem; }
        .tab-button { border: 1px solid #bbb; background: #f5f5f5; border-radius: 6px; padding: .4rem .8rem; cursor: pointer; }
        .tab-button.active { background: #dfefff; border-color: #95b4ff; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        pre { background: #f8f8f8; border-radius: 6px; padding: 1rem; overflow-x: auto; }
    </style>
</head>
<body>
<h1>Migration Control Panel</h1>

<div class="tabs">
    <button class="tab-button active" data-tab="overview">Overview</button>
    <button class="tab-button" data-tab="audit">Migration Audit</button>
</div>

<section id="tab-overview" class="tab-content active">
    <div class="panel"><h2>Preflight</h2><p>API availability, permissions, disk space and API limits are checked before start.</p></div>
    <div class="panel"><h2>Audit</h2><p>Inventory of users, tasks, CRM deals, comments and files.</p></div>
    <div class="panel actions">
        <h2>Job Control</h2>
        <button>Start FULL_MIGRATION</button>
        <button>Start INCREMENTAL_SYNC</button>
        <button>Pause</button>
        <button>Resume</button>
        <button>Soft Stop</button>
    </div>
    <div class="panel">
        <h2>Logs</h2>
        <form class="filters" method="get">
            <label>Type
                <select name="status">
                    <option value="">Any</option>
                    <option value="INFO">INFO</option>
                    <option value="WARNING">WARNING</option>
                    <option value="ERROR">ERROR</option>
                </select>
            </label>
            <label>Entity
                <input type="text" name="entity_type" placeholder="users/tasks/crm_deals...">
            </label>
            <label>Date from
                <input type="date" name="date_from">
            </label>
            <label>Date to
                <input type="date" name="date_to">
            </label>
            <button type="submit">Apply filters</button>
        </form>
    </div>
    <div class="panel"><h2>Verification</h2><p>Integrity issues are saved to migration_integrity_issues.</p></div>
</section>

<section id="tab-audit" class="tab-content">
    <div class="panel">
        <h2>Migration Audit</h2>
        <p><b>Status:</b> <span id="audit-status">Not started</span></p>
        <p><b>Statistics:</b> <span id="audit-stats">No audit data yet</span></p>
        <div class="actions">
            <button id="run-audit-btn">Run Audit</button>
            <button id="export-report-btn">Export Report</button>
        </div>
    </div>
    <div class="panel">
        <h3>Found Problems</h3>
        <pre id="audit-problems">[]</pre>
    </div>
    <div class="panel">
        <h3>Portal Diff</h3>
        <pre id="audit-diff">No diff yet</pre>
    </div>
</section>

<script type="module">
import { MigrationAuditModule } from '/audit/index.js';

const tabs = document.querySelectorAll('.tab-button');
for (const tab of tabs) {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab-button').forEach((button) => button.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach((panel) => panel.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(`tab-${tab.dataset.tab}`).classList.add('active');
    });
}

const auditModule = new MigrationAuditModule({ batch_size: 50, delay_ms: 300 });
const runButton = document.getElementById('run-audit-btn');
const exportButton = document.getElementById('export-report-btn');
let latestResult = null;

const oldPortal = {
    users: [{ id: 1, email: 'owner@company.tld', active: true }],
    tasks: [{ id: 10, responsible_id: 1, created_by: 1, group_id: 7, status: 'new' }],
    comments: [{ id: 100, task_id: 10, author: 1 }],
    groups: [{ id: 7, owner_id: 1, member_ids: [1] }],
};

const newPortal = {
    users: [{ id: 1, email: 'owner@company.tld', active: true }],
    tasks: [{ id: 10, responsible_id: 1, created_by: 1, group_id: 7, status: 'new' }],
    comments: [{ id: 100, task_id: 10, author: 1 }],
    groups: [{ id: 7, owner_id: 1, member_ids: [1] }],
};

const fetchPaged = (dataset) => async (entity, { offset, limit }) => (dataset[entity] ?? []).slice(offset, offset + limit);

runButton.addEventListener('click', async () => {
    latestResult = await auditModule.runAudit({
        sourcePortalData: oldPortal,
        targetPortalData: newPortal,
        fetchOld: fetchPaged(oldPortal),
        fetchNew: fetchPaged(newPortal),
    });

    document.getElementById('audit-status').textContent = latestResult.status;
    document.getElementById('audit-stats').textContent = JSON.stringify(latestResult.report.migration_info.entity_counts);
    document.getElementById('audit-problems').textContent = JSON.stringify(latestResult.issues, null, 2);
    document.getElementById('audit-diff').textContent = latestResult.report.portal_diff.text_report;
});

exportButton.addEventListener('click', () => {
    if (!latestResult) {
        alert('Run Audit first');
        return;
    }

    const blob = new Blob([latestResult.exports.html], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'migration-audit-report.html';
    link.click();
    URL.revokeObjectURL(url);
});
</script>
</body>
</html>
