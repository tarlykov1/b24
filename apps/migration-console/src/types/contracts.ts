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
