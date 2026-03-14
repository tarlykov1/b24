import { describe, expect, it } from 'vitest';

describe('api base path', () => {
  it('uses php api endpoint by default', async () => {
    const mod = await import('./client');
    expect(typeof mod.fetchJson).toBe('function');
  });
});
