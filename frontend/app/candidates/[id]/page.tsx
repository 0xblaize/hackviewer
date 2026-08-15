import Link from 'next/link';
import { notFound } from 'next/navigation';
import CandidateActions from '../../../components/CandidateActions';
import { getCandidate } from '../../../lib/api/candidates';

export const dynamic = 'force-dynamic';

export default async function CandidateDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  let candidate;
  try { candidate = (await getCandidate(id)).candidate; } catch { notFound(); }
  return <section className="detail-page"><Link className="back-link" href="/candidates">← Back to review queue</Link><div className="detail-header"><div><span className="eyebrow">{candidate.source_name || 'SOURCE PENDING'}</span><h1>Review this lead.</h1><p className="detail-organizer">{candidate.author_handle ? `@${candidate.author_handle}` : 'Author not reported'} · {candidate.posted_at || 'Date not reported'}</p></div><span className="status-pill">{candidate.status}</span></div><div className="detail-layout"><div className="detail-main"><div className="detail-section"><span className="eyebrow">DISCOVERY POST</span><p className="candidate-post">{candidate.text}</p><a className="arrow-link" href={candidate.post_url} target="_blank" rel="noopener">Open original post ↗</a></div><CandidateActions candidate={candidate} /></div><aside className="detail-aside"><div className="aside-panel"><span className="eyebrow">PROVENANCE</span><dl><div><dt>External key</dt><dd>{candidate.external_key}</dd></div><div><dt>Retrieved</dt><dd>{candidate.retrieved_at || 'Not reported'}</dd></div><div><dt>Raw payload</dt><dd>{candidate.payload_path || 'Not stored'}</dd></div></dl></div></aside></div></section>;
}
