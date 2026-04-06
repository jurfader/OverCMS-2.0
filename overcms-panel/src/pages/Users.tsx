import { useQuery } from '@tanstack/react-query';
import { ExternalLink, UserPlus } from 'lucide-react';
import { api } from '@/lib/api';
import { boot, type WpUser } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';

export function UsersPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['users'],
    queryFn: () => api<WpUser[]>('wp/v2/users', { query: { per_page: 100, context: 'edit' } }),
  });

  return (
    <>
      <PageHeader
        title="Użytkownicy"
        description="Konta z dostępem do panelu OverCMS."
        actions={
          <Button
            icon={<UserPlus />}
            onClick={() => window.open(`${boot.adminUrl}user-new.php`, '_blank')}
          >
            Dodaj użytkownika
          </Button>
        }
      />

      <div className="glass-card rounded-[var(--radius-lg)] overflow-hidden">
        <div className="grid grid-cols-[48px_1fr_180px_120px_60px] px-5 py-2.5 border-b border-[var(--color-border)] bg-[var(--color-surface-elevated)]">
          <span />
          <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Imię</span>
          <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Email</span>
          <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Rola</span>
          <span />
        </div>
        <div className="divide-y divide-[var(--color-border)]">
          {isLoading && <p className="text-sm text-[var(--color-muted-foreground)] px-5 py-4">Ładowanie…</p>}
          {data?.map((u) => (
            <div
              key={u.id}
              className="grid grid-cols-[48px_1fr_180px_120px_60px] items-center px-5 py-3 hover:bg-[var(--color-surface-elevated)] transition-colors"
            >
              <img src={u.avatar_urls?.['48']} alt="" className="w-8 h-8 rounded-full" />
              <span className="text-sm font-medium text-[var(--color-foreground)]">{u.name}</span>
              <span className="text-xs text-[var(--color-muted-foreground)] truncate">{u.email ?? '—'}</span>
              <span>{u.roles?.[0] && <Badge variant="secondary">{u.roles[0]}</Badge>}</span>
              <a
                href={`${boot.adminUrl}user-edit.php?user_id=${u.id}`}
                target="_blank"
                rel="noreferrer"
                className="text-[var(--color-muted-foreground)] hover:text-[var(--color-primary)] justify-self-end"
              >
                <ExternalLink className="w-4 h-4" />
              </a>
            </div>
          ))}
        </div>
      </div>
    </>
  );
}
