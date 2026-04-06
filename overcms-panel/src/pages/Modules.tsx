import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { Plus } from 'lucide-react';
import { api } from '@/lib/api';
import type { ModulesResponse } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Button } from '@/components/ui/Button';
import { Switch } from '@/components/ui/Switch';
import { Badge } from '@/components/ui/Badge';

export function ModulesPage() {
  const qc = useQueryClient();
  const navigate = useNavigate();
  const { data, isLoading } = useQuery({
    queryKey: ['modules'],
    queryFn: () => api<ModulesResponse>('overcms/v1/modules'),
  });

  const toggle = useMutation({
    mutationFn: ({ id, active }: { id: string; active: boolean }) =>
      api(`overcms/v1/modules/${id}/${active ? 'deactivate' : 'activate'}`, { method: 'POST' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['modules'] }),
  });

  return (
    <>
      <PageHeader
        title="Moduły"
        description="Zainstalowane pluginy. Aby dodać nowe, użyj Marketplace."
        actions={
          <Button icon={<Plus />} onClick={() => navigate('/marketplace')}>
            Otwórz Marketplace
          </Button>
        }
      />

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
    </>
  );
}
