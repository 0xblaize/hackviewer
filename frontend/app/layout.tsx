import type { Metadata } from 'next';
import Shell from '../components/Shell';
import '../styles/globals.css';

export const metadata: Metadata = {
  title: { default: 'Hackview', template: '%s · Hackview' },
  description: 'A focused discovery dashboard for credible hackathons.',
  icons: { icon: '/icon.svg', shortcut: '/icon.svg' },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en"><body><Shell>{children}</Shell></body></html>;
}
