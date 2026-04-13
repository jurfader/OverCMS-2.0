import { useState, useRef } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Plus, Upload, CheckCircle2, AlertCircle, Loader2, Palette, RefreshCw, Settings, ArrowUpCircle, X } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import type { ModulesResponse, ModuleItem } from '@/lib/types';
import { boot } from '@/lib/types';

function setEmbedCookie() {
  document.cookie = 'overcms_embed=1; path=/; SameSite=Lax';
}
function clearEmbedCookie() {
  document.cookie = 'overcms_embed=; path=/; max-age=0; SameSite=Lax';
}
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
  const [settingsUrl, setSettingsUrl] = useState<string | null>(null);
  const [updatingId, setUpdatingId] = useState<string | null>(null);
  const [updateResults, setUpdateResults] = useState<Record<string, { ok: boolean; msg: string }>>({});

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

  const checkUpdates = useMutation({
    mutationFn: () => api<{ checked: boolean; updates: { file: string; newVersion: string | null }[]; count: number }>(
      'overcms/v1/modules/check-updates',
      { method: 'POST' }
    ),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['modules'] }),
  });

  const updatePlugin = useMutation({
    mutationFn: ({ id }: { id: string }) =>
      api<{ success: boolean; newVersion: string | null }>(`overcms/v1/modules/${id}/update`, { method: 'POST' }),
    onMutate: ({ id }) => setUpdatingId(id),
    onSuccess: (res, { id }) => {
      setUpdatingId(null);
      setUpdateResults(prev => ({
        ...prev,
        [id]: { ok: true, msg: `Zaktualizowano do v${res.newVersion ?? '?'}` },
      }));
      qc.invalidateQueries({ queryKey: ['modules'] });
      setTimeout(() => setUpdateResults(prev => { const n = { ...prev }; delete n[id]; return n; }), 8000);
    },
    onError: (err, { id }) => {
      setUpdatingId(null);
      setUpdateResults(prev => ({
        ...prev,
        [id]: { ok: false, msg: err instanceof ApiError ? err.message : 'Błąd aktualizacji' },
      }));
      setTimeout(() => setUpdateResults(prev => { const n = { ...prev }; delete n[id]; return n; }), 8000);
    },
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

  const updateCount = data?.modules.filter(m => m.updateAvailable).length ?? 0;
  const adminUrl = boot.adminUrl.replace(/\/?$/, '/');

  return (
    <>
      <PageHeader
        title="Moduły"
        description="Zainstalowane pluginy i motywy."
        actions={
          <div className="flex items-center gap-2">
            {!showThemes && (
              <Button
                variant="outline"
                icon={checkUpdates.isPending ? <Loader2 className="animate-spin" /> : <RefreshCw />}
                onClick={() => checkUpdates.mutate()}
                disabled={checkUpdates.isPending}
              >
                {checkUpdates.isPending ? 'Sprawdzam…' : 'Sprawdź aktualizacje'}
              </Button>
            )}
            {showThemes && (
              <Button
                variant="outline"
                icon={<Upload />}
                onClick={() => fileRef.current?.click()}
                disabled={uploadTheme.isPending}
              >
                {uploadTheme.isPending ? 'Wgrywanie…' : 'Wgraj motyw (.zip)'}
              </Button>
            )}
            <Button icon={<Plus />} onClick={() => navigate('/marketplace')}>
              Marketplace
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

      {/* Tabs */}
      <div className="flex items-center gap-1 mb-4 border-b border-[var(--color-border)]">
        <button
          onClick={() => setShowThemes(false)}
          className={`px-4 py-2 text-sm font-medium transition-colors border-b-2 -mb-px flex items-center gap-2 ${
            !showThemes
              ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
              : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]'
          }`}
        >
          Pluginy
          {updateCount > 0 && (
            <span className="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold bg-[var(--color-warning)] text-black">
              {updateCount}
            </span>
          )}
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
            <PluginRow
              key={m.id}
              module={m}
              isUpdating={updatingId === m.id}
              result={updateResults[m.id] ?? null}
              onToggle={() => toggle.mutate({ id: m.id, active: m.active })}
              onUpdate={() => updatePlugin.mutate({ id: m.id })}
              onSettings={() => {
                if (m.settingsUrl) {
                  setEmbedCookie();
                  setSettingsUrl(m.settingsUrl);
                }
              }}
            />
          ))}
        </div>
      )}

      {/* Settings iframe overlay */}
      {settingsUrl && (
        <div className="fixed inset-0 z-50 flex flex-col bg-[var(--color-background)]">
          <div className="h-10 flex items-center justify-between px-4 border-b border-[var(--color-border)] bg-[var(--color-surface)] shrink-0">
            <span className="text-xs text-[var(--color-muted-foreground)]">
              {settingsUrl.split('?')[0].split('/').pop()}
            </span>
            <button
              onClick={() => { clearEmbedCookie(); setSettingsUrl(null); }}
              className="w-7 h-7 flex items-center justify-center rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] transition-colors"
              aria-label="Zamknij"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
          <iframe
            src={settingsUrl}
            className="flex-1 w-full border-0"
            title="Ustawienia pluginu"
          />
        </div>
      )}
    </>
  );
}

