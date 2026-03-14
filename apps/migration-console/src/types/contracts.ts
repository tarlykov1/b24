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

export interface MetricPoint {
  name: string;
  value: number;
  trend?: number;
}
