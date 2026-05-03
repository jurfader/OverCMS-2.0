import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Plus, ExternalLink, Trash2, Edit3, X, Loader2,
  Newspaper, Search, CheckCircle2,
} from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { boot, type WpPost, type WpCategory } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';

type StatusFilter = 'any' | 'publish' | 'draft';

export function BlogPage() {
  const qc = useQueryClient();
  const [search, setSearch]             = useState('');
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('any');
  const [showModal, setShowModal]       = useState(false);

  // Lista wpisów
  const { data: posts, isLoading } = useQuery({
    queryKey: ['posts', statusFilter, search],
    queryFn: () =>
      api<WpPost[]>('wp/v2/posts', {
        query: {
          per_page: 50,
          status: statusFilter === 'any' ? 'any' : statusFilter,
          search: search || undefined,
          _fields: 'id,date,modified,slug,status,link,title,excerpt,categories',
        },
      }),
  });

  // Kategorie
  const { data: categories } = useQuery({
    queryKey: ['categories'],
    queryFn: () => api<WpCategory[]>('wp/v2/categories', { query: { per_page: 100 } }),
  });

  // Zmiana statusu
  const setStatus = useMutation({
    mutationFn: ({ id, status }: { id: number; status: 'publish' | 'draft' }) =>
      api(`wp/v2/posts/${id}`, { method: 'POST', body: { status } }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['posts'] }),
  });

  // Usuń
  const remove = useMutation({
    mutationFn: (id: number) => api(`wp/v2/posts/${id}?force=true`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['posts'] }),
  });

  const editInDivi = (post: WpPost) => {
    const url = `${boot.siteUrl}?overcms_launch_vb=1&post=${post.id}`;
    window.open(url, '_blank', 'noopener');
  };

  const catMap = Object.fromEntries((categories ?? []).map((c) => [c.id, c.name]));

  const counts = {
    all:       posts?.length ?? 0,
    published: posts?.filter((p) => p.status === 'publish').length ?? 0,
    drafts:    posts?.filter((p) => p.status === 'draft').length ?? 0,
  };

  const tabs: { key: StatusFilter; label: string; count: number }[] = [
    { key: 'any',     label: 'Wszystkie',    count: counts.all },
    { key: 'publish', label: 'Opublikowane', count: counts.published },
    { key: 'draft',   label: 'Szkice',       count: counts.drafts },
  ];

  return (
    <>
      <PageHeader
        title="Blog"
        description="Twórz i zarządzaj wpisami — edycja treści przez Divi visual builder."
        actions={
          <Button icon={<Plus />} onClick={() => setShowModal(true)}>
            Nowy wpis
          </Button>
        }
      />

      {/* Filtry + szukaj */}
      <div className="flex items-center justify-between gap-4 mb-4 flex-wrap">
        <div className="flex items-center gap-1 border-b border-[var(--color-border)]">
          {tabs.map((t) => (
            <button
              key={t.key}
              onClick={() => setStatusFilter(t.key)}
              className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px flex items-center gap-1.5 ${
                statusFilter === t.key
                  ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                  : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]'
              }`}
            >
              {t.label}
              <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)]">
                {t.count}
              </span>
            </button>
          ))}
        </div>
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[var(--color-subtle)]" />
          <Input
            placeholder="Szukaj wpisów…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-8 w-52"
          />
        </div>
      </div>

      {/* Lista wpisów */}
      {isLoading && (
        <div className="flex items-center gap-2 text-sm text-[var(--color-muted-foreground)] py-8 justify-center">
          <Loader2 className="w-4 h-4 animate-spin" /> Ładowanie…
        </div>
      )}

      {!isLoading && posts?.length === 0 && (
        <div className="glass-card rounded-[var(--radius-lg)] flex flex-col items-center justify-center py-16 text-center">
          <Newspaper className="w-12 h-12 text-[var(--color-subtle)] mb-3" />
          <p className="text-sm font-medium text-[var(--color-foreground)]">Brak wpisów</p>
          <p className="text-xs text-[var(--color-muted-foreground)] mt-1 mb-4">
            Kliknij "Nowy wpis" aby dodać pierwszy artykuł
          </p>
          <Button size="sm" icon={<Plus />} onClick={() => setShowModal(true)}>
            Nowy wpis
          </Button>
        </div>
      )}

      {posts && posts.length > 0 && (
        <div className="glass-card rounded-[var(--radius-lg)] overflow-hidden">
          <div className="grid grid-cols-[1fr_160px_120px_140px_auto] px-5 py-2.5 border-b border-[var(--color-border)] bg-[var(--color-surface-elevated)]">
            <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Tytuł</span>
            <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Kategorie</span>
            <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Status</span>
            <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Data</span>
            <span />
          </div>
          <div className="divide-y divide-[var(--color-border)]">
            {posts.map((post) => (
              <PostRow
                key={post.id}
                post={post}
                catMap={catMap}
                onEdit={() => editInDivi(post)}
                onView={() => window.open(post.link, '_blank')}
                onToggleStatus={() =>
                  setStatus.mutate({ id: post.id, status: post.status === 'publish' ? 'draft' : 'publish' })
                }
                onDelete={() => {
                  const title = post.title.rendered || '(bez tytułu)';
                  if (confirm(`Usunąć wpis „${title}"?`)) remove.mutate(post.id);
                }}
              />
            ))}
          </div>
        </div>
      )}

      {/* Modal nowego wpisu */}
      {showModal && (
        <NewPostModal
          categories={categories ?? []}
          onClose={() => setShowModal(false)}
          onCreated={(post, openDivi) => {
            qc.invalidateQueries({ queryKey: ['posts'] });
            setShowModal(false);
            if (openDivi) editInDivi(post);
          }}
        />
      )}
    </>
  );
}

