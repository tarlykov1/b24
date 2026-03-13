<?php

declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bitrix24 Migration Admin</title>
    <style>
        body { font-family: sans-serif; margin: 2rem; background: #f7f8fa; }
        .panel { border: 1px solid #d9dde4; background: #fff; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .actions button { margin-right: .5rem; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid #eee; padding: .35rem; font-size: .9rem; }
    </style>
</head>
<body>
<h1>Migration Control Panel</h1>
<div class="panel">
    <h2>Run Settings</h2>
    <label>Mode:
        <select>
            <option>initial import</option>
            <option>sync / incremental</option>
        </select>
    </label>
    <label style="margin-left:1rem;">Inactive users cutoff date: <input type="date"></label>
    <label style="margin-left:1rem;">Tasks policy:
        <select>
            <option>Delete tasks</option>
            <option>Reassign to system account</option>
            <option>Keep and migrate user</option>
        </select>
    </label>
</div>
<div class="panel actions">
    <h2>Job Control</h2>
    <button>Start</button>
    <button>Pause</button>
    <button>Resume</button>
    <button>Stop / Cancel</button>
    <p>Status: queued | running | paused | stopping | cancelled | completed | failed</p>
</div>
<div class="grid">
    <div class="panel">
        <h2>Progress</h2>
        <ul>
            <li>Total: 0</li>
            <li>Processed: 0</li>
            <li>Successful: 0</li>
            <li>Skipped: 0</li>
            <li>Failed: 0</li>
        </ul>
    </div>
    <div class="panel">
        <h2>Preview diff (sync)</h2>
        <table>
            <tr><th>Category</th><th>Count</th></tr>
            <tr><td>Will create</td><td>0</td></tr>
            <tr><td>Will update</td><td>0</td></tr>
            <tr><td>Skipped</td><td>0</td></tr>
            <tr><td>Conflict</td><td>0</td></tr>
        </table>
        <button>Continue sync</button>
        <button>Cancel</button>
    </div>
</div>
<div class="panel">
    <h2>Event journal</h2>
    <table>
        <tr><th>Timestamp</th><th>Level</th><th>Entity type</th><th>Entity ID</th><th>Message</th></tr>
        <tr><td colspan="5">No events yet</td></tr>
    </table>
</div>
</body>
</html>
