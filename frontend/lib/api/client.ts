export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    public readonly details?: Record<string, unknown>,
  ) {
    super(`API error ${status}: ${code}`);
    this.name = 'ApiError';
  }
}

export interface RequestOptions {
  method?: 'GET' | 'POST' | 'PATCH' | 'DELETE';
  body?: unknown;
  token?: string | null;
  signal?: AbortSignal;
}

const baseUrl = (): string =>
  (process.env['NEXT_PUBLIC_API_BASE'] ?? 'http://localhost:8080/api').replace(/\/$/, '');

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, token, signal } = options;

  const headers: Record<string, string> = {
    Accept: 'application/json',
  };
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const init: RequestInit = { method, headers };
  if (body !== undefined) {
    init.body = JSON.stringify(body);
  }
  if (signal) {
    init.signal = signal;
  }

  const response = await fetch(`${baseUrl()}${path}`, init);

  const text = await response.text();
  const parsed: unknown = text ? JSON.parse(text) : null;

  if (!response.ok) {
    const err = (parsed && typeof parsed === 'object' ? parsed : {}) as {
      error?: string;
      details?: Record<string, unknown>;
    };
    throw new ApiError(response.status, err.error ?? 'unknown_error', err.details);
  }

  return parsed as T;
}