/* ─── Row ────────────────────────────────────────────────────────────── */

function PostRow({
  post, catMap, onEdit, onView, onToggleStatus, onDelete,
}: {
  post: WpPost;
  catMap: Record<number, string>;
  onEdit: () => void;
  onView: () => void;
  onToggleStatus: () => void;
  onDelete: () => void;
}) {
  const isPublished = post.status === 'publish';
  return (
    <div className="grid grid-cols-[1fr_160px_120px_140px_auto] items-center px-5 py-3.5 hover:bg-[var(--color-surface-elevated)] transition-colors">
      <button
        onClick={onEdit}
        className="text-sm text-[var(--color-foreground)] font-medium text-left truncate hover:text-[var(--color-primary)] pr-4"
        dangerouslySetInnerHTML={{ __html: post.title.rendered || '(bez tytułu)' }}
      />
      <div className="flex flex-wrap gap-1 pr-2">
        {post.categories.slice(0, 2).map((id) => (
          <span
            key={id}
            className="text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)]"
          >
            {catMap[id] ?? '—'}
          </span>
        ))}
        {post.categories.length > 2 && (
          <span className="text-[10px] px-2 py-0.5 rounded-full bg-[var(--color-surface-elevated)] text-[var(--color-subtle)]">
            +{post.categories.length - 2}
          </span>
        )}
      </div>
      <button onClick={onToggleStatus} title="Kliknij aby zmienić status">
        <Badge variant={isPublished ? 'success' : 'warning'}>
          {isPublished ? 'opublikowany' : 'szkic'}
        </Badge>
      </button>
      <span className="text-xs text-[var(--color-muted-foreground)]">
        {new Date(post.modified).toLocaleDateString('pl-PL')}
      </span>
      <div className="flex items-center gap-1">
        <Button size="icon" variant="ghost" title="Edytuj w Divi" onClick={onEdit}>
          <Edit3 className="w-3.5 h-3.5" />
        </Button>
        <Button size="icon" variant="ghost" title="Otwórz na stronie" onClick={onView}>
          <ExternalLink className="w-3.5 h-3.5" />
        </Button>
        <Button size="icon" variant="ghost" title="Usuń" onClick={onDelete}>
          <Trash2 className="w-3.5 h-3.5 text-[var(--color-destructive)]" />
        </Button>
      </div>
    </div>
  );
}

/* ─── Modal ──────────────────────────────────────────────────────────── */

