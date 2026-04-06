import { useEffect, useState, type ReactNode } from 'react';
import { Sidebar } from './Sidebar';
import { Topbar } from './Topbar';

export function Shell({ children }: { children: ReactNode }) {
  const [collapsed, setCollapsed] = useState(false);

  useEffect(() => {
    const width = collapsed ? 68 : 260;
    document.documentElement.style.setProperty('--sidebar-current', `${width}px`);
  }, [collapsed]);

  return (
    <div className="min-h-screen">
      <Sidebar collapsed={collapsed} onToggle={() => setCollapsed((v) => !v)} />
      <Topbar />
      <main
        className="pt-[60px] transition-[padding] duration-[250ms] ease-[cubic-bezier(0.4,0,0.2,1)]"
        style={{ paddingLeft: collapsed ? 68 : 260 }}
      >
        <div className="p-6 animate-fade-in">{children}</div>
      </main>
    </div>
  );
}

interface PageHeaderProps {
  title: string;
  description?: string;
  actions?: ReactNode;
}

export function PageHeader({ title, description, actions }: PageHeaderProps) {
  return (
    <div className="flex items-start justify-between gap-4 mb-6">
      <div>
        <h1 className="text-2xl font-bold text-[var(--color-foreground)]">{title}</h1>
        {description && (
          <p className="text-sm text-[var(--color-muted-foreground)] mt-1">{description}</p>
        )}
      </div>
      {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
    </div>
  );
}
