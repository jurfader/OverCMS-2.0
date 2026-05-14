import { useState, useRef, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Upload, Search, X, Trash2, Copy, Check, ExternalLink, ImageIcon } from 'lucide-react';
import { api } from '@/lib/api';
import type { MediaItem, MediaResponse } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

const fmtBytes = (b: number | null) => {
  if (!b) return '—';
  if (b < 1024) return `${b} B`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / 1024 / 1024).toFixed(1)} MB`;
};

export function MediaPage() {
  const qc = useQueryClient();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [preview, setPreview] = useState<MediaItem | null>(null);
  const [copied, setCopied] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['media', page, search],
    queryFn: () =>
      api<MediaResponse>('overcms/v1/media/summary', {
        query: { page, per_page: 24, search },
      }),
  });

  const upload = useMutation({
    mutationFn: async (files: FileList) => {
      const results = [];
      for (const file of Array.from(files)) {
        const fd = new FormData();
        fd.append('file', file);
        results.push(await api('wp/v2/media', { method: 'POST', body: fd }));
      }
      return results;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['media'] }),
  });

  const remove = useMutation({
    mutationFn: (id: number) => api(`wp/v2/media/${id}?force=true`, { method: 'DELETE' }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['media'] });
      setPreview(null);
    },
  });

  const onDrop = (e: React.DragEvent) => {
    e.preventDefault();
    if (e.dataTransfer.files.length) {
      upload.mutate(e.dataTransfer.files);
    }
  };

  const copyUrl = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // ignore
    }
  };

  // Close modal on Esc
  useEffect(() => {
    if (!preview) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setPreview(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [preview]);

  return (
    <>
      <PageHeader
        title="Media"
        description="Biblioteka plików — przeciągnij i upuść lub kliknij Wgraj."
        actions={
          <Button icon={<Upload />} onClick={() => fileRef.current?.click()} disabled={upload.isPending}>
            {upload.isPending ? 'Wgrywanie…' : 'Wgraj pliki'}
          </Button>
        }
      />

      <input
        ref={fileRef}
        type="file"
        multiple
        className="hidden"
        onChange={(e) => e.target.files && upload.mutate(e.target.files)}
      />

      <div className="mb-4 relative max-w-sm">
        <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-subtle)]" />
        <Input
          placeholder="Szukaj plików…"
          className="pl-9"
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setPage(1);
          }}
        />
      </div>

      <div
        onDragOver={(e) => e.preventDefault()}
        onDrop={onDrop}
        className="glass-card rounded-[var(--radius-lg)] p-4 min-h-[400px]"
      >
        {isLoading && <p className="text-sm text-[var(--color-muted-foreground)]">Ładowanie…</p>}
        {data && (
          <>
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
              {data.items.map((item) => (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setPreview(item)}
                  className="group relative aspect-square rounded-[var(--radius)] overflow-hidden bg-[var(--color-surface-elevated)] border border-[var(--color-border)] hover:border-[var(--color-primary)] transition-colors cursor-zoom-in text-left"
                  title={item.title}
                >
                  {item.thumb && item.mime.startsWith('image/') ? (
                    <img
                      src={item.thumb}
                      alt={item.title}
                      loading="lazy"
                      className="w-full h-full object-contain p-2"
                    />
                  ) : (
                    <div className="w-full h-full flex flex-col items-center justify-center gap-2 text-[10px] text-[var(--color-muted-foreground)] p-2 text-center">
                      <ImageIcon className="w-8 h-8 opacity-40" />
                      <span className="break-all">{item.mime}</span>
                    </div>
                  )}
                  <div className="absolute inset-x-0 bottom-0 px-2 py-1.5 bg-gradient-to-t from-black/90 via-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                    <p className="text-[11px] text-white truncate">{item.title}</p>
                    {item.width && item.height && (
                      <p className="text-[10px] text-white/70">{item.width}×{item.height} · {fmtBytes(item.sizeBytes)}</p>
                    )}
                  </div>
                </button>
              ))}
            </div>

            {data.totalPages > 1 && (
              <div className="flex items-center justify-center gap-2 mt-6">
                <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  Poprzednia
                </Button>
                <span className="text-xs text-[var(--color-muted-foreground)]">
                  {page} / {data.totalPages}
                </span>
                <Button size="sm" variant="outline" disabled={page >= data.totalPages} onClick={() => setPage((p) => p + 1)}>
                  Następna
                </Button>
              </div>
            )}

            {data.items.length === 0 && (
              <p className="text-center text-sm text-[var(--color-muted-foreground)] py-12">
                Brak plików. Przeciągnij tu pliki lub kliknij Wgraj.
              </p>
            )}
          </>
        )}
      </div>

      {/* Preview modal */}
      {preview && (
        <div
          className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
          onClick={() => setPreview(null)}
        >
          <div
            className="glass-card relative max-w-5xl w-full max-h-[90vh] rounded-[var(--radius-lg)] overflow-hidden flex flex-col"
            onClick={(e) => e.stopPropagation()}
          >
            {/* Header */}
            <div className="flex items-center justify-between gap-4 px-5 py-3 border-b border-[var(--color-border)] shrink-0">
              <div className="min-w-0">
                <p className="text-sm font-semibold truncate">{preview.title}</p>
                <p className="text-xs text-[var(--color-muted-foreground)]">
                  {preview.mime}
                  {preview.width && preview.height && ` · ${preview.width}×${preview.height}`}
                  {` · ${fmtBytes(preview.sizeBytes)}`}
                </p>
              </div>
              <button
                onClick={() => setPreview(null)}
                className="w-8 h-8 rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] flex items-center justify-center shrink-0"
                title="Zamknij (Esc)"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Image */}
            <div className="flex-1 overflow-auto flex items-center justify-center bg-[var(--color-surface-elevated)] p-4 min-h-[300px]">
              {preview.mime.startsWith('image/') ? (
                <img
                  src={preview.url}
                  alt={preview.title}
                  className="max-w-full max-h-[60vh] object-contain"
                />
              ) : (
                <div className="text-sm text-[var(--color-muted-foreground)] text-center py-12">
                  <ImageIcon className="w-12 h-12 mx-auto mb-3 opacity-40" />
                  <p>{preview.mime}</p>
                  <a href={preview.url} target="_blank" rel="noreferrer" className="text-[var(--color-primary)] hover:underline mt-2 inline-flex items-center gap-1">
                    Otwórz plik <ExternalLink className="w-3 h-3" />
                  </a>
                </div>
              )}
            </div>

            {/* Footer with URL + actions */}
            <div className="border-t border-[var(--color-border)] p-3 shrink-0 space-y-2">
              <div className="flex items-center gap-2">
                <Input value={preview.url} readOnly className="text-xs flex-1" onFocus={(e) => e.currentTarget.select()} />
                <Button
                  size="sm"
                  variant="outline"
                  icon={copied ? <Check className="w-4 h-4 text-[var(--color-primary)]" /> : <Copy />}
                  onClick={() => copyUrl(preview.url)}
                >
                  {copied ? 'Skopiowano' : 'Kopiuj URL'}
                </Button>
              </div>
              <div className="flex items-center justify-between">
                <a
                  href={preview.url}
                  target="_blank"
                  rel="noreferrer"
                  className="text-xs text-[var(--color-muted-foreground)] hover:text-[var(--color-primary)] inline-flex items-center gap-1"
                >
                  Otwórz w nowej karcie <ExternalLink className="w-3 h-3" />
                </a>
                <Button
                  size="sm"
                  variant="outline"
                  icon={<Trash2 />}
                  onClick={() => {
                    if (confirm(`Na pewno usunąć „${preview.title}"? Tej operacji nie można cofnąć.`)) {
                      remove.mutate(preview.id);
                    }
                  }}
                  disabled={remove.isPending}
                >
                  {remove.isPending ? 'Usuwanie…' : 'Usuń'}
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