function NewPostModal({
  categories,
  onClose,
  onCreated,
}: {
  categories: WpCategory[];
  onClose: () => void;
  onCreated: (post: WpPost, openDivi: boolean) => void;
}) {
  const [title,       setTitle]       = useState('');
  const [excerpt,     setExcerpt]     = useState('');
  const [selCats,     setSelCats]     = useState<number[]>([]);
  const [status,      setStatus]      = useState<'draft' | 'publish'>('draft');
  const [error,       setError]       = useState<string | null>(null);

  const create = useMutation({
    mutationFn: (openDivi: boolean) =>
      api<{ id: number; link: string; editUrl: string }>('overcms/v1/blog/create', {
        method: 'POST',
        body: { title, excerpt, status, categories: selCats },
      }).then((res) => ({ res, openDivi })),
    onSuccess: ({ res, openDivi }) => {
      const post: WpPost = {
        id: res.id,
        link: res.link,
        title: { rendered: title },
        excerpt: { rendered: excerpt },
        status,
        categories: selCats,
        date: new Date().toISOString(),
        modified: new Date().toISOString(),
        slug: '',
      };
      onCreated(post, openDivi);
    },
    onError: (err) => {
      setError(err instanceof ApiError ? err.message : 'Błąd tworzenia wpisu');
    },
  });

  const toggleCat = (id: number) =>
    setSelCats((prev) => prev.includes(id) ? prev.filter((c) => c !== id) : [...prev, id]);

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4"
      onClick={onClose}
    >
      <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" />
      <div
        className="relative glass-card rounded-[var(--radius-lg)] w-full max-w-lg p-6 flex flex-col gap-5"
        onClick={(e) => e.stopPropagation()}
      >
        {/* Nagłówek */}
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-base font-semibold text-[var(--color-foreground)]">Nowy wpis</h2>
            <p className="text-xs text-[var(--color-muted-foreground)] mt-0.5">
              Po utworzeniu otworzysz Divi aby zaprojektować treść
            </p>
          </div>
          <button
            onClick={onClose}
            className="w-7 h-7 flex items-center justify-center rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] transition-colors"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Tytuł */}
        <label className="block">
          <span className="text-xs font-medium text-[var(--color-muted-foreground)] mb-1.5 block">
            Tytuł <span className="text-[var(--color-destructive)]">*</span>
          </span>
          <Input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="np. Jak zbudować świetną stronę internetową"
            autoFocus
          />
        </label>

        {/* Kategorie */}
        {categories.length > 0 && (
          <div>
            <span className="text-xs font-medium text-[var(--color-muted-foreground)] mb-2 block">
              Kategorie
            </span>
            <div className="flex flex-wrap gap-1.5">
              {categories.map((cat) => {
                const active = selCats.includes(cat.id);
                return (
                  <button
                    key={cat.id}
                    onClick={() => toggleCat(cat.id)}
                    className={`flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium transition-all ${
                      active
                        ? 'bg-[var(--color-primary)] text-white'
                        : 'bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]'
                    }`}
                  >
                    {active && <CheckCircle2 className="w-3 h-3" />}
                    {cat.name}
                    <span className="opacity-60">({cat.count})</span>
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* Zajawka */}
        <label className="block">
          <span className="text-xs font-medium text-[var(--color-muted-foreground)] mb-1.5 block">
            Zajawka <span className="text-[var(--color-subtle)]">(opcjonalnie)</span>
          </span>
          <textarea
            value={excerpt}
            onChange={(e) => setExcerpt(e.target.value)}
            placeholder="Krótki opis wpisu widoczny na listingu…"
            rows={2}
            className="w-full px-3 py-2 rounded-[var(--radius)] bg-[var(--color-surface-elevated)] border border-[var(--color-border)] text-sm text-[var(--color-foreground)] placeholder:text-[var(--color-subtle)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)] resize-none"
          />
        </label>

        {/* Status */}
        <div>
          <span className="text-xs font-medium text-[var(--color-muted-foreground)] mb-2 block">
            Status
          </span>
          <div className="flex rounded-[var(--radius)] overflow-hidden border border-[var(--color-border)] w-fit">
            {(['draft', 'publish'] as const).map((s) => (
              <button
                key={s}
                onClick={() => setStatus(s)}
                className={`px-4 py-1.5 text-xs font-medium transition-colors ${
                  status === s
                    ? 'bg-[var(--color-primary)] text-white'
                    : 'text-[var(--color-muted-foreground)] hover:bg-[var(--color-surface-elevated)]'
                }`}
              >
                {s === 'draft' ? 'Szkic' : 'Opublikowany'}
              </button>
            ))}
          </div>
        </div>

        {error && (
          <p className="text-xs text-[var(--color-destructive)] bg-[color-mix(in_srgb,var(--color-destructive)_10%,transparent)] px-3 py-2 rounded-[var(--radius)]">
            {error}
          </p>
        )}

        {/* Akcje */}
        <div className="flex items-center justify-end gap-2 pt-2 border-t border-[var(--color-border)]">
          <Button variant="ghost" onClick={onClose} disabled={create.isPending}>
            Anuluj
          </Button>
          <Button
            variant="outline"
            disabled={!title.trim() || create.isPending}
            onClick={() => create.mutate(false)}
          >
            {create.isPending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : null}
            Zapisz szkic
          </Button>
          <Button
            disabled={!title.trim() || create.isPending}
            onClick={() => create.mutate(true)}
          >
            {create.isPending ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Edit3 className="w-3.5 h-3.5" />}
            Utwórz i edytuj w Divi
          </Button>
        </div>
      </div>
    </div>
  );
}
