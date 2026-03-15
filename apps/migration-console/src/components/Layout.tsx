import { useEffect, useState } from 'react';
import { Link, Outlet, useLocation } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { fetchJson } from '../api/client';
import type { MetaDto } from '../types/contracts';
import { useConsoleStore } from '../store/useConsoleStore';

const nav = [
  ['/', 'Global Dashboard'],
  ['/jobs', 'Migration Jobs'],
  ['/graph', 'Dependency Graph'],
  ['/heatmap', 'Error Heatmap'],
  ['/mapping', 'CRM Mapping Studio'],
  ['/workers', 'Workers Monitor'],
  ['/logs', 'Logs Console'],
  ['/conflicts', 'Conflict Center'],
  ['/integrity', 'Integrity Center'],
  ['/diff', 'Diff Explorer'],
  ['/replay', 'Replay / Sync'],
  ['/health', 'System Health'],
  ['/cutover', 'Cutover Command Center'],
  ['/security', 'Security Hub'],
  ['/security/roles', 'Role Matrix'],
  ['/security/approvals', 'Approval Queue'],
  ['/security/audit', 'Audit Explorer'],
  ['/security/sessions', 'Session Security'],
  ['/security/policy', 'Policy Simulator'],
  ['/security/incidents', 'Incident Review'],
];

export function Layout() {
  const location = useLocation();
  const { selectedRole, setSelectedRole, setFeatureFlags } = useConsoleStore();
  const [tenantId, setTenantId] = useState('tenant-alpha');
  const [workspaceId, setWorkspaceId] = useState('ws-core');
  const { data } = useQuery({ queryKey: ['meta'], queryFn: () => fetchJson<MetaDto>(`/meta?tenantId=${tenantId}&workspaceId=${workspaceId}&role=${selectedRole}`) });

  useEffect(() => {
    if (data?.featureFlags) setFeatureFlags(data.featureFlags);
  }, [data, setFeatureFlags]);

  return (
    <div className="app">
      <aside className="sidebar">
        <h1>Migration Ops</h1>
        <label className="muted" htmlFor="tenantSelect">Tenant</label>
        <select id="tenantSelect" value={tenantId} onChange={(e) => setTenantId(e.target.value)}>
          {['tenant-alpha', 'tenant-bravo'].map((tenant) => <option key={tenant} value={tenant}>{tenant}</option>)}
        </select>
        <label className="muted" htmlFor="workspaceSelect">Workspace</label>
        <select id="workspaceSelect" value={workspaceId} onChange={(e) => setWorkspaceId(e.target.value)}>
          {['ws-core', 'ws-prod', 'ws-green'].map((workspace) => <option key={workspace} value={workspace}>{workspace}</option>)}
        </select>
        <label className="muted" htmlFor="roleSelect">Role</label>
        <select id="roleSelect" value={selectedRole} onChange={(e) => setSelectedRole(e.target.value)}>
          {(data?.roles ?? ['MigrationOperator']).map((role) => <option key={role} value={role}>{role}</option>)}
        </select>
        {nav.map(([to, label]) => (
          <Link key={to} className={location.pathname === to ? 'active' : ''} to={to}>
            {label}
          </Link>
        ))}
      </aside>
      <main className="content">
        <div className="security-context">Tenant: {tenantId} · Workspace: {workspaceId} · Role: {selectedRole}</div>
        <Outlet />
      </main>
    </div>
  );
}
