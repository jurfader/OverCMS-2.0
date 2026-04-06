import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Save, CheckCircle2 } from 'lucide-react';
import { api } from '@/lib/api';
import type { SiteSettings } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Card, CardHeader } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';

export function SettingsPage() {
  const qc = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['site'],
    queryFn: () => api<SiteSettings>('overcms/v1/site'),
  });

  const [form, setForm] = useState<Partial<SiteSettings>>({});
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    if (data) setForm(data);
  }, [data]);

  const save = useMutation({
    mutationFn: (payload: Partial<SiteSettings>) =>
      api<SiteSettings>('overcms/v1/site', { method: 'POST', body: payload }),
    onSuccess: (next) => {
      qc.setQueryData(['site'], next);
      setSaved(true);
      setTimeout(() => setSaved(false), 2500);
    },
  });

  if (isLoading || !data) {
    return (
      <>
        <PageHeader title="Ustawienia" />
        <p className="text-sm text-[var(--color-muted-foreground)]">Ładowanie…</p>
      </>
    );
  }

  return (
    <>
      <PageHeader
        title="Ustawienia"
        description="Konfiguracja witryny i panelu."
        actions={
          <Button
            icon={<Save />}
            onClick={() => save.mutate(form)}
            disabled={save.isPending}
          >
            {save.isPending ? 'Zapisywanie…' : 'Zapisz'}
          </Button>
        }
      />

      {saved && (
        <div className="rounded-[var(--radius)] bg-[color-mix(in_srgb,var(--color-success)_10%,transparent)] text-[var(--color-success)] px-4 py-3 flex items-center gap-2.5 mb-4">
          <CheckCircle2 className="w-4 h-4" />
          Ustawienia zapisane.
        </div>
      )}

      <div className="space-y-4 max-w-2xl">
        <Card>
          <CardHeader title="Tożsamość witryny" />
          <div className="space-y-3">
            <Field label="Nazwa witryny">
              <Input value={form.title ?? ''} onChange={(e) => setForm({ ...form, title: e.target.value })} />
            </Field>
            <Field label="Opis (tagline)">
              <Input value={form.description ?? ''} onChange={(e) => setForm({ ...form, description: e.target.value })} />
            </Field>
          </div>
        </Card>

        <Card>
          <CardHeader title="Lokalizacja" />
          <div className="grid grid-cols-2 gap-3">
            <Field label="Język">
              <Input value={form.language ?? ''} onChange={(e) => setForm({ ...form, language: e.target.value })} placeholder="pl_PL" />
            </Field>
            <Field label="Strefa czasowa">
              <Input value={form.timezone ?? ''} onChange={(e) => setForm({ ...form, timezone: e.target.value })} placeholder="Europe/Warsaw" />
            </Field>
          </div>
        </Card>

        <Card>
          <CardHeader title="Środowisko" />
          <dl className="space-y-2 text-sm">
            <Row label="WordPress" value={data.wpVersion} />
            <Row label="PHP" value={data.phpVersion} />
            <Row label="Permalinks" value={data.permalinks || '(domyślne)'} />
            <Row label="Email administratora" value={data.adminEmail} />
          </dl>
        </Card>
      </div>
    </>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="text-xs font-medium text-[var(--color-muted-foreground)] mb-1.5 block">{label}</span>
      {children}
    </label>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-3 py-1.5 border-b border-[var(--color-border)] last:border-0">
      <dt className="text-[var(--color-muted-foreground)]">{label}</dt>
      <dd className="text-[var(--color-foreground)] font-mono text-xs">{value}</dd>
    </div>
  );
}
