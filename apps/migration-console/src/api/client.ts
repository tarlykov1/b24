const API_BASE = import.meta.env.VITE_MIGRATION_API_BASE ?? '/apps/migration-module/ui/admin/api.php';

export async function fetchJson<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${API_BASE}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(init?.headers ?? {}),
    },
  });
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`);
  }
  return res.json() as Promise<T>;
}

export function openStream(topic: 'logs' | 'workers' | 'dashboard', onMessage: (payload: unknown) => void): EventSource {
  const stream = new EventSource(`${API_BASE}/stream?topic=${topic}`);
  stream.addEventListener(topic, (event) => {
    const e = event as MessageEvent;
    onMessage(JSON.parse(e.data));
  });
  return stream;
}
