import { Link, Outlet, useLocation } from 'react-router-dom';

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
  return (
    <div className="app">
      <aside className="sidebar">
        <h1>Migration Ops</h1>
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
