import { apiFetch } from './client';
import type { HackathonDetailResponse, HackathonResponse } from '../types';

export type HackathonFilters = {
  q?: string;
  status?: string;
  type?: string;
  source?: string;
  horizon?: string;
  sort?: string;
};

export async function getHackathons(filters: HackathonFilters = {}) {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value) params.set(key, value);
  }
  return apiFetch<HackathonResponse>(`/hackathons${params.size ? `?${params}` : ''}`);
}

export function getHackathon(id: string) {
  return apiFetch<HackathonDetailResponse>(`/hackathons/${encodeURIComponent(id)}`);
}
