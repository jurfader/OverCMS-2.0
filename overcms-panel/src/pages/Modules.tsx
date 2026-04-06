import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Plus, Upload, CheckCircle2, AlertCircle, Loader2, Palette } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import type { ModulesResponse } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Card, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Switch } from '@/components/ui/Switch';
import { Badge } from '@/components/ui/Badge';

interface ThemeItem {
  slug: string;
  name: string;
  version: string;
  author: string;
  description: string;
  screenshot: string | null;
  active: boolean;
}

interface ThemesResponse {
  themes: ThemeItem[];
  activeSlug: string;
}

export function ModulesPage() {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const fileRef = useRef<HTMLInputElement>(null);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [uploadSuccess, setUploadSuccess] = useState<string | null>(null);
  const [showThemes, setShowThemes] = useState(false);

  const { data, isLoading } = useQuery({
    queryKey: ['modules'],
    queryFn: () => api<ModulesResponse>('overcms/v1/modules'),
  });

  const { data: themes } = useQuery({
    queryKey: ['themes'],
    queryFn: () => api<ThemesResponse>('overcms/v1/themes'),
  });

  const toggle = useMutation({
    mutationFn: ({ id, active }: { id: string; active: boolean }) =>
      api(`overcms/v1/modules/${id}/${active ? 'deactivate' : 'activate'}`, { method: 'POST' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['modules'] }),
  });

  const uploadTheme = useMutation({
    mutationFn: (file: File) => {
      const fd = new FormData();
      fd.append('file', file);
      return api<{ success: boolean; slug: string; name: string }>('overcms/v1/themes/upload', {
        method: 'POST',
        body: fd,
      });
    },
    onMutate: () => {
      setUploadError(null);
      setUploadSuccess(null);
    },
    onSuccess: (res) => {
      setUploadSuccess(`Wgrano motyw: ${res.name}`);
      qc.invalidateQueries({ queryKey: ['themes'] });
      setTimeout(() => setUploadSuccess(null), 6000);
    },
    onError: (err) => {
      setUploadError(err instanceof ApiError ? err.message : 'Błąd wgrywania motywu');
    },
  });

  const activateTheme = useMutation({
    mutationFn: (slug: string) =>
      api(`overcms/v1/themes/${slug}/activate`, { method: 'POST' }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['themes'] });
      qc.invalidateQueries({ queryKey: ['templates'] });
    },
  });

  return (
    <>
      <PageHeader
        title="Moduły"
        description="Zainstalowane pluginy i motywy. Aby dodać nowe pluginy, użyj Marketplace."
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              icon={<Upload />}
              onClick={() => fileRef.current?.click()}
              disabled={uploadTheme.isPending}
            >
              {uploadTheme.isPending ? 'Wgrywanie…' : 'Wgraj motyw (.zip)'}
            </Button>
            <Button icon={<Plus />} onClick={() => navigate('/marketplace')}>
              Otwórz Marketplace
            </Button>
          </div>
        }
      />

      <input
        ref={fileRef}
        type="file"
        accept=".zip"
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) uploadTheme.mutate(f);
          e.target.value = '';
        }}
      />

      {uploadError && (
        <div className="mb-4 flex items-start gap-3 px-4 py-3 rounded-[var(--radius)] bg-[color-mix(in_srgb,var(--color-destructive)_10%,transparent)] text-[var(--color-destructive)] border border-[color-mix(in_srgb,var(--color-destructive)_30%,transparent)]">
          <AlertCircle className="w-4 h-4 mt-0.5 flex-shrink-0" />
          <div className="text-xs flex-1 break-words">{uploadError}</div>
        </div>
      )}
      {uploadSuccess && (
        <div className="mb-4 flex items-start gap-3 px-4 py-3 rounded-[var(--radius)] bg-[color-mix(in_srgb,var(--color-success)_10%,transparent)] text-[var(--color-success)] border border-[color-mix(in_srgb,var(--color-success)_30%,transparent)]">
          <CheckCircle2 className="w-4 h-4 mt-0.5 flex-shrink-0" />
          <div className="text-xs flex-1">{uploadSuccess}</div>
        </div>
      )}

      {/* Toggle: Themes / Plugins */}
      <div className="flex items-center gap-1 mb-4 border-b border-[var(--color-border)]">
        <button
          onClick={() => setShowThemes(false)}
          className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px ${
            !showThemes
              ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
              : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]'
          }`}
        >
          Pluginy
        </button>
        <button
          onClick={() => setShowThemes(true)}
          className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px ${
            showThemes
              ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
              : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]'
          }`}
        >
          Motywy ({themes?.themes.length ?? 0})
        </button>
      </div>

      {showThemes ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {themes?.themes.map((t) => (
            <Card key={t.slug}>
              <div className="flex items-start gap-3 mb-3">
                <div className="w-16 h-16 rounded-[var(--radius)] bg-[var(--color-surface-elevated)] overflow-hidden flex items-center justify-center shrink-0">
                  {t.screenshot ? (
                    <img src={t.screenshot} alt={t.name} className="w-full h-full object-cover" />
                  ) : (
                    <Palette className="w-6 h-6 text-[var(--color-subtle)]" />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <h3 className="text-sm font-semibold text-[var(--color-foreground)] truncate">{t.name}</h3>
                    {t.active && <Badge variant="success">aktywny</Badge>}
                  </div>
                  <p className="text-[10px] text-[var(--color-subtle)]">v{t.version} · {t.author}</p>
                </div>
              </div>
              {!t.active && (
                <Button
                  size="sm"
                  variant="outline"
                  className="w-full"
                  onClick={() => activateTheme.mutate(t.slug)}
                  disabled={activateTheme.isPending}
                >
                  Aktywuj
                </Button>
              )}
            </Card>
          ))}
          {(!themes || themes.themes.length === 0) && (
            <p className="text-sm text-[var(--color-muted-foreground)]">Brak motywów.</p>
          )}
        </div>
      ) : (
        <div className="space-y-3">
          {isLoading && <p className="text-sm text-[var(--color-muted-foreground)]">Ładowanie…</p>}
          {data?.modules.map((m) => (
            <div key={m.id} className="glass-card rounded-[var(--radius-lg)] p-5 flex items-start gap-4">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <h3 className="text-sm font-semibold text-[var(--color-foreground)]">{m.name}</h3>
                  <Badge variant="outline">v{m.version}</Badge>
                  {m.active && <Badge variant="success">aktywny</Badge>}
                </div>
                <p
                  className="text-xs text-[var(--color-muted-foreground)] line-clamp-2"
                  dangerouslySetInnerHTML={{ __html: m.description }}
                />
                <p className="text-[10px] text-[var(--color-subtle)] mt-1">{m.author}</p>
              </div>
              <Switch
                checked={m.active}
                onChange={() => toggle.mutate({ id: m.id, active: m.active })}
                aria-label={`Aktywuj ${m.name}`}
              />
            </div>
          ))}
        </div>
      )}
    </>
  );
}
