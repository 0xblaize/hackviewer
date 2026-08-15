import type { Metadata } from 'next';
import Shell from '../components/Shell';
import '../styles/globals.css';

export const metadata: Metadata = { title: 'Hackview', description: 'A focused discovery dashboard for credible hackathons.' };

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="en"><body><Shell>{children}</Shell></body></html>;
}
