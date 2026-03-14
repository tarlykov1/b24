import { useEffect, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { fetchJson, openStream } from '../api/client';
import { DataTable } from '../components/DataTable';
import { MetricCard } from '../components/MetricCard';
import type { JobDto, Paged } from '../types/contracts';

function useApi<T>(path: string) {
  return useQuery({ queryKey: [path], queryFn: () => fetchJson<T>(path), refetchInterval: 10000 });
}

export function DashboardPage() {
  const { data } = useApi<{ stats: Record<string, number>; latestEvents: Array<{ kind: string; severity: string }>; jobs: JobDto[] }>('/dashboard');
  const [streamStatus, setStreamStatus] = useState('connecting');
  useEffect(() => {
    const s = openStream('dashboard', () => setStreamStatus('live'));
    s.onerror = () => setStreamStatus('polling fallback');
    return () => s.close();
  }, []);

  const metrics = data?.stats ?? {};
  return (
    <section>
      <h2>Global Dashboard</h2>
      <p className="muted">Realtime transport: {streamStatus}</p>
      <div className="metric-grid">
        {Object.entries(metrics).map(([k, v]) => <MetricCard key={k} title={k} value={v} />)}
      </div>
      <h3>Latest events</h3>
      <DataTable columns={['kind', 'severity']} rows={(data?.latestEvents ?? []).map((e) => [e.kind, e.severity])} />
    </section>
  );
}

export function JobsPage() {
  const { data } = useApi<Paged<JobDto>>('/jobs?limit=20');
  return <section><h2>Migration Jobs</h2><DataTable columns={['job', 'status', 'mode', 'stage', 'progress']} rows={(data?.items ?? []).map((j) => [j.jobId, j.status, j.mode, j.stage, `${j.progress}%`])} /></section>;
}

export function GraphPage() {
  const { data } = useApi<{ nodes: Array<{ id: string; entityType: string; status: string }>; edges: Array<{ from: string; to: string; type: string }> }>('/graph');
  return <section><h2>Dependency Graph View</h2><p>Nodes: {data?.nodes.length ?? 0} | Edges: {data?.edges.length ?? 0}</p><DataTable columns={['Node', 'Type', 'Status']} rows={(data?.nodes ?? []).slice(0, 20).map((n) => [n.id, n.entityType, n.status])} /></section>;
}

export function HeatmapPage() {
  const { data } = useApi<{ cells: Array<{ x: string; y: string; count: number }> }>('/heatmap');
  const chartData = useMemo(() => (data?.cells ?? []).slice(0, 15).map((c) => ({ name: `${c.x}/${c.y}`, errors: c.count })), [data]);
  return <section><h2>Error Heatmap</h2><div className="chart"> <ResponsiveContainer width="100%" height={280}><BarChart data={chartData}><CartesianGrid strokeDasharray="3 3"/><XAxis dataKey="name"/><YAxis/><Tooltip/><Bar dataKey="errors" fill="#ff6b6b"/></BarChart></ResponsiveContainer></div></section>;
}

export function MappingPage() {
  const { data } = useApi<{ rules: Array<{ source: string; target: string; transform: string; warning: string | null; lossRisk: boolean }> }>('/mapping');
  return <section><h2>CRM Mapping Studio</h2><DataTable columns={['Source', 'Target', 'Transform', 'Warning', 'Loss risk']} rows={(data?.rules ?? []).map((r) => [r.source, r.target, r.transform, r.warning ?? '-', r.lossRisk ? 'yes' : 'no'])} /></section>;
}

export function WorkersPage() {
  const { data } = useApi<{ items: Array<{ workerId: string; status: string; throughput: number; queueDepth: number; latencyMs: number }> }>('/workers');
  const series = (data?.items ?? []).slice(0, 12).map((w) => ({ name: w.workerId, throughput: w.throughput, latency: w.latencyMs }));
  return <section><h2>Workers Stream Monitor</h2><div className="chart"><ResponsiveContainer width="100%" height={280}><LineChart data={series}><CartesianGrid strokeDasharray="3 3"/><XAxis dataKey="name"/><YAxis/><Tooltip/><Line type="monotone" dataKey="throughput" stroke="#7bd88f"/><Line type="monotone" dataKey="latency" stroke="#74c0fc"/></LineChart></ResponsiveContainer></div></section>;
}

export function LogsPage() {
  const { data } = useApi<Paged<{ timestamp: string; severity: string; module: string; message: string; correlationId: string }>>('/logs?limit=80');
  return <section><h2>Real-Time Logs Console</h2><DataTable columns={['ts', 'severity', 'module', 'message', 'correlation']} rows={(data?.items ?? []).map((l) => [l.timestamp, l.severity, l.module, l.message, l.correlationId])} /></section>;
}

export function ConflictsPage() {
  const { data } = useApi<Paged<{ conflictId: string; type: string; severity: string; status: string; suggestedResolution: string }>>('/conflicts?limit=50');
  return <section><h2>Conflict Resolution Center</h2><DataTable columns={['id', 'type', 'severity', 'status', 'suggested']} rows={(data?.items ?? []).map((c) => [c.conflictId, c.type, c.severity, c.status, c.suggestedResolution])} /></section>;
}

export function IntegrityPage() {
  const { data } = useApi<Paged<{ issueId: string; type: string; severity: string; status: string; repairable: boolean }>>('/integrity?limit=50');
  return <section><h2>Integrity Repair Center</h2><DataTable columns={['id', 'type', 'severity', 'status', 'repairable']} rows={(data?.items ?? []).map((i) => [i.issueId, i.type, i.severity, i.status, i.repairable ? 'yes' : 'no'])} /></section>;
}

export function DiffPage() {
  const { data } = useApi<{ items: Array<{ entityId: string; entityType: string; kind: string; mismatch: boolean }> }>('/diff');
  return <section><h2>Diff Explorer</h2><DataTable columns={['entity', 'type', 'kind', 'mismatch']} rows={(data?.items ?? []).map((d) => [d.entityId, d.entityType, d.kind, d.mismatch ? 'yes' : 'no'])} /></section>;
}

export function ReplayPage() {
  const { data } = useApi<{ mode: string; reviewed: number; willChange: number; alreadyMapped: number; skippedByCheckpoint: number; risks: string[] }>('/replay-preview?mode=resume');
  return <section><h2>Replay / Resume / Incremental Sync Center</h2><div className="metric-grid"><MetricCard title="mode" value={data?.mode ?? '-'} /><MetricCard title="reviewed" value={data?.reviewed ?? 0} /><MetricCard title="willChange" value={data?.willChange ?? 0} /><MetricCard title="alreadyMapped" value={data?.alreadyMapped ?? 0} /><MetricCard title="skippedByCheckpoint" value={data?.skippedByCheckpoint ?? 0} /></div><p>Risks: {(data?.risks ?? []).join(', ')}</p></section>;
}

export function HealthPage() {
  const { data } = useApi<{ throughputPerSec: number; eventRate: number; queueDepth: number; processingLagSec: number; retriesPerMin: number; adaptiveThrottlingState: string; safeMode: boolean }>('/system-health');
  return <section><h2>System Health / Throughput / Queue Pressure</h2><div className="metric-grid"><MetricCard title="throughput/s" value={data?.throughputPerSec ?? 0} /><MetricCard title="eventRate" value={data?.eventRate ?? 0} /><MetricCard title="queueDepth" value={data?.queueDepth ?? 0} /><MetricCard title="lagSec" value={data?.processingLagSec ?? 0} /><MetricCard title="retries/min" value={data?.retriesPerMin ?? 0} /><MetricCard title="throttling" value={data?.adaptiveThrottlingState ?? '-'} /><MetricCard title="safe mode" value={data?.safeMode ? 'enabled' : 'disabled'} /></div></section>;
}
