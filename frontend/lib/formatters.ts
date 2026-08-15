export function formatDate(value: string | null, fallback = 'Not reported') {
  if (!value) return fallback;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? fallback : `${date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })} · ${date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: false })} UTC`;
}

export function formatShortDate(value: string | null) {
  if (!value) return 'Not reported';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 'Not reported' : date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

export function formatPrize(amount: number | null, currency: string | null, text: string | null) {
  if (text) return text;
  if (amount === null) return 'Not reported';
  return `${(amount / 100).toLocaleString()} ${currency || ''}`.trim();
}
