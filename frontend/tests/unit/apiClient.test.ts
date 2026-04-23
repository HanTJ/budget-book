import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('apiRequest baseUrl resolution', () => {
  const originalEnv = process.env['NEXT_PUBLIC_API_BASE'];

  beforeEach(() => {
    vi.resetModules();
  });

  afterEach(() => {
    if (originalEnv === undefined) {
      delete process.env['NEXT_PUBLIC_API_BASE'];
    } else {
      process.env['NEXT_PUBLIC_API_BASE'] = originalEnv;
    }
    vi.restoreAllMocks();
  });

  async function loadAndCall(path: string): Promise<string> {
    const mod = await import('@/lib/api/client');
    const mockFetch = vi.fn().mockResolvedValue(new Response('{"ok":true}', { status: 200 }));
    vi.stubGlobal('fetch', mockFetch);
    await mod.apiRequest(path);
    const call = mockFetch.mock.calls[0]?.[0];
    return String(call);
  }

  it('defaults to same-origin /api prefix when env var missing', async () => {
    delete process.env['NEXT_PUBLIC_API_BASE'];

    const url = await loadAndCall('/health');

    expect(url).toBe('/api/health');
  });

  it('respects absolute NEXT_PUBLIC_API_BASE', async () => {
    process.env['NEXT_PUBLIC_API_BASE'] = 'http://localhost:8080/api';

    const url = await loadAndCall('/health');

    expect(url).toBe('http://localhost:8080/api/health');
  });

  it('strips trailing slash from NEXT_PUBLIC_API_BASE', async () => {
    process.env['NEXT_PUBLIC_API_BASE'] = '/api/';

    const url = await loadAndCall('/health');

    expect(url).toBe('/api/health');
  });
});
