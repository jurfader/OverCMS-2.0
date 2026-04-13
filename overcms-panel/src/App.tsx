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
import { MarketplacePage } from '@/pages/Marketplace';
import { TemplatesPage } from '@/pages/Templates';
import { NavigationPage } from '@/pages/Navigation';
import { BackupsPage } from '@/pages/Backups';
import { SecurityPage } from '@/pages/Security';
import { BlogPage } from '@/pages/Blog';
import { ShopPage } from '@/pages/Shop';
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
            <Route path="/posts" element={<BlogPage />} />
            <Route path="/shop" element={<ShopPage />} />
            <Route path="/media" element={<MediaPage />} />
            <Route path="/seo" element={<SeoPage />} />
            <Route path="/navigation" element={<NavigationPage />} />
            <Route path="/templates" element={<TemplatesPage />} />
            <Route path="/users" element={<UsersPage />} />
            <Route path="/settings" element={<SettingsPage />} />
            <Route path="/modules" element={<ModulesPage />} />
            <Route path="/marketplace" element={<MarketplacePage />} />
            <Route path="/backups" element={<BackupsPage />} />
            <Route path="/security" element={<SecurityPage />} />
          </Routes>
        </Shell>
      </HashRouter>
    </QueryClientProvider>
  );
}
