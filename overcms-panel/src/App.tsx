import { HashRouter, Routes, Route } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Shell } from '@/components/layout/Shell';
import { DashboardPage } from '@/pages/Dashboard';
import { PagesPage } from '@/pages/Pages';
import { MediaPage } from '@/pages/Media';
import { SeoPage } from '@/pages/Seo';
import { UsersPage } from '@/pages/Users';
import { SettingsPage } from '@/pages/Settings';
import { ModulesPage } from '@/pages/Modules';
import { Placeholder } from '@/pages/Placeholder';

const qc = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 30_000, retry: 1, refetchOnWindowFocus: false },
  },
});

export function App() {
  return (
    <QueryClientProvider client={qc}>
      <HashRouter>
        <Shell>
          <Routes>
            <Route path="/" element={<DashboardPage />} />
            <Route path="/pages" element={<PagesPage />} />
            <Route path="/posts" element={<Placeholder title="Treści" description="Wpisy bloga" />} />
            <Route path="/media" element={<MediaPage />} />
            <Route path="/seo" element={<SeoPage />} />
            <Route path="/navigation" element={<Placeholder title="Nawigacja" description="Menu witryny" />} />
            <Route path="/templates" element={<Placeholder title="Szablony" description="Szablony stron Divi" />} />
            <Route path="/users" element={<UsersPage />} />
            <Route path="/settings" element={<SettingsPage />} />
            <Route path="/modules" element={<ModulesPage />} />
          </Routes>
        </Shell>
      </HashRouter>
    </QueryClientProvider>
  );
}
