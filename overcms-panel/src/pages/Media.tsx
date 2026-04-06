import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Upload, Search } from 'lucide-react';
import { api } from '@/lib/api';
import type { MediaResponse } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export function MediaPage() {
  const qc = useQueryClient();
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const fileRef = useRef<HTMLInputElement>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['media', page, search],
    queryFn: () =>
      api<MediaResponse>('overcms/v1/media/summary', {
        query: { page, per_page: 30, search },
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

  const onDrop = (e: React.DragEvent) => {
    e.preventDefault();
    if (e.dataTransfer.files.length) {
      upload.mutate(e.dataTransfer.files);
    }
  };

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
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
              {data.items.map((item) => (
                <div
                  key={item.id}
                  className="group relative aspect-square rounded-[var(--radius)] overflow-hidden bg-[var(--color-surface-elevated)] border border-[var(--color-border)] hover:border-[var(--color-primary)] transition-colors"
                >
                  {item.thumb && item.mime.startsWith('image/') ? (
                    <img src={item.thumb} alt={item.title} className="w-full h-full object-cover" />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-[10px] text-[var(--color-muted-foreground)] p-2 text-center">
                      {item.mime}
                    </div>
                  )}
                  <div className="absolute inset-x-0 bottom-0 p-2 bg-gradient-to-t from-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity">
                    <p className="text-[10px] text-white truncate">{item.title}</p>
                  </div>
                </div>
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
    </>
  );
}
