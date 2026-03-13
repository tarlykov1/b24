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
    </style>
</head>
<body>
<h1>Migration Control Panel</h1>
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
</body>
</html>