function PluginRow({
  module: m,
  isUpdating,
  result,
  onToggle,
  onUpdate,
  onSettings,
}: {
  module: ModuleItem;
  isUpdating: boolean;
  result: { ok: boolean; msg: string } | null;
  onToggle: () => void;
  onUpdate: () => void;
  onSettings: () => void;
}) {
  return (
    <div className="glass-card rounded-[var(--radius-lg)] p-5">
      <div className="flex items-start gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1 flex-wrap">
            <h3 className="text-sm font-semibold text-[var(--color-foreground)]">{m.name}</h3>
            <Badge variant="outline">v{m.version}</Badge>
            {m.active && <Badge variant="success">aktywny</Badge>}
            {m.updateAvailable && (
              <Badge variant="warning">aktualizacja v{m.newVersion}</Badge>
            )}
          </div>
          <p
            className="text-xs text-[var(--color-muted-foreground)] line-clamp-2"
            dangerouslySetInnerHTML={{ __html: m.description }}
          />
          <p className="text-[10px] text-[var(--color-subtle)] mt-1">{m.author}</p>

          {result && (
            <div className={`mt-2 flex items-center gap-1.5 text-xs ${result.ok ? 'text-[var(--color-success)]' : 'text-[var(--color-destructive)]'}`}>
              {result.ok
                ? <CheckCircle2 className="w-3.5 h-3.5 shrink-0" />
                : <AlertCircle className="w-3.5 h-3.5 shrink-0" />
              }
              {result.msg}
            </div>
          )}
        </div>

        <div className="flex items-center gap-2 shrink-0">
          {m.settingsUrl && (
            <button
              onClick={onSettings}
              title="Ustawienia"
              className="w-8 h-8 flex items-center justify-center rounded-[var(--radius)] text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)] hover:bg-[var(--color-surface-elevated)] transition-colors"
            >
              <Settings className="w-4 h-4" />
            </button>
          )}
          {m.updateAvailable && (
            <button
              onClick={onUpdate}
              disabled={isUpdating}
              title={`Zaktualizuj do v${m.newVersion}`}
              className="w-8 h-8 flex items-center justify-center rounded-[var(--radius)] text-[var(--color-warning)] hover:bg-[color-mix(in_srgb,var(--color-warning)_15%,transparent)] transition-colors disabled:opacity-50"
            >
              {isUpdating
                ? <Loader2 className="w-4 h-4 animate-spin" />
                : <ArrowUpCircle className="w-4 h-4" />
              }
            </button>
          )}
          <Switch
            checked={m.active}
            onChange={onToggle}
            aria-label={`Aktywuj ${m.name}`}
          />
        </div>
      </div>
    </div>
  );
}
