import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Bar, BarChart, CartesianGrid, Cell, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { fetchJson, openStream, postAction } from '../api/client';
import { DataTable } from '../components/DataTable';
import { MetricCard } from '../components/MetricCard';
import { useConsoleStore } from '../store/useConsoleStore';
import type { DashboardDto, JobDetailsDto, JobDto, Paged } from '../types/contracts';

function useApi<T>(path: string) {
  return useQuery({ queryKey: [path], queryFn: () => fetchJson<T>(path), refetchInterval: 7000 });
}

function StreamBadge({ topic }: { topic: 'logs' | 'workers' | 'dashboard' }) {
  const [state, setState] = useState('connecting');
  const { setFallbackMode } = useConsoleStore();
  useEffect(() => {
    const s = openStream(topic, () => {
      setState('live');
      setFallbackMode(false);
    });
    s.onerror = () => {
      setState('polling fallback');
      setFallbackMode(true);
    };
    return () => s.close();
  }, [topic, setFallbackMode]);
  return <span className="badge">{topic}: {state}</span>;
}

export function DashboardPage() {
  const { data } = useApi<DashboardDto>('/dashboard');
  const metrics = data?.stats ?? {};

  return (
    <section>
      <h2>Global Dashboard</h2>
      <div className="badge-row"><StreamBadge topic="dashboard" /></div>
      <div className="metric-grid">
        {Object.entries(metrics).map(([k, v]) => <MetricCard key={k} title={k} value={v} />)}
      </div>
      <h3>Latest events</h3>
      <DataTable columns={['ts', 'kind', 'severity', 'message']} rows={(data?.latestEvents ?? []).map((e) => [e.timestamp, e.kind, e.severity, e.message])} />
    </section>
  );
}

export function JobsPage() {
  const { selectedJobId, setSelectedJobId } = useConsoleStore();
  const { data } = useApi<Paged<JobDto>>('/jobs?limit=20');
  const details = useApi<JobDetailsDto>(`/jobs/details?jobId=${selectedJobId ?? 'latest'}`);
  const action = useMutation({ mutationFn: (payload: { jobId: string; action: string }) => postAction('/jobs/action', payload) });

  return <section>
    <h2>Migration Jobs</h2>
    <div className="action-row">
      {['start', 'pause', 'resume', 'cancel', 'retry', 'verify'].map((a) => (
        <button key={a} onClick={() => action.mutate({ jobId: selectedJobId ?? 'latest', action: a })}>{a}</button>
      ))}
    </div>
    <DataTable columns={['job', 'status', 'mode', 'stage', 'progress']} rows={(data?.items ?? []).map((j) => [
      <button key={j.jobId} className="link-like" onClick={() => setSelectedJobId(j.jobId)}>{j.jobId}</button>,
      j.status,
      j.mode,
      j.stage,
      `${j.progress}%`,
    ])} />
    <h3>Job card: {details.data?.jobId ?? '-'}</h3>
    <div className="metric-grid">
      <MetricCard title="status" value={details.data?.overview.status ?? '-'} />
      <MetricCard title="mode" value={details.data?.overview.mode ?? '-'} />
      <MetricCard title="progress" value={`${details.data?.overview.progress ?? 0}%`} />
      <MetricCard title="stage" value={details.data?.overview.currentStage ?? '-'} />
      <MetricCard title="throughput" value={details.data?.overview.throughput ?? 0} />
    </div>
    <DataTable columns={['timeline step', 'status']} rows={(details.data?.timeline ?? []).map((t) => [t.step, t.status])} />
  </section>;
}

export function GraphPage() {
  const { data } = useApi<{ nodes: Array<{ id: string; entityType: string; status: string; blockedReason?: string | null; criticalChain: boolean }>; edges: Array<{ from: string; to: string; type: string }> }>('/graph');
  return <section><h2>Dependency Graph View</h2><p>Nodes: {data?.nodes.length ?? 0} | Edges: {data?.edges.length ?? 0}</p><DataTable columns={['Node', 'Type', 'Status', 'Blocked reason', 'Critical chain']} rows={(data?.nodes ?? []).slice(0, 20).map((n) => [n.id, n.entityType, n.status, n.blockedReason ?? '-', n.criticalChain ? 'yes' : 'no'])} /></section>;
}

export function HeatmapPage() {
  const { data } = useApi<{ cells: Array<{ x: string; y: string; count: number; critical: boolean }> }>('/heatmap');
  const chartData = useMemo(() => (data?.cells ?? []).slice(0, 15).map((c) => ({ name: `${c.x}/${c.y}`, errors: c.count, critical: c.critical })), [data]);
  return <section><h2>Error Heatmap</h2><div className="chart"> <ResponsiveContainer width="100%" height={280}><BarChart data={chartData}><CartesianGrid strokeDasharray="3 3"/><XAxis dataKey="name"/><YAxis/><Tooltip/><Bar dataKey="errors">{chartData.map((entry, idx) => <Cell key={`cell-${idx}`} fill={entry.critical ? '#ff4d6d' : '#f08c00'} />)}</Bar></BarChart></ResponsiveContainer></div></section>;
}

