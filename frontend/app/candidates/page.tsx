import Link from 'next/link';
import { getCandidates } from '../../lib/api/candidates';
import type { Candidate } from '../../lib/types';

export const dynamic = 'force-dynamic';

export default async function CandidatesPage() {
  let candidates: Candidate[] = [];
  let error = '';
  try {
    candidates = (await getCandidates()).items;
  } catch (caught) {
    error = caught instanceof Error ? caught.message : 'Candidate API unavailable.';
    candidates = [];
  }
  return <section className="detail-page"><Link className="back-link" href="/">← Back to opportunities</Link><div className="detail-header"><div><span className="eyebrow">PRIVATE REVIEW QUEUE</span><h1>Discovery candidates.</h1><p className="detail-organizer">Social discovery leads stay private until a reviewer checks the official details.</p></div><span className="status-pill">{candidates.length} unreviewed</span></div>{error ? <div className="error-banner" role="alert">{error}</div> : candidates.length ? <div className="candidate-list">{candidates.map(candidate => <article className="candidate-card" key={candidate.id}><div><div className="card-topline"><span className="source-label">{candidate.source_name || 'Source pending'}</span><span className="status-pill">{candidate.status}</span></div><h2>{candidate.text.slice(0, 180)}{candidate.text.length > 180 ? '…' : ''}</h2><p>{candidate.author_handle ? `@${candidate.author_handle}` : 'Author not reported'} · {candidate.posted_at || 'Date not reported'}</p></div><Link className="button button-primary" href={`/candidates/${candidate.id}`}>Review lead</Link></article>)}</div> : <div className="empty-state"><div className="empty-icon">⌁</div><span className="eyebrow">QUEUE CLEAR</span><h3>No unreviewed candidates.</h3><p>New discovery leads will appear here after the scheduled source collection runs.</p></div>}</section>;
}
