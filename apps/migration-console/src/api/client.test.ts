import { describe, expect, it } from 'vitest';

describe('api client exports', () => {
  it('exports JSON and action helpers', async () => {
    const mod = await import('./client');
    expect(typeof mod.fetchJson).toBe('function');
    expect(typeof mod.postAction).toBe('function');
    expect(typeof mod.openStream).toBe('function');
  });
});