export function MappingPage() {
  const { data } = useApi<{ requiredFields: string[]; rules: Array<{ source: string; target: string; transform: string; warning: string | null; lossRisk: boolean; required: boolean }>; versions: Array<{ version: string; author: string; createdAt: string }> }>('/mapping');
  return <section><h2>CRM Mapping Studio</h2><p>Required fields: {(data?.requiredFields ?? []).join(', ')}</p><DataTable columns={['Source', 'Target', 'Transform', 'Required', 'Warning', 'Loss risk']} rows={(data?.rules ?? []).map((r) => [r.source, r.target, r.transform, r.required ? 'yes' : 'no', r.warning ?? '-', r.lossRisk ? 'yes' : 'no'])} /><h3>Mapping versions</h3><DataTable columns={['version', 'author', 'created']} rows={(data?.versions ?? []).map((v) => [v.version, v.author, v.createdAt])} /></section>;
}

export function WorkersPage() {
  const { data } = useApi<{ items: Array<{ workerId: string; status: string; throughput: number; queueDepth: number; latencyMs: number; backpressure: number }> }>('/workers');
  const action = useMutation({ mutationFn: (payload: { jobId: string; action: string; workerId?: string }) => postAction('/workers/action', payload) });
  const series = (data?.items ?? []).slice(0, 12).map((w) => ({ name: w.workerId, throughput: w.throughput, latency: w.latencyMs }));
  return <section><h2>Workers Stream Monitor</h2><div className="badge-row"><StreamBadge topic="workers" /></div><div className="action-row">{['pause_worker', 'restart_worker', 'quarantine_queue', 'rebalance_load', 'safe_retry'].map((a) => <button key={a} onClick={() => action.mutate({ jobId: 'latest', action: a })}>{a}</button>)}</div><div className="chart"><ResponsiveContainer width="100%" height={280}><LineChart data={series}><CartesianGrid strokeDasharray="3 3"/><XAxis dataKey="name"/><YAxis/><Tooltip/><Line type="monotone" dataKey="throughput" stroke="#7bd88f"/><Line type="monotone" dataKey="latency" stroke="#74c0fc"/></LineChart></ResponsiveContainer></div><DataTable columns={['worker', 'status', 'queueDepth', 'backpressure']} rows={(data?.items ?? []).map((w) => [w.workerId, w.status, w.queueDepth, w.backpressure])} /></section>;
}

export function LogsPage() {
  const { data } = useApi<Paged<{ timestamp: string; severity: string; module: string; message: string; correlationId: string; traceId: string; entityId: string }>>('/logs?limit=80');
  return <section><h2>Real-Time Logs Console</h2><div className="badge-row"><StreamBadge topic="logs" /></div><DataTable columns={['ts', 'severity', 'module', 'message', 'correlation', 'trace', 'entity']} rows={(data?.items ?? []).map((l) => [l.timestamp, l.severity, l.module, l.message, l.correlationId, l.traceId, l.entityId])} /></section>;
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
  const { data } = useApi<{ items: Array<{ entityId: string; entityType: string; kind: string; mismatch: boolean; source: Record<string, string | number>; target: Record<string, string | number> }> }>('/diff');
  return <section><h2>Diff Explorer</h2><DataTable columns={['entity', 'type', 'kind', 'mismatch', 'source', 'target']} rows={(data?.items ?? []).map((d) => [d.entityId, d.entityType, d.kind, d.mismatch ? 'yes' : 'no', JSON.stringify(d.source), JSON.stringify(d.target)])} /></section>;
}

export function ReplayPage() {
  const { data } = useApi<{ mode: string; reviewed: number; willChange: number; alreadyMapped: number; skippedByCheckpoint: number; risks: string[] }>('/replay-preview?mode=resume');
  return <section><h2>Replay / Resume / Incremental Sync Center</h2><div className="metric-grid"><MetricCard title="mode" value={data?.mode ?? '-'} /><MetricCard title="reviewed" value={data?.reviewed ?? 0} /><MetricCard title="willChange" value={data?.willChange ?? 0} /><MetricCard title="alreadyMapped" value={data?.alreadyMapped ?? 0} /><MetricCard title="skippedByCheckpoint" value={data?.skippedByCheckpoint ?? 0} /></div><p>Risks: {(data?.risks ?? []).join(', ')}</p></section>;
}

export function HealthPage() {
  const { data } = useApi<{ throughputPerSec: number; eventRate: number; queueDepth: number; processingLagSec: number; retriesPerMin: number; adaptiveThrottlingState: string; safeMode: boolean; legacyApiPressure: { rpmLimit: number; currentRpm: number; backoffMs: number; protectedSyncWindow: string } }>('/system-health');
  return <section><h2>System Health / Throughput / Queue Pressure</h2><div className="metric-grid"><MetricCard title="throughput/s" value={data?.throughputPerSec ?? 0} /><MetricCard title="eventRate" value={data?.eventRate ?? 0} /><MetricCard title="queueDepth" value={data?.queueDepth ?? 0} /><MetricCard title="lagSec" value={data?.processingLagSec ?? 0} /><MetricCard title="retries/min" value={data?.retriesPerMin ?? 0} /><MetricCard title="throttling" value={data?.adaptiveThrottlingState ?? '-'} /><MetricCard title="safe mode" value={data?.safeMode ? 'enabled' : 'disabled'} /></div><h3>Legacy API pressure protection</h3><DataTable columns={['rpmLimit', 'currentRpm', 'backoffMs', 'protectedSyncWindow']} rows={data ? [[data.legacyApiPressure.rpmLimit, data.legacyApiPressure.currentRpm, data.legacyApiPressure.backoffMs, data.legacyApiPressure.protectedSyncWindow]] : []} /></section>;
}
