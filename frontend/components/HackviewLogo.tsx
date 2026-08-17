type HackviewLogoProps = {
  compact?: boolean;
};

export default function HackviewLogo({ compact = false }: HackviewLogoProps) {
  return <span className={`hackview-logo${compact ? ' hackview-logo-compact' : ''}`} aria-label="Hackview">
    <svg className="hackview-logo-mark" viewBox="0 0 40 40" role="img" aria-hidden="true">
      <path d="M8 8h9v5h-4v14h4v5H8V8Zm24 0h-9v5h4v14h-4v5h9V8Z" fill="currentColor" />
      <path d="M17 17h6v6h-6z" fill="var(--lime)" />
      <path d="M20 3v6M20 31v6M3 20h6M31 20h6" stroke="var(--lime)" strokeWidth="2" strokeLinecap="round" />
    </svg>
    {!compact ? <span className="hackview-logo-word">hackview<span>.</span></span> : null}
  </span>;
}
