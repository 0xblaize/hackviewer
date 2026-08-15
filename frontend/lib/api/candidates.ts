import { apiFetch } from './client';
import type { Candidate, CandidateDetailResponse, CandidateResponse } from '../types';

export function getCandidates() {
  return apiFetch<CandidateResponse>('/candidates');
}

export function getCandidate(id: string) {
  return apiFetch<CandidateDetailResponse>(`/candidates/${encodeURIComponent(id)}`);
}

export function rejectCandidate(id: number, reviewNote: string) {
  return apiFetch<{ status: string }>(`/candidates/${id}/reject`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ review_note: reviewNote }),
  });
}

export type CandidateConversion = {
  title: string;
  official_url: string;
  organizer_name?: string;
  platform_name?: string;
  description?: string;
  hackathon_type?: string;
  start_at_utc?: string;
  end_at_utc?: string;
  registration_deadline_utc?: string;
  prize_text?: string;
  location_text?: string;
  what_to_know?: string;
  review_note?: string;
};

export function convertCandidate(id: number, input: CandidateConversion) {
  return apiFetch<{ status: string; hackathon_id: number }>(`/candidates/${id}/convert`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify(input),
  });
}

export type { Candidate };
