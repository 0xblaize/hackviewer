'use client';

import { useEffect, useState } from 'react';

function label(value: string | null) {
  if (!value) return 'Deadline not reported';
  const diff = new Date(value).getTime() - Date.now();
  if (Number.isNaN(diff)) return 'Deadline not reported';
  if (diff <= 0) return 'Ended';
  const minutes = Math.floor(diff / 60000);
  if (minutes < 60) return `${minutes}m`;
  const hours = Math.floor(minutes / 60);
  if (hours < 48) return `${hours}h ${minutes % 60}m`;
  return `${Math.floor(hours / 24)}d ${hours % 24}h`;
}

export default function Countdown({ deadline }: { deadline: string | null }) {
  const [value, setValue] = useState(() => label(deadline));
  useEffect(() => {
    const update = () => setValue(label(deadline));
    update();
    const timer = window.setInterval(update, 60000);
    return () => window.clearInterval(timer);
  }, [deadline]);
  return <strong>{value}</strong>;
}
