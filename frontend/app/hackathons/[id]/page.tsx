import Link from 'next/link';
import { notFound } from 'next/navigation';
import Countdown from '../../../components/Countdown';
import { getHackathon } from '../../../lib/api/hackathons';
import { formatDate, formatPrize } from '../../../lib/formatters';

export const dynamic = 'force-dynamic';

function findLink(links: { kind: string; url: string; label: string }[], kind: string) {
  return links.find(link => link.kind === kind);
}

export default async function HackathonDetail({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  let result;
  try { result = await getHackathon(id); } catch { notFound(); }
  const { hackathon, links, checks } = result;
  const registrationLink = findLink(links, 'registration');
  const rulesLink = findLink(links, 'rules');
  const judgingLink = findLink(links, 'judging');
  const applyUrl = registrationLink?.url || hackathon.official_url;

  return <section className="detail-page">
    <Link className="back-link" href="/">← Back to opportunities</Link>
    <div className="detail-header">
      <div>
        <span className="eyebrow">{hackathon.source_name || hackathon.platform_name || 'Source pending'}</span>
        <h1>{hackathon.title}</h1>
        <p className="detail-organizer">{hackathon.organizer_name || 'Organizer not reported'}</p>
      </div>
      <span className={`status-pill status-${hackathon.status}`}>{hackathon.status}</span>
    </div>
    <div className="detail-layout">
      <div className="detail-main">
        <div className="countdown-panel">
          <span className="eyebrow">TIME REMAINING</span>
          <Countdown deadline={hackathon.end_at_utc} />
          <span>Submission/event end · {formatDate(hackathon.end_at_utc)}</span>
        </div>
        <div className="detail-section application-panel">
          <span className="eyebrow">HOW TO APPLY</span>
          <p className="section-intro">Use the official event page as the final authority. Hackview provides this checklist as practical guidance; exact eligibility and submission requirements may vary by event.</p>
          <ol className="application-steps">
            <li><strong>Open the official application page.</strong><span>Start with the registration link when one is available, otherwise use the official event website.</span></li>
            <li><strong>Check eligibility and rules.</strong><span>Review the official rules, participation requirements, judging criteria, and prize terms before committing.</span></li>
            <li><strong>Prepare your submission.</strong><span>Follow the event’s official instructions for the project, materials, repository, demo, or other required entries.</span></li>
            <li><strong>Submit before the deadline.</strong><span>Registration deadline: {formatDate(hackathon.registration_deadline_utc)}. Confirm the deadline and timezone on the official page.</span></li>
            <li><strong>Keep your confirmation.</strong><span>Save the registration or submission confirmation and return to the official page for updates.</span></li>
          </ol>
          <div className="detail-actions">
            <a className="button button-primary" href={applyUrl} target="_blank" rel="noopener noreferrer">{registrationLink ? 'Apply / register ↗' : 'Open official website ↗'}</a>
            {rulesLink ? <a className="button button-secondary" href={rulesLink.url} target="_blank" rel="noopener noreferrer">Read rules ↗</a> : null}
          </div>
        </div>
        <div className="detail-section">
          <span className="eyebrow">EVENT TIMELINE</span>
          <div className="event-timeline">
            <div><span>Starts</span><strong>{formatDate(hackathon.start_at_utc)}</strong></div>
            <div><span>Register by</span><strong>{formatDate(hackathon.registration_deadline_utc)}</strong></div>
            <div><span>Ends</span><strong>{formatDate(hackathon.end_at_utc)}</strong></div>
          </div>
        </div>
        <div className="detail-section">
          <span className="eyebrow">ABOUT THIS OPPORTUNITY</span>
          <p>{hackathon.description || 'The source has not provided a longer description yet.'}</p>
        </div>
        <div className="detail-section">
          <span className="eyebrow">BEFORE YOU SIGN UP</span>
          <p>{hackathon.what_to_know || 'Review the official rules, eligibility, judging criteria, prize terms, and submission requirements before signing up.'}</p>
        </div>
        <div className="detail-section">
          <span className="eyebrow">VERIFICATION NOTES</span>
          <p>{hackathon.legitimacy_notes || 'Use the official source link below as the authority.'}</p>
        </div>
      </div>
      <aside className="detail-aside">
        <div className="aside-panel">
          <span className="eyebrow">AT A GLANCE</span>
          <dl>
            <div><dt>Prize</dt><dd>{formatPrize(hackathon.prize_amount_minor, hackathon.prize_currency, hackathon.prize_text)}</dd></div>
            <div><dt>Registration</dt><dd>{formatDate(hackathon.registration_deadline_utc)}</dd></div>
            <div><dt>Participants</dt><dd>{hackathon.participant_count === null ? 'Not reported' : hackathon.participant_count.toLocaleString()}</dd></div>
            <div><dt>Type</dt><dd>{hackathon.hackathon_type || 'Not reported'}</dd></div>
            <div><dt>Format</dt><dd>{hackathon.online_or_location || 'Not reported'}</dd></div>
            <div><dt>Location</dt><dd>{hackathon.location_text || 'Not reported'}</dd></div>
          </dl>
        </div>
        <a className="button button-primary full-width" href={applyUrl} target="_blank" rel="noopener noreferrer">{registrationLink ? 'Go to registration ↗' : 'Open official website ↗'}</a>
        <div className="aside-panel trust-panel"><span className="trust-icon">✓</span><div><strong>{hackathon.verification_status}</strong><span>Source status for this listing</span></div></div>
      </aside>
    </div>
    {checks.length ? <div className="detail-section"><span className="eyebrow">VERIFICATION EVIDENCE</span><div className="source-links">{checks.map((check, index) => <div key={`${check.checked_at}-${index}`}><strong>{check.result}</strong> · {check.check_type}{check.evidence_excerpt ? ` · ${check.evidence_excerpt}` : ''}</div>)}</div></div> : null}
    {links.length ? <div className="detail-section"><span className="eyebrow">SOURCE LINKS</span><div className="source-links">{links.map(link => <a key={link.url} href={link.url} target="_blank" rel="noopener noreferrer">{link.label || (link.kind === 'judging' ? 'Judging details' : link.kind)} ↗</a>)}</div>{judgingLink ? <p className="source-note">Judging details are provided by the source and may explain how submissions are evaluated.</p> : null}</div> : null}
  </section>;
}
