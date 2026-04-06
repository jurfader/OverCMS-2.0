import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Plus,
  Trash2,
  ArrowUp,
  ArrowDown,
  ExternalLink,
  Compass,
  FileText,
  Newspaper,
  Tag,
  Link as LinkIcon,
} from 'lucide-react';
import { api } from '@/lib/api';
import type { NavMenu, MenuDetail, MenuSources, NavMenuItem } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Card, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { cn } from '@/lib/cn';

export function NavigationPage() {
  const qc = useQueryClient();
  const [activeMenuId, setActiveMenuId] = useState<number | null>(null);
  const [newMenuName, setNewMenuName] = useState('');
  const [showCreate, setShowCreate] = useState(false);

  const { data: menusData } = useQuery({
    queryKey: ['nav-menus'],
    queryFn: () => api<{ menus: NavMenu[] }>('overcms/v1/menus'),
  });

  const menus = menusData?.menus ?? [];

  // Auto-select first menu
  useEffect(() => {
    if (activeMenuId === null && menus.length > 0) {
      setActiveMenuId(menus[0].id);
    }
  }, [menus, activeMenuId]);

  const { data: detail } = useQuery({
    queryKey: ['nav-menu', activeMenuId],
    queryFn: () => api<MenuDetail>(`overcms/v1/menus/${activeMenuId}`),
    enabled: activeMenuId !== null,
  });

  const { data: sources } = useQuery({
    queryKey: ['nav-sources'],
    queryFn: () => api<MenuSources>('overcms/v1/menus/sources'),
  });

  const createMenu = useMutation({
    mutationFn: (name: string) =>
      api<{ id: number }>('overcms/v1/menus', { method: 'POST', body: { name } }),
    onSuccess: (res) => {
      qc.invalidateQueries({ queryKey: ['nav-menus'] });
      setActiveMenuId(res.id);
      setNewMenuName('');
      setShowCreate(false);
    },
  });

  const deleteMenu = useMutation({
    mutationFn: (id: number) => api(`overcms/v1/menus/${id}`, { method: 'DELETE' }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['nav-menus'] });
      setActiveMenuId(null);
    },
  });

  const addItem = useMutation({
    mutationFn: (payload: { type: string; objectId?: number; url?: string; title: string }) =>
      api(`overcms/v1/menus/${activeMenuId}/items`, { method: 'POST', body: payload }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['nav-menu', activeMenuId] }),
  });

  const deleteItem = useMutation({
    mutationFn: (itemId: number) =>
      api(`overcms/v1/menus/${activeMenuId}/items/${itemId}`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['nav-menu', activeMenuId] }),
  });

  const reorder = useMutation({
    mutationFn: (order: number[]) =>
      api(`overcms/v1/menus/${activeMenuId}/items/reorder`, { method: 'POST', body: { order } }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['nav-menu', activeMenuId] }),
  });

  const moveItem = (idx: number, dir: -1 | 1) => {
    if (!detail) return;
    const items = [...detail.items];
    const target = idx + dir;
    if (target < 0 || target >= items.length) return;
    [items[idx], items[target]] = [items[target], items[idx]];
    reorder.mutate(items.map((i) => i.id));
  };

  return (
    <>
      <PageHeader
        title="Nawigacja"
        description="Zarządzaj menu witryny — dodawaj strony, wpisy, kategorie i linki zewnętrzne."
        actions={
          <Button icon={<Plus />} onClick={() => setShowCreate(true)}>
            Nowe menu
          </Button>
        }
      />

      {menus.length === 0 ? (
        <Card>
          <div className="text-center py-8">
            <Compass className="w-12 h-12 text-[var(--color-subtle)] mx-auto mb-3" />
            <p className="text-sm font-medium text-[var(--color-foreground)]">Brak menu</p>
            <p className="text-xs text-[var(--color-muted-foreground)] mt-1">
              Utwórz pierwsze menu klikając przycisk powyżej.
            </p>
          </div>
        </Card>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
          {/* Lewy panel — lista menu */}
          <Card className="p-3">
            <div className="space-y-1">
              {menus.map((m) => (
                <button
                  key={m.id}
                  onClick={() => setActiveMenuId(m.id)}
                  className={cn(
                    'w-full text-left flex items-center justify-between px-3 py-2 rounded-[var(--radius)] text-sm transition-colors',
                    activeMenuId === m.id
                      ? 'bg-[var(--color-primary-muted)] text-[var(--color-primary)]'
                      : 'text-[var(--color-foreground)] hover:bg-[var(--color-surface-elevated)]',
                  )}
                >
                  <span className="truncate">{m.name}</span>
                  <span className="text-[10px] text-[var(--color-subtle)]">{m.count}</span>
                </button>
              ))}
            </div>
          </Card>

          {/* Prawy panel — items wybranego menu */}
          <div className="space-y-4">
            {detail ? (
              <>
                <Card>
                  <CardHeader
                    title={detail.name}
                    description={`${detail.items.length} pozycji`}
                    actions={
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          if (confirm(`Usunąć menu „${detail.name}"?`)) deleteMenu.mutate(detail.id);
                        }}
                      >
                        <Trash2 className="w-3.5 h-3.5 mr-1" /> Usuń menu
                      </Button>
                    }
                  />

                  {detail.items.length === 0 ? (
                    <p className="text-xs text-[var(--color-muted-foreground)] py-4">
                      Menu jest puste. Dodaj pozycje poniżej.
                    </p>
                  ) : (
                    <div className="space-y-1">
                      {detail.items.map((item, idx) => (
                        <MenuItemRow
                          key={item.id}
                          item={item}
                          onUp={idx > 0 ? () => moveItem(idx, -1) : undefined}
                          onDown={idx < detail.items.length - 1 ? () => moveItem(idx, 1) : undefined}
                          onDelete={() => deleteItem.mutate(item.id)}
                        />
                      ))}
                    </div>
                  )}
                </Card>

                <Card>
                  <CardHeader title="Dodaj pozycję" />
                  <AddItemPanel
                    sources={sources}
                    onAdd={(payload) => addItem.mutate(payload)}
                    pending={addItem.isPending}
                  />
                </Card>
              </>
            ) : (
              <p className="text-sm text-[var(--color-muted-foreground)]">Wybierz menu z listy.</p>
            )}
          </div>
        </div>
      )}

      {/* Modal Nowe menu */}
      {showCreate && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => setShowCreate(false)}>
          <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" />
          <div className="relative glass-card rounded-[var(--radius-lg)] p-6 w-full max-w-sm" onClick={(e) => e.stopPropagation()}>
            <CardHeader title="Nowe menu" />
            <Input
              placeholder="np. Menu główne"
              value={newMenuName}
              onChange={(e) => setNewMenuName(e.target.value)}
              autoFocus
            />
            <div className="flex items-center justify-end gap-2 mt-4">
              <Button variant="ghost" onClick={() => setShowCreate(false)}>
                Anuluj
              </Button>
              <Button
                disabled={!newMenuName.trim() || createMenu.isPending}
                onClick={() => createMenu.mutate(newMenuName.trim())}
              >
                Utwórz
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

