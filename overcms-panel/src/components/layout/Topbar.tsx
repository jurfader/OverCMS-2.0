import { useState, useRef, useEffect } from 'react';
import { Search, Bell, Sun, Moon, LogOut, User as UserIcon } from 'lucide-react';
import { Input } from '@/components/ui/Input';
import { useTheme } from '@/lib/theme';
import { boot } from '@/lib/types';
import { cn } from '@/lib/cn';

export function Topbar() {
  const [theme, , toggleTheme] = useTheme();
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const onClickOutside = (e: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) {
        setMenuOpen(false);
      }
    };
    document.addEventListener('mousedown', onClickOutside);
    return () => document.removeEventListener('mousedown', onClickOutside);
  }, []);

  const initials = boot.currentUser.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();

  return (
    <header
      className="glass fixed top-0 right-0 h-[60px] border-b border-[var(--color-border)] flex items-center px-5 gap-3 z-30"
      style={{ left: 'var(--sidebar-current, 260px)' }}
    >
      <div className="relative max-w-sm w-full">
        <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-subtle)]" />
        <Input placeholder="Szukaj…" className="pl-9" />
      </div>

      <div className="flex-1" />

      <button
        onClick={toggleTheme}
        className="h-9 w-9 inline-flex items-center justify-center rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] transition-colors"
        aria-label="Przełącz motyw"
      >
        {theme === 'dark' ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
      </button>

      <button
        className="h-9 w-9 inline-flex items-center justify-center rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] transition-colors relative"
        aria-label="Powiadomienia"
      >
        <Bell className="w-4 h-4" />
        <span className="absolute top-2 right-2 w-1.5 h-1.5 rounded-full bg-[var(--color-destructive)]" />
      </button>

      <div className="w-px h-6 bg-[var(--color-border)] mx-1" />

      <div className="relative" ref={menuRef}>
        <button
          onClick={() => setMenuOpen((v) => !v)}
          className="flex items-center gap-2.5 h-9 pl-1 pr-3 rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] transition-colors"
        >
          <span className="w-7 h-7 rounded-full gradient-bg flex items-center justify-center text-white text-xs font-semibold">
            {initials || 'U'}
          </span>
          <span className="text-left hidden sm:block leading-tight">
            <span className="block text-xs font-semibold text-[var(--color-foreground)]">
              {boot.currentUser.name}
            </span>
            <span className="block text-[10px] text-[var(--color-subtle)]">
              {boot.currentUser.roles[0] ?? 'user'}
            </span>
          </span>
        </button>

        <div
          className={cn(
            'absolute right-0 top-full mt-2 w-56 glass rounded-[var(--radius-lg)] p-1.5 shadow-[var(--shadow-lg)] origin-top-right transition-all',
            menuOpen ? 'opacity-100 scale-100 pointer-events-auto' : 'opacity-0 scale-95 pointer-events-none',
          )}
        >
          <a
            href={`${boot.adminUrl}profile.php`}
            className="flex items-center gap-2 px-3 py-2 rounded-[var(--radius-sm)] text-sm text-[var(--color-foreground)] hover:bg-[var(--color-surface-elevated)]"
          >
            <UserIcon className="w-4 h-4" /> Profil
          </a>
          <a
            href={boot.logoutUrl}
            className="flex items-center gap-2 px-3 py-2 rounded-[var(--radius-sm)] text-sm text-[var(--color-foreground)] hover:bg-[var(--color-surface-elevated)]"
          >
            <LogOut className="w-4 h-4" /> Wyloguj
          </a>
        </div>
      </div>
    </header>
  );
}
