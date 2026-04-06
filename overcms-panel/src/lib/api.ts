import { boot } from './types';

export class ApiError extends Error {
  constructor(public status: number, message: string, public payload?: unknown) {
    super(message);
  }
}

interface RequestOptions extends Omit<RequestInit, 'body'> {
  query?: Record<string, string | number | boolean | undefined | null>;
  body?: unknown;
  raw?: boolean;
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
  // Akceptuje pełne ścieżki "/wp/v2/pages" i "overcms/v1/dashboard"
  const root = boot.restRoot.replace(/\/$/, '');
  const clean = path.startsWith('/') ? path : '/' + path;
  const url = new URL(root + clean, window.location.origin);
  if (query) {
    for (const [k, v] of Object.entries(query)) {
      if (v === undefined || v === null || v === '') continue;
      url.searchParams.set(k, String(v));
    }
  }
  return url.toString();
}

export async function api<T = unknown>(path: string, opts: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'X-WP-Nonce': boot.restNonce,
    ...((opts.headers as Record<string, string>) ?? {}),
  };

  let body: BodyInit | undefined;
  if (opts.body !== undefined) {
    if (opts.body instanceof FormData) {
      body = opts.body;
    } else {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(opts.body);
    }
  }

  const res = await fetch(buildUrl(path, opts.query), {
    ...opts,
    headers,
    body,
    credentials: 'same-origin',
  });

  if (!res.ok) {
    let payload: unknown;
    try {
      payload = await res.json();
    } catch {
      payload = await res.text();
    }
    const message =
      (typeof payload === 'object' && payload && 'message' in payload && String((payload as { message: unknown }).message)) ||
      `HTTP ${res.status}`;
    throw new ApiError(res.status, message, payload);
  }

  if (opts.raw) {
    return res as unknown as T;
  }

  if (res.status === 204) {
    return undefined as T;
  }
  return (await res.json()) as T;
}
