import { useState } from 'react';
import { useQuery, useMutation } from '@tanstack/react-query';
import { Layers, AlertTriangle, Plus, X, LayoutTemplate, BookMarked, Sliders } from 'lucide-react';
import { api } from '@/lib/api';
import type { TemplatesResponse, DiviTemplate } from '@/lib/types';
import { boot } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Card, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export function TemplatesPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['templates'],
    queryFn: () => api<TemplatesResponse>('overcms/v1/templates'),
  });

  const [selected, setSelected] = useState<DiviTemplate | null>(null);
  const [newTitle, setNewTitle] = useState('');
  const [embedUrl, setEmbedUrl] = useState<string | null>(null);

  const useTemplate = useMutation({
    mutationFn: ({ templateId, title }: { templateId: number; title: string }) =>
      api<{ success: boolean; pageId: number; editUrl: string }>('overcms/v1/templates/use', {
        method: 'POST',
        body: { templateId, title },
      }),
    onSuccess: (res) => {
      setSelected(null);
      setNewTitle('');
      window.open(res.editUrl, '_blank');
    },
  });

  const adminUrl = boot.adminUrl.replace(/\/?$/, '/');

  return (
    <>
      <PageHeader
        title="Szablony"
        description="Narzędzia projektowe Divi i biblioteka layoutów."
      />

      {/* Narzędzia projektowe Divi */}
      <div className="mb-6">
        <p className="text-xs uppercase tracking-widest text-[var(--color-subtle)] mb-3">Narzędzia projektowe</p>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <DesignToolCard
            icon={<LayoutTemplate className="w-4 h-4 text-white" />}
            iconClass="gradient-bg"
            title="Theme Builder"
            description="Globalne szablony stron — nagłówek, stopka, body"
            onClick={() => setEmbedUrl(`${adminUrl}admin.php?page=et_theme_builder&overcms_embed=1`)}
          />
          <DesignToolCard
            icon={<BookMarked className="w-4 h-4 text-[var(--color-primary)]" />}
            title="Biblioteka Divi"
            description="Zapisane sekcje, wiersze i layouty"
            onClick={() => setEmbedUrl(`${adminUrl}admin.php?page=et_pb_layouts&overcms_embed=1`)}
          />
          <DesignToolCard
            icon={<Sliders className="w-4 h-4 text-[var(--color-primary)]" />}
            title="Customizer"
            description="Globalne style, kolory i czcionki motywu"
            onClick={() => setEmbedUrl(`${adminUrl}customize.php`)}
          />
        </div>
      </div>

      {/* Biblioteka layoutów */}
      <p className="text-xs uppercase tracking-widest text-[var(--color-subtle)] mb-3">Biblioteka layoutów</p>

      {isLoading && <p className="text-sm text-[var(--color-muted-foreground)]">Ładowanie…</p>}

      {data && !data.diviActive && (
        <Card>
          <div className="flex items-start gap-3">
            <span className="w-10 h-10 rounded-full bg-[color-mix(in_srgb,var(--color-warning)_15%,transparent)] text-[var(--color-warning)] flex items-center justify-center shrink-0">
              <AlertTriangle className="w-5 h-5" />
            </span>
            <div className="flex-1">
              <p className="text-sm font-semibold text-[var(--color-foreground)]">
                Motyw Divi nie jest aktywny
              </p>
              <p className="text-xs text-[var(--color-muted-foreground)] mt-1">
                {data.message ?? 'Aby korzystać z szablonów, zainstaluj i aktywuj motyw Divi (Elegant Themes).'}
              </p>
            </div>
          </div>
        </Card>
      )}

      {data?.diviActive && (
        <>
          {data.templates.length === 0 ? (
            <Card>
              <div className="text-center py-8">
                <Layers className="w-12 h-12 text-[var(--color-subtle)] mx-auto mb-3" />
                <p className="text-sm font-medium text-[var(--color-foreground)]">Brak szablonów</p>
                <p className="text-xs text-[var(--color-muted-foreground)] mt-1 max-w-md mx-auto">
                  Utwórz pierwszy szablon w bibliotece Divi (Divi → Divi Library) lub
                  zaimportuj predefiniowane układy z Divi Cloud.
                </p>
              </div>
            </Card>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {data.templates.map((tpl) => (
                <TemplateCard key={tpl.id} template={tpl} onUse={() => setSelected(tpl)} />
              ))}
            </div>
          )}
        </>
      )}

      {/* Iframe overlay — Divi tools bez WP chrome */}
      {embedUrl && (
        <div className="fixed inset-0 z-50 flex flex-col bg-[var(--color-background)]">
          <div className="h-10 flex items-center justify-between px-4 border-b border-[var(--color-border)] bg-[var(--color-surface)] shrink-0">
            <span className="text-xs text-[var(--color-muted-foreground)]">{embedUrl.split('?')[0].split('/').pop()}</span>
            <button
              onClick={() => setEmbedUrl(null)}
              className="w-7 h-7 flex items-center justify-center rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] transition-colors"
              aria-label="Zamknij"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
          <iframe
            src={embedUrl}
            className="flex-1 w-full border-0"
            title="Divi"
          />
        </div>
      )}

      {/* Modal "Use template" */}
      {selected && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4"
          onClick={() => setSelected(null)}
        >
          <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" />
          <div
            className="relative glass-card rounded-[var(--radius-lg)] p-6 w-full max-w-md"
            onClick={(e) => e.stopPropagation()}
          >
            <CardHeader
              title="Utwórz stronę z szablonu"
              description={`Szablon: ${selected.title}`}
            />
            <div className="space-y-3">
              <label className="block">
                <span className="text-xs font-medium text-[var(--color-muted-foreground)] mb-1.5 block">
                  Tytuł nowej strony
                </span>
                <Input
                  value={newTitle}
                  onChange={(e) => setNewTitle(e.target.value)}
                  placeholder="np. O nas"
                  autoFocus
                />
              </label>
              <p className="text-[10px] text-[var(--color-subtle)]">
                Po utworzeniu strona otworzy się w Divi visual builder w nowej karcie.
              </p>
            </div>
            <div className="flex items-center justify-end gap-2 mt-5 pt-4 border-t border-[var(--color-border)]">
              <Button variant="ghost" onClick={() => setSelected(null)}>
                Anuluj
              </Button>
              <Button
                disabled={!newTitle.trim() || useTemplate.isPending}
                onClick={() =>
                  useTemplate.mutate({ templateId: selected.id, title: newTitle.trim() })
                }
              >
                {useTemplate.isPending ? 'Tworzę…' : 'Utwórz stronę'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function DesignToolCard({
  icon, iconClass, title, description, onClick,
}: {
  icon: React.ReactNode;
  iconClass?: string;
  title: string;
  description: string;
  onClick: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className="glass-card rounded-[var(--radius-lg)] p-4 flex items-start gap-3 hover:bg-[var(--color-surface-elevated)] transition-colors text-left w-full group"
    >
      <span className={`w-9 h-9 rounded-[var(--radius)] ${iconClass ?? 'bg-[var(--color-surface-elevated)]'} flex items-center justify-center shrink-0`}>
        {icon}
      </span>
      <div className="flex-1 min-w-0">
        <p className="text-sm font-semibold text-[var(--color-foreground)]">{title}</p>
        <p className="text-[11px] text-[var(--color-muted-foreground)] mt-0.5">{description}</p>
      </div>
    </button>
  );
}

function TemplateCard({ template, onUse }: { template: DiviTemplate; onUse: () => void }) {
  return (
    <div className="glass-card rounded-[var(--radius-lg)] overflow-hidden flex flex-col">
      <div className="aspect-video bg-[var(--color-surface-elevated)] flex items-center justify-center overflow-hidden">
        {template.thumb ? (
          <img src={template.thumb} alt={template.title} className="w-full h-full object-cover" />
        ) : (
          <Layers className="w-10 h-10 text-[var(--color-subtle)]" />
        )}
      </div>
      <div className="p-4 flex flex-col gap-2 flex-1">
        <h3 className="text-sm font-semibold text-[var(--color-foreground)] line-clamp-1">
          {template.title}
        </h3>
        <p className="text-[10px] text-[var(--color-subtle)]">
          {template.type} · {new Date(template.modifiedAt + 'Z').toLocaleDateString('pl-PL')}
        </p>
        <Button size="sm" icon={<Plus />} onClick={onUse} className="mt-auto">
          Użyj szablonu
        </Button>
      </div>
    </div>
  );
}
