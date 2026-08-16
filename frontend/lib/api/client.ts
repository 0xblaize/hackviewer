function backendUrl(path: string): string {
  if (typeof window !== 'undefined') return `/api/backend${path}`;
  const origin = process.env.NEXT_PUBLIC_APP_URL || 'http://127.0.0.1:3013';
  return new URL(`/api/backend${path}`, origin).toString();
}

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(backendUrl(path), { ...init, cache: 'no-store' });
  const body = await response.json().catch(() => null);
  if (!response.ok) {
    throw new Error(body?.error?.message || `Request failed with HTTP ${response.status}`);
  }
  if (!body || typeof body !== 'object' || !('data' in body)) {
    throw new Error('The backend returned an invalid API response.');
  }
  return body.data as T;
}
