import HackathonCard from '../components/HackathonCard';
import { getHackathons } from '../lib/api/hackathons';

type SearchParams = Promise<Record<string, string | string[] | undefined>>;

export const dynamic = 'force-dynamic';

function value(params: Record<string, string | string[] | undefined>, key: string) {
  const item = params[key];
  return Array.isArray(item) ? item[0] || '' : item || '';
}

export default async function Home({ searchParams }: { searchParams: SearchParams }) {
  const params = await searchParams;
  const filters = { q: value(params, 'q'), status: value(params, 'status'), type: value(params, 'type'), source: value(params, 'source'), horizon: value(params, 'horizon'), sort: value(params, 'sort') || 'ending' };
  let result;
  let error = '';
  try {
    result = await getHackathons(filters);
  } catch (caught) {
    error = caught instanceof Error ? caught.message : 'The hackathon API is unavailable.';
    result = { items: [], summary: { verified: 0, ending: 0, sources: 0 }, options: { types: [], sources: [] } };
  }
  return <><section className="hero-section"><div className="hero-copy"><span className="eyebrow">THE SIGNAL BOARD</span><h1>Find the next<br /><em>worthwhile</em> hackathon.</h1><p>Less noise. Better opportunities. A focused view of hackathons worth your time, with the details that matter before you commit.</p></div><div className="hero-aside"><div className="orb orb-one" /><div className="orb orb-two" /><div className="hero-aside-content"><span className="eyebrow">DISCOVERY MODE</span><strong>Real sources.<br />Clear signals.</strong><span className="aside-note">Every listing will link back to its official source.</span></div></div></section><section className="summary-grid"><div className="summary-card"><span className="summary-icon">◉</span><div><strong>{result.summary.verified}</strong><span>Verified active</span></div></div><div className="summary-card"><span className="summary-icon accent-icon">↘</span><div><strong>{result.summary.ending}</strong><span>Ending this week</span></div></div><div className="summary-card"><span className="summary-icon">◎</span><div><strong>{result.summary.sources}</strong><span>Source channels</span></div></div></section><section className="board-section"><div className="section-heading"><div><span className="eyebrow">LIVE BOARD</span><h2>Opportunities in view</h2></div><span className="result-count">{result.items.length} results</span></div><form className="filter-bar" method="get"><label className="search-field"><span>⌕</span><input type="search" name="q" defaultValue={filters.q} placeholder="Search by name, type, or organizer..." /></label><select name="status" defaultValue={filters.status}><option value="">All status</option><option value="active">Active</option><option value="upcoming">Upcoming</option><option value="closed">Closed</option></select><select name="type" defaultValue={filters.type}><option value="">All types</option>{result.options.types.map(type => <option key={type} value={type}>{type}</option>)}</select><select name="source" defaultValue={filters.source}><option value="">All sources</option>{result.options.sources.map(source => <option key={source} value={source}>{source}</option>)}</select><select name="horizon" defaultValue={filters.horizon}><option value="">Any registration deadline</option><option value="1">Next 24 hours</option><option value="7">Next 7 days</option><option value="30">Next 30 days</option></select><select name="sort" defaultValue={filters.sort}><option value="ending">Ending soon</option><option value="noise">Low-noise first</option><option value="prize">Prize value</option><option value="newest">Recently added</option></select><button className="button button-primary" type="submit">Filter</button></form>{error ? <div className="error-banner" role="alert">{error}</div> : result.items.length ? <div className="card-grid">{result.items.map(item => <HackathonCard key={item.id} item={item} />)}</div> : <div className="empty-state"><div className="empty-icon">⌁</div><span className="eyebrow">NO VERIFIED LISTINGS YET</span><h3>The board is quiet for now.</h3><p>Hackathons will appear here once a real source has been connected and its official details have been checked. No invented opportunities, ever.</p></div>}</section></>;
}
