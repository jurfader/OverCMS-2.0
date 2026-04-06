import { useQuery } from '@tanstack/react-query';
import { ExternalLink, CheckCircle2, AlertTriangle } from 'lucide-react';
import { api } from '@/lib/api';
import { PageHeader } from '@/components/layout/Shell';
import { Card, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';

interface SeoGlobal {
  rankMathInstalled: boolean;
  sitemapUrl: string;
  robotsUrl: string;
  titleSeparator: string;
  siteName: string;
  siteDescription: string;
}

export function SeoPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['seo-global'],
    queryFn: () => api<SeoGlobal>('overcms/v1/seo/global'),
  });

  return (
    <>
      <PageHeader title="SEO" description="Globalne ustawienia SEO oraz status integracji z Rank Math." />

      {isLoading && <p className="text-sm text-[var(--color-muted-foreground)]">Ładowanie…</p>}

      {data && (
        <div className="space-y-4">
          <Card>
            <div className="flex items-center gap-3">
              {data.rankMathInstalled ? (
                <>
                  <span className="w-10 h-10 rounded-full bg-[color-mix(in_srgb,var(--color-success)_15%,transparent)] text-[var(--color-success)] flex items-center justify-center">
                    <CheckCircle2 className="w-5 h-5" />
                  </span>
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-[var(--color-foreground)]">Rank Math aktywne</p>
                    <p className="text-xs text-[var(--color-muted-foreground)]">Pełne SEO, sitemap i schema są obsługiwane.</p>
                  </div>
                </>
              ) : (
                <>
                  <span className="w-10 h-10 rounded-full bg-[color-mix(in_srgb,var(--color-warning)_15%,transparent)] text-[var(--color-warning)] flex items-center justify-center">
                    <AlertTriangle className="w-5 h-5" />
                  </span>
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-[var(--color-foreground)]">Rank Math nie jest aktywne</p>
                    <p className="text-xs text-[var(--color-muted-foreground)]">Aktywuj plugin „Rank Math SEO" w sekcji Moduły.</p>
                  </div>
                </>
              )}
            </div>
          </Card>

          <Card>
            <CardHeader title="Linki techniczne" description="Pliki SEO udostępniane robotom wyszukiwarek" />
            <div className="space-y-2">
              <LinkRow label="Sitemap XML" url={data.sitemapUrl} />
              <LinkRow label="Plik robots.txt" url={data.robotsUrl} />
            </div>
          </Card>

          <Card>
            <CardHeader title="Tożsamość witryny" />
            <dl className="space-y-2 text-sm">
              <Row label="Nazwa witryny" value={data.siteName} />
              <Row label="Opis (tagline)" value={data.siteDescription || '—'} />
              <Row label="Separator tytułu" value={data.titleSeparator} />
            </dl>
          </Card>
        </div>
      )}
    </>
  );
}

function LinkRow({ label, url }: { label: string; url: string }) {
  return (
    <div className="flex items-center justify-between gap-3 py-2 border-b border-[var(--color-border)] last:border-0">
      <div>
        <p className="text-sm text-[var(--color-foreground)]">{label}</p>
        <p className="text-xs text-[var(--color-muted-foreground)] font-mono break-all">{url}</p>
      </div>
      <Button size="sm" variant="outline" icon={<ExternalLink />} onClick={() => window.open(url, '_blank')}>
        Otwórz
      </Button>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3 py-1.5">
      <dt className="text-[var(--color-muted-foreground)]">{label}</dt>
      <dd className="text-[var(--color-foreground)] font-medium">{value}</dd>
    </div>
  );
}
