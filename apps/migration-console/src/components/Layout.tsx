import { useEffect } from 'react';
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
];

export function Layout() {
  const location = useLocation();
  const { selectedRole, setSelectedRole, setFeatureFlags } = useConsoleStore();
  const { data } = useQuery({ queryKey: ['meta'], queryFn: () => fetchJson<MetaDto>('/meta') });

  useEffect(() => {
    if (data?.featureFlags) setFeatureFlags(data.featureFlags);
  }, [data, setFeatureFlags]);

  return (
    <div className="app">
      <aside className="sidebar">
        <h1>Migration Ops</h1>
        <label className="muted" htmlFor="roleSelect">Role</label>
        <select id="roleSelect" value={selectedRole} onChange={(e) => setSelectedRole(e.target.value)}>
          {(data?.roles ?? ['operator']).map((role) => <option key={role} value={role}>{role}</option>)}
        </select>
        {nav.map(([to, label]) => (
          <Link key={to} className={location.pathname === to ? 'active' : ''} to={to}>
            {label}
          </Link>
        ))}
      </aside>
      <main className="content">
        <Outlet />
      </main>
    </div>
  );
}
