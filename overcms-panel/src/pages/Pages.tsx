import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Plus, ExternalLink, Trash2, Edit3 } from 'lucide-react';
import { api } from '@/lib/api';
import { boot, type WpPage } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';

export function PagesPage() {
  const qc = useQueryClient();
  const [search, setSearch] = useState('');

  const { data: pages, isLoading } = useQuery({
    queryKey: ['pages', search],
    queryFn: () =>
      api<WpPage[]>('wp/v2/pages', {
        query: { per_page: 50, status: 'any', search: search || undefined, _fields: 'id,date,modified,slug,status,link,title,excerpt' },
      }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => api(`wp/v2/pages/${id}?force=true`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['pages'] }),
  });

  const create = useMutation({
    mutationFn: () =>
      api<WpPage>('wp/v2/pages', {
        method: 'POST',
        body: { title: 'Nowa strona', status: 'draft' },
      }),
    onSuccess: (page) => {
      qc.invalidateQueries({ queryKey: ['pages'] });
      editInDivi(page);
    },
  });

  const editInDivi = (page: WpPage) => {
    // Server-side launcher: /?overcms_launch_vb=1&post=ID
    // Handler weryfikuje sesje + uprawnienia i robi redirect do VB URL.
    // User nie widzi WP admin chrome — bezpośredni skok do frontendu z et_fb=1.
    const url = `${boot.siteUrl}?overcms_launch_vb=1&post=${page.id}`;
    window.open(url, '_blank', 'noopener');
  };

  return (
    <>
      <PageHeader
        title="Strony"
        description="Zarządzaj stronami swojej witryny — edycja w Divi visual builderze."
        actions={
          <Button icon={<Plus />} onClick={() => create.mutate()} disabled={create.isPending}>
            Nowa strona
          </Button>
        }
      />

      <div className="mb-4">
        <Input
          placeholder="Szukaj stron…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-sm"
        />
      </div>

      <div className="glass-card rounded-[var(--radius-lg)] overflow-hidden">
        <div className="grid grid-cols-[1fr_120px_140px_100px] px-5 py-2.5 border-b border-[var(--color-border)] bg-[var(--color-surface-elevated)]">
          <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Tytuł</span>
          <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Status</span>
          <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Modyfikowano</span>
          <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)] text-right">Akcje</span>
        </div>
        <div className="divide-y divide-[var(--color-border)]">
          {isLoading && <p className="text-sm text-[var(--color-muted-foreground)] px-5 py-4">Ładowanie…</p>}
          {pages?.map((page) => (
            <div
              key={page.id}
              className="grid grid-cols-[1fr_120px_140px_100px] items-center px-5 py-3.5 hover:bg-[var(--color-surface-elevated)] transition-colors"
            >
              <button
                onClick={() => editInDivi(page)}
                className="text-sm text-[var(--color-foreground)] font-medium text-left truncate hover:text-[var(--color-primary)]"
                dangerouslySetInnerHTML={{ __html: page.title.rendered || '(bez tytułu)' }}
              />
              <Badge variant={page.status === 'publish' ? 'success' : 'warning'}>{page.status}</Badge>
              <span className="text-xs text-[var(--color-muted-foreground)]">
                {new Date(page.modified).toLocaleDateString('pl-PL')}
              </span>
              <div className="flex items-center justify-end gap-1">
                <Button size="icon" variant="ghost" title="Edytuj w Divi" onClick={() => editInDivi(page)}>
                  <Edit3 className="w-3.5 h-3.5" />
                </Button>
                <Button size="icon" variant="ghost" title="Otwórz" onClick={() => window.open(page.link, '_blank')}>
                  <ExternalLink className="w-3.5 h-3.5" />
                </Button>
                <Button
                  size="icon"
                  variant="ghost"
                  title="Usuń"
                  onClick={() => {
                    if (confirm(`Usunąć stronę „${page.title.rendered}"?`)) {
                      remove.mutate(page.id);
                    }
                  }}
                >
                  <Trash2 className="w-3.5 h-3.5 text-[var(--color-destructive)]" />
                </Button>
              </div>
            </div>
          ))}
          {pages?.length === 0 && !isLoading && (
            <p className="text-sm text-[var(--color-muted-foreground)] px-5 py-4">Brak stron.</p>
          )}
        </div>
      </div>
    </>
  );
}
