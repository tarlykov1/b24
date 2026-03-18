<?php

declare(strict_types=1);

use MigrationModule\Support\DbConfig;

$dbConfig = DbConfig::fromRuntimeSources([], dirname(__DIR__, 4));
if ((string) ($dbConfig['name'] ?? '') === '' || (string) ($dbConfig['user'] ?? '') === '') {
    header('Location: install.php');
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Bitrix24 Migration Control Plane</title>
  <style>
    body{font-family:Inter,Arial,sans-serif;max-width:1200px;margin:24px auto;padding:0 12px;background:#fafafa;color:#1f2937}
    .grid{display:grid;grid-template-columns:1.2fr 1fr;gap:16px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:14px}
    .row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    button,select,input{padding:8px 10px;border-radius:8px;border:1px solid #d1d5db;background:#fff}
    button{background:#2563eb;color:#fff;border-color:#2563eb;cursor:pointer}
    button.secondary{background:#fff;color:#1f2937;border-color:#d1d5db}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #f3f4f6;padding:8px;text-align:left;font-size:13px;vertical-align:top}
    .badge{padding:2px 8px;border-radius:999px;font-size:12px;background:#e5e7eb;display:inline-block}
    .running{background:#dcfce7}.paused{background:#fef3c7}.failed{background:#fee2e2}.completed{background:#dbeafe}.verified{background:#cffafe}
    .bar{height:10px;background:#e5e7eb;border-radius:99px;overflow:hidden}
    .bar > span{display:block;height:100%;background:#22c55e}
    pre{background:#0b1220;color:#d1e7ff;padding:10px;border-radius:8px;max-height:240px;overflow:auto;font-size:12px}
    ul{margin:0;padding-left:16px}
  </style>
</head>
<body>
<h1>Bitrix24 Migration Web Control Plane</h1>
<p>Управление install/config/jobs/lifecycle без SSH. CLI остаётся бэкенд-интерфейсом, но не основным UX.</p>
<section class="card" style="margin-bottom:16px">
  <h2>Admin session</h2>
  <div class="row">
    <input id="adminPassword" type="password" placeholder="Admin password">
    <button onclick="login()">Login</button>
    <button class="secondary" onclick="logout()">Logout</button>
    <span id="authState" class="badge">not authenticated</span>
  </div>
</section>

<div class="grid">
  <section class="card">
    <h2>Jobs</h2>
    <div class="row">
      <select id="createMode">
        <option value="validate">validate</option>
        <option value="dry-run">dry-run</option>
        <option value="execute" selected>execute</option>
        <option value="verify">verify</option>
      </select>
      <button onclick="createJob()">Create Job</button>
      <button class="secondary" onclick="refreshJobs()">Refresh</button>
      <span id="pollInfo" class="badge">polling: 5s</span>
    </div>
    <table>
      <thead><tr><th>Job</th><th>Status</th><th>Mode</th><th>Progress</th><th>Updated</th></tr></thead>
      <tbody id="jobsBody"><tr><td colspan="5">Loading...</td></tr></tbody>
    </table>
  </section>

  <section class="card">
    <h2>Job Details</h2>
    <div id="details">Выберите job из списка.</div>
    <hr>
    <div class="row">
      <button onclick="lifecycle('validate')">Validate</button>
      <button onclick="lifecycle('dry-run')">Dry-run</button>
      <button onclick="lifecycle('execute')">Execute</button>
      <button onclick="lifecycle('pause')">Pause</button>
      <button onclick="lifecycle('resume')">Resume</button>
      <button onclick="lifecycle('verify')">Verify</button>
    </div>
  </section>
</div>

<section class="card" style="margin-top:16px">
  <h2>Structured Logs (recent)</h2>
  <pre id="logs">Select job.</pre>
</section>

<section class="card" style="margin-top:16px">
  <h2>Reports</h2>
  <table>
    <thead><tr><th>Report ID</th><th>Status</th><th>Created</th><th>Summary</th><th>Download</th></tr></thead>
    <tbody id="reportsBody"><tr><td colspan="5">No report selected.</td></tr></tbody>
  </table>
</section>

<script>
let selectedJobId = null;
let csrfToken = null;

async function api(path, options = {}) {
  const headers = {'Content-Type': 'application/json', ...(options.headers || {})};
  if ((options.method || 'GET').toUpperCase() !== 'GET' && csrfToken) headers['X-CSRF-Token'] = csrfToken;
  const res = await fetch('api.php' + path, {
    ...options,
    credentials: 'same-origin',
    headers
  });
  const text = await res.text();
  try {
    return JSON.parse(text);
  } catch (_) {
    return {ok: false, error: 'invalid_json', raw: text};
  }
}

function statusBadge(status) {
  return `<span class="badge ${status || ''}">${status || 'unknown'}</span>`;
}

function progressBar(value) {
  const val = Math.max(0, Math.min(100, Number(value || 0)));
  return `<div class="bar"><span style="width:${val}%"></span></div><small>${val}%</small>`;
}

async function refreshJobs() {
  const data = await api('/runtime/jobs');
  const rows = (data.items || []).map((job) => `
    <tr onclick="selectJob('${job.jobId}')" style="cursor:pointer">
      <td>${job.jobId}</td>
      <td>${statusBadge(job.status)}</td>
      <td>${job.mode}</td>
      <td>${progressBar(job.progress)}</td>
      <td>${job.updatedAt}</td>
    </tr>`).join('');
  document.getElementById('jobsBody').innerHTML = rows || '<tr><td colspan="5">No jobs yet.</td></tr>';

  if (!selectedJobId && data.items && data.items[0]) {
    await selectJob(data.items[0].jobId);
  }
}

async function selectJob(jobId) {
  selectedJobId = jobId;
  const [details, logs, reports] = await Promise.all([
    api(`/runtime/jobs/${jobId}`),
    api(`/runtime/jobs/${jobId}/logs?limit=20`),
    api(`/runtime/jobs/${jobId}/reports`)
  ]);

  document.getElementById('details').innerHTML = `
    <p><strong>Job:</strong> ${details.jobId}</p>
    <p><strong>Status:</strong> ${statusBadge(details.status)} <strong>Mode:</strong> ${details.mode}</p>
    <p><strong>Current step:</strong> ${details.currentStep || 'n/a'}</p>
    <p><strong>Progress:</strong> ${progressBar(details.progress)}</p>
    <p><strong>Warnings:</strong> ${details.warnings} <strong>Errors:</strong> ${details.errors}</p>
    <p><strong>Queue:</strong> done=${details.queue?.done || 0}, pending=${details.queue?.pending || 0}, retry=${details.queue?.retry || 0}, failed=${details.queue?.failed || 0}</p>
    <h4>Step timeline</h4>
    <ul>${(details.timeline || []).map((x) => `<li>${x.timestamp} — ${x.step} (${x.status})</li>`).join('') || '<li>No steps yet</li>'}</ul>
  `;

  document.getElementById('logs').textContent = (logs.items || []).map((x) =>
    `[${x.timestamp}] ${x.severity.toUpperCase()} ${x.message}` + (x.context ? ` | ${JSON.stringify(x.context)}` : '')
  ).join('\n') || 'No logs.';

  document.getElementById('reportsBody').innerHTML = (reports.items || []).map((r) => `
    <tr>
      <td>${r.reportId}</td>
      <td>${r.status}</td>
      <td>${r.createdAt}</td>
      <td>${r.summary || '-'}</td>
      <td><a href="api.php/runtime/jobs/${jobId}/reports/${r.reportId}/download">download json</a></td>
    </tr>
  `).join('') || '<tr><td colspan="5">No reports.</td></tr>';
}



async function login() {
  const password = document.getElementById('adminPassword').value;
  const data = await api('/auth/login', {method:'POST', body: JSON.stringify({password})});
  if (data.ok) {
    csrfToken = data.csrf;
    document.getElementById('authState').textContent = 'authenticated';
    await refreshJobs();
  } else {
    document.getElementById('authState').textContent = 'login failed';
  }
}

async function logout() {
  await api('/auth/logout', {method:'POST', body: JSON.stringify({})});
  csrfToken = null;
  document.getElementById('authState').textContent = 'not authenticated';
}

async function createJob() {
  const mode = document.getElementById('createMode').value;
  const data = await api('/runtime/jobs/create', {method:'POST', body: JSON.stringify({mode})});
  if (data.jobId) {
    await refreshJobs();
    await selectJob(data.jobId);
  }
}

async function lifecycle(action) {
  if (!selectedJobId) return;
  await api(`/runtime/jobs/${selectedJobId}/action`, {method:'POST', body: JSON.stringify({action})});
  await refreshJobs();
  await selectJob(selectedJobId);
}

setInterval(() => { refreshJobs(); if (selectedJobId) selectJob(selectedJobId); }, 5000);
refreshJobs();
</script>
</body>
</html>
