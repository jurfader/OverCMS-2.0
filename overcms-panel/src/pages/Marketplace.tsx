import { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Search, Download, Star, Users, Loader2, CheckCircle2 } from 'lucide-react';
import { api } from '@/lib/api';
import type { MarketplacePlugin, MarketplaceResponse } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { cn } from '@/lib/cn';

type Browse = 'popular' | 'featured' | 'new' | 'updated';

export function MarketplacePage() {
  const qc = useQueryClient();
  const [browse, setBrowse] = useState<Browse>('popular');
  const [search, setSearch] = useState('');
  const [submittedSearch, setSubmittedSearch] = useState('');
  const [page, setPage] = useState(1);
  const [installingSlug, setInstallingSlug] = useState<string | null>(null);

  const isSearch = submittedSearch.trim() !== '';

  const { data, isLoading, isFetching } = useQuery({
    queryKey: isSearch ? ['marketplace', 'search', submittedSearch, page] : ['marketplace', browse, page],
    queryFn: () =>
      isSearch
        ? api<MarketplaceResponse>('overcms/v1/marketplace/search', {
            query: { q: submittedSearch, page, per_page: 24 },
          })
        : api<MarketplaceResponse>('overcms/v1/marketplace', {
            query: { browse, page, per_page: 24 },
          }),
  });

  const install = useMutation({
    mutationFn: (slug: string) =>
      api<{ success: boolean; activated?: boolean }>('overcms/v1/marketplace/install', {
        method: 'POST',
        body: { slug, activate: true },
      }),
    onMutate: (slug) => setInstallingSlug(slug),
    onSettled: () => setInstallingSlug(null),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['marketplace'] });
      qc.invalidateQueries({ queryKey: ['modules'] });
    },
  });

  const onSearch = (e: React.FormEvent) => {
    e.preventDefault();
    setSubmittedSearch(search);
    setPage(1);
  };

  const tabs: { id: Browse; label: string }[] = useMemo(
    () => [
      { id: 'popular', label: 'Popularne' },
      { id: 'featured', label: 'Polecane' },
      { id: 'new', label: 'Nowe' },
      { id: 'updated', label: 'Ostatnio aktualizowane' },
    ],
    [],
  );

  return (
    <>
      <PageHeader
        title="Marketplace"
        description="Tysiące darmowych pluginów z oficjalnego repozytorium WordPress.org"
      />

      {/* Search */}
      <form onSubmit={onSearch} className="mb-4 flex gap-2 max-w-xl">
        <div className="relative flex-1">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-subtle)]" />
          <Input
            placeholder="Szukaj pluginów (np. forms, seo, security)…"
            className="pl-9"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <Button type="submit">Szukaj</Button>
        {isSearch && (
          <Button
            type="button"
            variant="ghost"
            onClick={() => {
              setSearch('');
              setSubmittedSearch('');
              setPage(1);
            }}
          >
            Wyczyść
          </Button>
        )}
      </form>

      {/* Tabs (gdy nie ma wyszukiwania) */}
      {!isSearch && (
        <div className="flex items-center gap-1 mb-4 border-b border-[var(--color-border)]">
          {tabs.map((t) => (
            <button
              key={t.id}
              onClick={() => {
                setBrowse(t.id);
                setPage(1);
              }}
              className={cn(
                'px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px',
                browse === t.id
                  ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                  : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]',
              )}
            >
              {t.label}
            </button>
          ))}
        </div>
      )}

      {/* Grid */}
      {isLoading && (
        <p className="text-sm text-[var(--color-muted-foreground)]">Ładowanie…</p>
      )}

      {data && (
        <>
          {data.items.length === 0 ? (
            <p className="text-sm text-[var(--color-muted-foreground)] py-12 text-center">
              Brak wyników dla zapytania „{submittedSearch}".
            </p>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {data.items.map((plugin) => (
                <PluginCard
                  key={plugin.slug}
                  plugin={plugin}
                  installing={installingSlug === plugin.slug}
                  onInstall={() => install.mutate(plugin.slug)}
                />
              ))}
            </div>
          )}

          {/* Pagination */}
          {data.info.pages > 1 && (
            <div className="flex items-center justify-center gap-2 mt-6">
              <Button
                size="sm"
                variant="outline"
                disabled={page <= 1 || isFetching}
                onClick={() => setPage((p) => p - 1)}
              >
                Poprzednia
              </Button>
              <span className="text-xs text-[var(--color-muted-foreground)]">
                Strona {data.info.page} z {data.info.pages}
              </span>
              <Button
                size="sm"
                variant="outline"
                disabled={page >= data.info.pages || isFetching}
                onClick={() => setPage((p) => p + 1)}
              >
                Następna
              </Button>
            </div>
          )}
        </>
      )}
    </>
  );
}

