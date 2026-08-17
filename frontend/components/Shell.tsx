import Link from 'next/link';
import HackviewLogo from './HackviewLogo';

export default function Shell({ children }: { children: React.ReactNode }) {
  return <div className="app-shell"><header className="topbar"><Link className="brand" href="/" aria-label="Hackview home"><HackviewLogo /></Link><div className="topbar-meta"><span className="live-dot" /> Tracking verified opportunities <span className="meta-separator">·</span> Source leads included below <span className="meta-separator">·</span> UTC</div></header><main>{children}</main><footer className="footer"><span><HackviewLogo compact /> Built for people who want to find the right room before it gets crowded.</span><span>Sources are always linked and freshness is visible.</span></footer></div>;
}
