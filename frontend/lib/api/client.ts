const backendBase = '/api/backend';

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${backendBase}${path}`, { ...init, cache: 'no-store' });
  const body = await response.json().catch(() => null);
  if (!response.ok) {
    throw new Error(body?.error?.message || `Request failed with HTTP ${response.status}`);
  }
  return body.data as T;
}
