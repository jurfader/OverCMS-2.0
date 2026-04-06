import { useQuery } from '@tanstack/react-query';
import { FileText, Image, Users, Newspaper, ExternalLink } from 'lucide-react';
import { api } from '@/lib/api';
import type { DashboardResponse } from '@/lib/types';
import { boot } from '@/lib/types';
import { Card, CardHeader } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { PageHeader } from '@/components/layout/Shell';

export function DashboardPage() {
  const { data, isLoading, error } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => api<DashboardResponse>('overcms/v1/dashboard'),
  });

  return (
    <>
      <PageHeader
        title={`Cześć, ${boot.currentUser.name.split(' ')[0]} 👋`}
        description="Tu znajdziesz przegląd swojej witryny i ostatnie aktywności."
      />

      {isLoading && <p className="text-sm text-[var(--color-muted-foreground)]">Ładowanie…</p>}
      {error && (
        <Card className="bg-[color-mix(in_srgb,var(--color-destructive)_10%,transparent)]">
          <p className="text-sm text-[var(--color-destructive)]">Nie udało się załadować danych.</p>
        </Card>
      )}

      {data && (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard label="Strony" value={data.stats.pages} hint={`${data.stats.pagesAll} łącznie`} icon={<FileText className="w-4 h-4" />} />
            <StatCard label="Wpisy" value={data.stats.posts} hint={`${data.stats.postsAll} łącznie`} icon={<Newspaper className="w-4 h-4" />} />
            <StatCard label="Pliki w mediach" value={data.stats.media} icon={<Image className="w-4 h-4" />} />
            <StatCard label="Użytkownicy" value={data.stats.users} icon={<Users className="w-4 h-4" />} />
          </div>

          <Card>
            <CardHeader
              title="Ostatnio modyfikowane"
              description="Pięć ostatnio edytowanych stron i wpisów"
            />
            <div className="divide-y divide-[var(--color-border)] -mx-6">
              {data.recent.map((item) => (
                <div
                  key={`${item.type}-${item.id}`}
                  className="flex items-center gap-3 px-6 py-3 hover:bg-[var(--color-surface-elevated)] transition-colors"
                >
                  <div className="w-9 h-9 rounded-[var(--radius)] bg-[var(--color-surface-elevated)] flex items-center justify-center text-[var(--color-muted-foreground)]">
                    {item.type === 'page' ? <FileText className="w-4 h-4" /> : <Newspaper className="w-4 h-4" />}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-[var(--color-foreground)] truncate">
                      {item.title}
                    </p>
                    <p className="text-xs text-[var(--color-muted-foreground)]">
                      {new Date(item.modifiedAt + 'Z').toLocaleString('pl-PL')}
                    </p>
                  </div>
                  <Badge variant={item.status === 'publish' ? 'success' : 'warning'}>
                    {item.status}
                  </Badge>
                  {item.editUrl && (
                    <a
                      href={item.editUrl}
                      className="text-[var(--color-muted-foreground)] hover:text-[var(--color-primary)] transition-colors"
                      title="Edytuj"
                    >
                      <ExternalLink className="w-4 h-4" />
                    </a>
                  )}
                </div>
              ))}
              {data.recent.length === 0 && (
                <p className="text-sm text-[var(--color-muted-foreground)] px-6 py-4">
                  Brak treści — dodaj pierwszą stronę.
                </p>
              )}
            </div>
          </Card>
        </>
      )}
    </>
  );
}

interface StatCardProps {
  label: string;
  value: number;
  hint?: string;
  icon: React.ReactNode;
}

function StatCard({ label, value, hint, icon }: StatCardProps) {
  return (
    <div className="glass-card rounded-[var(--radius-lg)] p-5">
      <div className="flex items-center justify-between mb-2">
        <p className="text-xs text-[var(--color-muted-foreground)]">{label}</p>
        <span className="w-7 h-7 rounded-[var(--radius)] bg-[var(--color-primary-muted)] text-[var(--color-primary)] flex items-center justify-center">
          {icon}
        </span>
      </div>
      <p className="text-2xl font-bold text-[var(--color-foreground)]">{value}</p>
      {hint && <p className="text-xs text-[var(--color-muted-foreground)] mt-1">{hint}</p>}
    </div>
  );
}
