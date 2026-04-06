import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Database, Download, Trash2, Loader2, CheckCircle2, AlertCircle, HardDrive } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { boot, type BackupsResponse } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Card, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';

export function BackupsPage() {
  const qc = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['backups'],
    queryFn: () => api<BackupsResponse>('overcms/v1/backups'),
  });

  const create = useMutation({
    mutationFn: () => api<{ filename: string; sizeMb: number }>('overcms/v1/backups', { method: 'POST' }),
    onMutate: () => {
      setError(null);
      setSuccess(null);
    },
    onSuccess: (res) => {
      setSuccess(`Utworzono backup: ${res.filename} (${res.sizeMb} MB)`);
      qc.invalidateQueries({ queryKey: ['backups'] });
      setTimeout(() => setSuccess(null), 6000);
    },
    onError: (err) => {
      setError(err instanceof ApiError ? err.message : 'Błąd tworzenia backupu');
    },
  });

  const remove = useMutation({
    mutationFn: (filename: string) =>
      api(`overcms/v1/backups/${encodeURIComponent(filename)}`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['backups'] }),
  });

  const download = (filename: string) => {
    // REST endpoint streamuje plik z nagłówkiem nonce
    const url = `${boot.restRoot.replace(/\/$/, '')}/overcms/v1/backups/${encodeURIComponent(
      filename,
    )}/download?_wpnonce=${boot.restNonce}`;
    window.location.href = url;
  };

  return (
    <>
      <PageHeader
        title="Backupy"
        description="Tworzenie, pobieranie i przywracanie kopii zapasowych witryny"
        actions={
          <Button
            icon={create.isPending ? <Loader2 className="animate-spin" /> : <Database />}
            onClick={() => create.mutate()}
            disabled={create.isPending}
          >
            {create.isPending ? 'Tworzę…' : 'Utwórz backup'}
          </Button>
        }
      />

      {error && (
        <div className="mb-4 flex items-start gap-3 px-4 py-3 rounded-[var(--radius)] bg-[color-mix(in_srgb,var(--color-destructive)_10%,transparent)] text-[var(--color-destructive)] border border-[color-mix(in_srgb,var(--color-destructive)_30%,transparent)]">
          <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
          <div className="text-xs flex-1 break-words">{error}</div>
        </div>
      )}
      {success && (
        <div className="mb-4 flex items-start gap-3 px-4 py-3 rounded-[var(--radius)] bg-[color-mix(in_srgb,var(--color-success)_10%,transparent)] text-[var(--color-success)] border border-[color-mix(in_srgb,var(--color-success)_30%,transparent)]">
          <CheckCircle2 className="w-4 h-4 mt-0.5 flex-shrink-0" />
          <div className="text-xs flex-1">{success}</div>
        </div>
      )}

      {isLoading && <p className="text-sm text-[var(--color-muted-foreground)]">Ładowanie…</p>}

      {data && (
        <>
          <Card className="mb-4">
            <div className="flex items-center gap-3">
              <span className="w-10 h-10 rounded-full bg-[var(--color-primary-muted)] text-[var(--color-primary)] flex items-center justify-center">
                <HardDrive className="w-5 h-5" />
              </span>
              <div>
                <p className="text-sm font-semibold text-[var(--color-foreground)]">
                  {data.items.length} {data.items.length === 1 ? 'kopia' : 'kopii'}
                </p>
                <p className="text-xs text-[var(--color-muted-foreground)]">
                  Łącznie: {data.totalSizeMb} MB
                </p>
              </div>
            </div>
          </Card>

          {data.items.length === 0 ? (
            <Card>
              <p className="text-sm text-[var(--color-muted-foreground)] text-center py-4">
                Brak backupów. Utwórz pierwszy klikając przycisk powyżej.
              </p>
            </Card>
          ) : (
            <div className="glass-card rounded-[var(--radius-lg)] overflow-hidden">
              <div className="grid grid-cols-[1fr_120px_180px_120px] px-5 py-2.5 border-b border-[var(--color-border)] bg-[var(--color-surface-elevated)]">
                <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Plik</span>
                <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Rozmiar</span>
                <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Utworzono</span>
                <span />
              </div>
              <div className="divide-y divide-[var(--color-border)]">
                {data.items.map((b) => (
                  <div
                    key={b.filename}
                    className="grid grid-cols-[1fr_120px_180px_120px] items-center px-5 py-3 hover:bg-[var(--color-surface-elevated)] transition-colors"
                  >
                    <span className="text-xs font-mono text-[var(--color-foreground)] truncate">{b.filename}</span>
                    <span className="text-xs text-[var(--color-muted-foreground)]">{b.sizeMb} MB</span>
                    <span className="text-xs text-[var(--color-muted-foreground)]">
                      {new Date(b.createdAt).toLocaleString('pl-PL')}
                    </span>
                    <div className="flex items-center justify-end gap-1">
                      <Button size="icon" variant="ghost" title="Pobierz" onClick={() => download(b.filename)}>
                        <Download className="w-3.5 h-3.5" />
                      </Button>
                      <Button
                        size="icon"
                        variant="ghost"
                        title="Usuń"
                        onClick={() => {
                          if (confirm(`Usunąć backup ${b.filename}?`)) remove.mutate(b.filename);
                        }}
                      >
                        <Trash2 className="w-3.5 h-3.5 text-[var(--color-destructive)]" />
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </>
      )}
    </>
  );
}
