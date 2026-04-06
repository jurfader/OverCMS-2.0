import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Shield, CheckCircle2, AlertTriangle, AlertCircle, Trash2 } from 'lucide-react';
import { api } from '@/lib/api';
import type { SecurityStatus, LoginAttempt } from '@/lib/types';
import { PageHeader } from '@/components/layout/Shell';
import { Card, CardHeader } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';

export function SecurityPage() {
  const qc = useQueryClient();

  const { data: status } = useQuery({
    queryKey: ['security-status'],
    queryFn: () => api<SecurityStatus>('overcms/v1/security/status'),
  });

  const { data: attempts } = useQuery({
    queryKey: ['login-attempts'],
    queryFn: () => api<{ items: LoginAttempt[]; total: number }>('overcms/v1/security/login-attempts'),
  });

  const clear = useMutation({
    mutationFn: () => api('overcms/v1/security/login-attempts', { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['login-attempts'] }),
  });

  return (
    <>
      <PageHeader
        title="Bezpieczeństwo"
        description="Status hardening witryny i log nieudanych prób logowania"
      />

      {status && (
        <Card className="mb-4">
          <div className="flex items-center gap-4 mb-4">
            <ScoreCircle percent={status.percent} />
            <div>
              <p className="text-sm font-semibold text-[var(--color-foreground)]">
                Wynik bezpieczeństwa: {status.score} / {status.maxScore}
              </p>
              <p className="text-xs text-[var(--color-muted-foreground)]">
                {status.percent >= 90
                  ? 'Doskonale — wszystkie krytyczne kontrole zaliczone'
                  : status.percent >= 70
                    ? 'Dobrze — kilka rzeczy do poprawy'
                    : 'Wymaga uwagi — sprawdź czerwone elementy poniżej'}
              </p>
            </div>
          </div>

          <div className="space-y-2 -mx-6 px-6 pt-3 border-t border-[var(--color-border)]">
            {status.checks.map((c) => (
              <div key={c.id} className="flex items-center gap-3 py-1.5">
                {c.ok ? (
                  <CheckCircle2 className="w-4 h-4 text-[var(--color-success)] shrink-0" />
                ) : c.level === 'critical' ? (
                  <AlertCircle className="w-4 h-4 text-[var(--color-destructive)] shrink-0" />
                ) : (
                  <AlertTriangle className="w-4 h-4 text-[var(--color-warning)] shrink-0" />
                )}
                <span className="text-sm text-[var(--color-foreground)] flex-1">{c.label}</span>
                <Badge
                  variant={c.level === 'critical' ? 'destructive' : c.level === 'warning' ? 'warning' : 'outline'}
                >
                  {c.level}
                </Badge>
              </div>
            ))}
          </div>
        </Card>
      )}

      <Card>
        <CardHeader
          title="Nieudane próby logowania"
          description={`Ostatnie ${attempts?.total ?? 0} prób`}
          actions={
            attempts && attempts.total > 0 ? (
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  if (confirm('Wyczyścić cały log prób logowania?')) clear.mutate();
                }}
              >
                <Trash2 className="w-3.5 h-3.5 mr-1" /> Wyczyść
              </Button>
            ) : null
          }
        />

        {!attempts || attempts.total === 0 ? (
          <div className="text-center py-8">
            <Shield className="w-10 h-10 text-[var(--color-success)] mx-auto mb-2" />
            <p className="text-sm text-[var(--color-muted-foreground)]">
              Brak nieudanych prób logowania.
            </p>
          </div>
        ) : (
          <div className="overflow-hidden -mx-6">
            <div className="grid grid-cols-[1fr_140px_180px] px-6 py-2 border-b border-[var(--color-border)] bg-[var(--color-surface-elevated)]">
              <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Login / IP</span>
              <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">IP</span>
              <span className="text-[10px] font-semibold uppercase tracking-widest text-[var(--color-subtle)]">Czas</span>
            </div>
            <div className="divide-y divide-[var(--color-border)] max-h-96 overflow-y-auto">
              {attempts.items.map((a, i) => (
                <div key={i} className="grid grid-cols-[1fr_140px_180px] px-6 py-2.5 items-center text-xs">
                  <span className="font-mono text-[var(--color-foreground)] truncate">{a.username || '(pusty)'}</span>
                  <span className="font-mono text-[var(--color-muted-foreground)]">{a.ip}</span>
                  <span className="text-[var(--color-muted-foreground)]">
                    {a.timestamp ? new Date(a.timestamp + 'Z').toLocaleString('pl-PL') : '—'}
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}
      </Card>
    </>
  );
}

function ScoreCircle({ percent }: { percent: number }) {
  const color =
    percent >= 90 ? 'var(--color-success)' : percent >= 70 ? 'var(--color-warning)' : 'var(--color-destructive)';
  const r = 28;
  const c = 2 * Math.PI * r;
  const offset = c - (percent / 100) * c;

  return (
    <div className="relative w-16 h-16 shrink-0">
      <svg viewBox="0 0 64 64" className="w-16 h-16 -rotate-90">
        <circle cx="32" cy="32" r={r} fill="none" stroke="var(--color-surface-elevated)" strokeWidth="6" />
        <circle
          cx="32"
          cy="32"
          r={r}
          fill="none"
          stroke={color}
          strokeWidth="6"
          strokeDasharray={c}
          strokeDashoffset={offset}
          strokeLinecap="round"
          style={{ transition: 'stroke-dashoffset 0.6s ease' }}
        />
      </svg>
      <span className="absolute inset-0 flex items-center justify-center text-sm font-bold text-[var(--color-foreground)]">
        {percent}%
      </span>
    </div>
  );
}
