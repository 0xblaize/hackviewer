'use client';

import { FormEvent, useState } from 'react';
import { convertCandidate, rejectCandidate, type CandidateConversion } from '../lib/api/candidates';
import type { Candidate } from '../lib/types';

export default function CandidateActions({ candidate }: { candidate: Candidate }) {
  const [mode, setMode] = useState<'idle' | 'convert' | 'reject'>('idle');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setMessage('');
    const form = new FormData(event.currentTarget);
    try {
      if (mode === 'reject') {
        await rejectCandidate(candidate.id, String(form.get('review_note') || ''));
        setMessage('Candidate rejected.');
      } else {
        const input: CandidateConversion = {
          title: String(form.get('title') || ''),
          official_url: String(form.get('official_url') || ''),
          organizer_name: String(form.get('organizer_name') || ''),
          platform_name: String(form.get('platform_name') || ''),
          description: String(form.get('description') || ''),
          hackathon_type: String(form.get('hackathon_type') || ''),
          start_at_utc: String(form.get('start_at_utc') || ''),
          end_at_utc: String(form.get('end_at_utc') || ''),
          registration_deadline_utc: String(form.get('registration_deadline_utc') || ''),
          prize_text: String(form.get('prize_text') || ''),
          location_text: String(form.get('location_text') || ''),
          what_to_know: String(form.get('what_to_know') || ''),
          review_note: String(form.get('review_note') || ''),
        };
        const result = await convertCandidate(candidate.id, input);
        setMessage(`Converted to hackathon #${result.hackathon_id}.`);
      }
      setMode('idle');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'The review action failed.');
    } finally {
      setBusy(false);
    }
  }

  if (candidate.status !== 'unreviewed') {
    return <div className="detail-section"><span className="eyebrow">REVIEW STATUS</span><p>This candidate has already been reviewed as <strong>{candidate.status}</strong>.</p></div>;
  }

  return <div className="candidate-actions">
    {mode === 'idle' ? <div className="action-row"><button className="button button-primary" type="button" onClick={() => setMode('convert')}>Convert to hackathon</button><button className="button button-secondary" type="button" onClick={() => setMode('reject')}>Reject candidate</button></div> : <form className="review-form" onSubmit={submit}>
      <div className="form-heading"><span className="eyebrow">{mode === 'convert' ? 'CONVERT CANDIDATE' : 'REJECT CANDIDATE'}</span><button className="text-button" type="button" onClick={() => setMode('idle')}>Cancel</button></div>
      {mode === 'convert' ? <div className="form-grid">
        <label>Title<input name="title" required defaultValue={candidate.text.slice(0, 120)} /></label>
        <label>HTTPS official URL<input name="official_url" type="url" required placeholder="https://..." /></label>
        <label>Organizer<input name="organizer_name" /></label>
        <label>Platform<input name="platform_name" defaultValue={candidate.source_name || ''} /></label>
        <label>Type<input name="hackathon_type" /></label>
        <label>Prize<input name="prize_text" /></label>
        <label>Start (UTC)<input name="start_at_utc" type="datetime-local" /></label>
        <label>End (UTC)<input name="end_at_utc" type="datetime-local" /></label>
        <label>Registration deadline (UTC)<input name="registration_deadline_utc" type="datetime-local" /></label>
        <label>Location<input name="location_text" /></label>
        <label className="form-wide">Description<textarea name="description" rows={4} /></label>
        <label className="form-wide">Before-signup notes<textarea name="what_to_know" rows={3} defaultValue="Review official rules, eligibility, judging, prize terms, and submission requirements before signing up." /></label>
        <label className="form-wide">Review note<textarea name="review_note" rows={2} /></label>
      </div> : <label>Review note<textarea name="review_note" rows={4} placeholder="Why should this lead be rejected?" /></label>}
      <button className="button button-primary" type="submit" disabled={busy}>{busy ? 'Saving…' : mode === 'convert' ? 'Create unreviewed hackathon' : 'Reject candidate'}</button>
      {message ? <p className="form-message" role="status">{message}</p> : null}
    </form>}
    {message && mode === 'idle' ? <p className="form-message" role="status">{message}</p> : null}
  </div>;
}
