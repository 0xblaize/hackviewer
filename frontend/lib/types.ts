export type Hackathon = {
  id: number;
  title: string;
  official_url: string;
  source_name: string | null;
  platform_name: string | null;
  organizer_name: string | null;
  description: string | null;
  hackathon_type: string | null;
  start_at_utc: string | null;
  end_at_utc: string | null;
  registration_deadline_utc: string | null;
  prize_text: string | null;
  prize_amount_minor: number | null;
  prize_currency: string | null;
  participant_count: number | null;
  online_or_location: string | null;
  location_text: string | null;
  status: string;
  verification_status: string;
  legitimacy_notes: string | null;
  what_to_know: string | null;
};

export type HackathonLink = {
  kind: string;
  url: string;
  label: string;
  is_primary: number;
};

export type VerificationCheck = {
  check_type: string;
  result: string;
  evidence_url: string | null;
  evidence_excerpt: string | null;
  checked_at: string;
};

export type Candidate = {
  id: number;
  source_name: string | null;
  external_key: string;
  post_url: string;
  author_handle: string | null;
  text: string;
  posted_at: string | null;
  retrieved_at: string | null;
  payload_path: string | null;
  status: string;
};

export type DiscoveryLead = {
  id: number;
  source_id: number;
  external_key: string;
  post_url: string;
  author_handle: string | null;
  text: string;
  posted_at: string | null;
  status: string;
  source_name: string | null;
};

export type HackathonResponse = {
  items: Hackathon[];
  summary: { verified: number; ending: number; sources: number; pending_candidates: number };
  options: { types: string[]; sources: string[] };
  leads: DiscoveryLead[];
};

export type HackathonDetailResponse = {
  hackathon: Hackathon;
  links: HackathonLink[];
  checks: VerificationCheck[];
};

export type CandidateResponse = { items: Candidate[] };
export type CandidateDetailResponse = { candidate: Candidate };