interface MenuItemRowProps {
  item: NavMenuItem;
  onUp?: () => void;
  onDown?: () => void;
  onDelete: () => void;
}

function MenuItemRow({ item, onUp, onDown, onDelete }: MenuItemRowProps) {
  return (
    <div className="flex items-center gap-2 px-3 py-2.5 rounded-[var(--radius)] bg-[var(--color-surface-elevated)] border border-[var(--color-border)]">
      <Badge variant="outline">{item.object || item.type}</Badge>
      <span className="text-sm text-[var(--color-foreground)] flex-1 truncate">{item.title}</span>
      <a
        href={item.url}
        target="_blank"
        rel="noreferrer"
        className="text-[var(--color-muted-foreground)] hover:text-[var(--color-primary)]"
      >
        <ExternalLink className="w-3.5 h-3.5" />
      </a>
      <Button size="icon" variant="ghost" onClick={onUp} disabled={!onUp}>
        <ArrowUp className="w-3.5 h-3.5" />
      </Button>
      <Button size="icon" variant="ghost" onClick={onDown} disabled={!onDown}>
        <ArrowDown className="w-3.5 h-3.5" />
      </Button>
      <Button size="icon" variant="ghost" onClick={onDelete}>
        <Trash2 className="w-3.5 h-3.5 text-[var(--color-destructive)]" />
      </Button>
    </div>
  );
}

interface AddItemPanelProps {
  sources?: MenuSources;
  onAdd: (payload: { type: string; objectId?: number; url?: string; title: string }) => void;
  pending: boolean;
}

function AddItemPanel({ sources, onAdd, pending }: AddItemPanelProps) {
  const [tab, setTab] = useState<'page' | 'post' | 'category' | 'custom'>('page');
  const [customTitle, setCustomTitle] = useState('');
  const [customUrl, setCustomUrl] = useState('https://');

  const tabs = [
    { id: 'page' as const, label: 'Strony', icon: FileText },
    { id: 'post' as const, label: 'Wpisy', icon: Newspaper },
    { id: 'category' as const, label: 'Kategorie', icon: Tag },
    { id: 'custom' as const, label: 'Link', icon: LinkIcon },
  ];

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-1 border-b border-[var(--color-border)]">
        {tabs.map((t) => {
          const Icon = t.icon;
          return (
            <button
              key={t.id}
              onClick={() => setTab(t.id)}
              className={cn(
                'flex items-center gap-1.5 px-3 py-2 text-xs font-medium transition-colors border-b-2 -mb-px',
                tab === t.id
                  ? 'border-[var(--color-primary)] text-[var(--color-primary)]'
                  : 'border-transparent text-[var(--color-muted-foreground)] hover:text-[var(--color-foreground)]',
              )}
            >
              <Icon className="w-3.5 h-3.5" />
              {t.label}
            </button>
          );
        })}
      </div>

      {tab === 'custom' ? (
        <div className="space-y-2">
          <Input placeholder="Tytuł" value={customTitle} onChange={(e) => setCustomTitle(e.target.value)} />
          <Input placeholder="https://example.com" value={customUrl} onChange={(e) => setCustomUrl(e.target.value)} />
          <Button
            size="sm"
            disabled={!customTitle.trim() || !customUrl.trim() || pending}
            onClick={() => {
              onAdd({ type: 'custom', url: customUrl.trim(), title: customTitle.trim() });
              setCustomTitle('');
              setCustomUrl('https://');
            }}
          >
            Dodaj link
          </Button>
        </div>
      ) : (
        <div className="max-h-64 overflow-y-auto space-y-1">
          {(sources?.[tab === 'category' ? 'categories' : tab === 'post' ? 'posts' : 'pages'] ?? []).map((src) => (
            <button
              key={src.id}
              disabled={pending}
              onClick={() => onAdd({ type: tab, objectId: src.id, title: src.title })}
              className="w-full text-left flex items-center justify-between px-3 py-2 rounded-[var(--radius)] text-sm hover:bg-[var(--color-surface-elevated)] transition-colors"
            >
              <span className="truncate text-[var(--color-foreground)]">{src.title}</span>
              <Plus className="w-3.5 h-3.5 text-[var(--color-subtle)]" />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
