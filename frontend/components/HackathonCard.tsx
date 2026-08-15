import Link from 'next/link';
import type { Hackathon } from '../lib/types';
import { formatPrize, formatShortDate } from '../lib/formatters';
import Countdown from './Countdown';

export default function HackathonCard({ item }: { item: Hackathon }) {
  return <article className="hack-card"><div className="card-topline"><span className="source-label">{item.source_name || item.platform_name || 'Source pending'}</span><span className={`status-pill status-${item.status}`}>{item.status}</span></div><h3><Link href={`/hackathons/${item.id}`}>{item.title}</Link></h3><p className="card-description">{item.what_to_know || 'Review the official rules, eligibility, and submission requirements before signing up.'}</p><div className="countdown"><span className="countdown-label">Time remaining</span><Countdown deadline={item.end_at_utc} /></div><div className="card-stats"><div><span>Prize</span><strong>{formatPrize(item.prize_amount_minor, item.prize_currency, item.prize_text)}</strong></div><div><span>People</span><strong>{item.participant_count === null ? 'Not reported' : item.participant_count.toLocaleString()}</strong></div><div><span>Register by</span><strong>{formatShortDate(item.registration_deadline_utc)}</strong></div><div><span>Type</span><strong>{item.hackathon_type || 'Not reported'}</strong></div></div><div className="card-footer"><span className="trust-label"><span className="trust-icon">✓</span>{item.verification_status}</span><Link className="arrow-link" href={`/hackathons/${item.id}`}>View details <span>↗</span></Link></div></article>;
}