interface PluginCardProps {
  plugin: MarketplacePlugin;
  installing: boolean;
  onInstall: () => void;
}

function PluginCard({ plugin, installing, onInstall }: PluginCardProps) {
  const installs =
    plugin.activeInstalls >= 1_000_000
      ? `${(plugin.activeInstalls / 1_000_000).toFixed(1)}M+`
      : plugin.activeInstalls >= 1_000
        ? `${Math.round(plugin.activeInstalls / 1_000)}k+`
        : plugin.activeInstalls.toString();

  return (
    <div className="glass-card rounded-[var(--radius-lg)] p-5 flex flex-col gap-3">
      <div className="flex items-start gap-3">
        <div className="w-12 h-12 rounded-[var(--radius)] bg-[var(--color-surface-elevated)] flex items-center justify-center overflow-hidden shrink-0">
          {plugin.icon ? (
            <img src={plugin.icon} alt="" className="w-full h-full object-cover" />
          ) : (
            <span className="text-xs text-[var(--color-subtle)]">{plugin.slug.slice(0, 2).toUpperCase()}</span>
          )}
        </div>
        <div className="flex-1 min-w-0">
          <h3
            className="text-sm font-semibold text-[var(--color-foreground)] truncate"
            dangerouslySetInnerHTML={{ __html: plugin.name }}
          />
          <p className="text-[10px] text-[var(--color-subtle)] truncate">
            {plugin.author && (
              <span dangerouslySetInnerHTML={{ __html: plugin.author }} />
            )}
          </p>
        </div>
      </div>

      <p
        className="text-xs text-[var(--color-muted-foreground)] line-clamp-3 min-h-[3em]"
        dangerouslySetInnerHTML={{ __html: plugin.shortDescription }}
      />

      <div className="flex items-center gap-3 text-[10px] text-[var(--color-subtle)]">
        {plugin.rating !== null && (
          <span className="inline-flex items-center gap-1">
            <Star className="w-3 h-3 fill-current text-[var(--color-warning)]" />
            {(plugin.rating / 20).toFixed(1)}
          </span>
        )}
        <span className="inline-flex items-center gap-1">
          <Users className="w-3 h-3" />
          {installs}
        </span>
        {plugin.version && <span>v{plugin.version}</span>}
      </div>

      <div className="flex items-center justify-between gap-2 mt-auto pt-2 border-t border-[var(--color-border)]">
        {plugin.installed ? (
          <Badge variant={plugin.active ? 'success' : 'secondary'}>
            {plugin.active ? 'aktywny' : 'zainstalowany'}
          </Badge>
        ) : (
          <span className="text-[10px] text-[var(--color-subtle)]">niezainstalowany</span>
        )}
        {plugin.installed ? (
          <Button size="sm" variant="outline" disabled>
            <CheckCircle2 className="w-3.5 h-3.5 mr-1" /> OK
          </Button>
        ) : (
          <Button size="sm" onClick={onInstall} disabled={installing}>
            {installing ? (
              <>
                <Loader2 className="w-3.5 h-3.5 mr-1 animate-spin" /> Instaluję…
              </>
            ) : (
              <>
                <Download className="w-3.5 h-3.5 mr-1" /> Zainstaluj
              </>
            )}
          </Button>
        )}
      </div>
    </div>
  );
}
