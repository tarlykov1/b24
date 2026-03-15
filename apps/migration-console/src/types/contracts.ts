export type JobStatus = 'running' | 'paused' | 'failed' | 'completed' | 'resume' | 'syncing';

export interface JobDto {
  jobId: string;
  status: JobStatus | string;
  mode: string;
  source: string;
  target: string;
  startedAt: string;
  durationSec: number;
  stage: string;
  progress: number;
  processed: number;
  pending: number;
  failed: number;
  skipped: number;
}

export interface Paged<T> {
  items: T[];
  total: number;
  limit: number;
  offset: number;
}

export interface DashboardDto {
  stats: Record<string, number>;
  latestEvents: Array<{ timestamp: string; kind: string; severity: string; message: string }>;
  jobs: JobDto[];
  timeRange: string;
  featureFlags?: Record<string, boolean>;
  roles?: string[];
}

export interface MetaDto {
  featureFlags: Record<string, boolean>;
  roles: string[];
  defaultRole: string;
  realtime: { transport: string; fallback: string };
}

export interface JobDetailsDto {
  jobId: string;
  overview: { status: string; mode: string; progress: number; currentStage: string; throughput: number };
  timeline: Array<{ step: string; status: string }>;
  entities: { processed: number; pending: number; failed: number };
  queues: Record<string, number>;
  syncStatus: { mode: string; lagSec: number; checkpoint: string };
}

export interface CutoverCommandCenterDto {
  jobId: string;
  phase: string;
  stateMachineState: string;
  etaMin: number;
  readiness: {
    readinessScore: number;
    recommendation: string;
    hardBlockers: string[];
    softBlockers: string[];
    warnings: string[];
  };
  approvals: Array<{ role: string; status: string }>;
  freeze: { status: string; mode: string; exceptions: number };
  deltaSync: { status: string; progress: number; etaMin: number };
  smoke: { status: string; criticalPassRate: number };
  rollbackPanel: { possible: boolean; risk: string; strategy: string };
  runbookTracker: Array<{ minute: string; step: string; status: string }>;
}
