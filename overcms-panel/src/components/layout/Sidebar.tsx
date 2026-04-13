import { useState, type ComponentType } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  LayoutDashboard,
  FileText,
  Image as ImageIcon,
  Search,
  Compass,
  Layers,
  Newspaper,
  Users,
  Settings,
  Puzzle,
  ShoppingBag,
  ShoppingCart,
  Database,
  Shield,
  ChevronLeft,
  ChevronRight,
  Sparkles,
} from 'lucide-react';
import { Tooltip } from '@/components/ui/Tooltip';
import { boot } from '@/lib/types';
import { cn } from '@/lib/cn';

interface NavItem {
  label: string;
  to: string;
  icon: ComponentType<{ className?: string }>;
}

interface NavSection {
  label: string;
  items: NavItem[];
}

const baseSections: NavSection[] = [
  {
    label: 'Główne',
    items: [
      { label: 'Dashboard', to: '/', icon: LayoutDashboard },
      { label: 'Strony', to: '/pages', icon: FileText },
      { label: 'Media', to: '/media', icon: ImageIcon },
    ],
  },
  {
    label: 'Witryna',
    items: [
      { label: 'SEO', to: '/seo', icon: Search },
      { label: 'Nawigacja', to: '/navigation', icon: Compass },
      { label: 'Szablony', to: '/templates', icon: Layers },
      { label: 'Blog', to: '/posts', icon: Newspaper },
      ...(boot.hasWooCommerce ? [{ label: 'Sklep', to: '/shop', icon: ShoppingCart }] : []),
    ],
  },
  {
    label: 'System',
    items: [
      { label: 'Użytkownicy', to: '/users', icon: Users },
      { label: 'Ustawienia', to: '/settings', icon: Settings },
      { label: 'Moduły', to: '/modules', icon: Puzzle },
      { label: 'Marketplace', to: '/marketplace', icon: ShoppingBag },
      { label: 'Backupy', to: '/backups', icon: Database },
      { label: 'Bezpieczeństwo', to: '/security', icon: Shield },
    ],
  },
];

const sections = baseSections;

interface SidebarProps {
  collapsed: boolean;
  onToggle: () => void;
}

export function Sidebar({ collapsed, onToggle }: SidebarProps) {
  return (
    <motion.aside
      initial={false}
      animate={{ width: collapsed ? 68 : 260 }}
      transition={{ duration: 0.25, ease: [0.4, 0, 0.2, 1] }}
      className="glass fixed top-0 left-0 h-screen border-r border-[var(--color-border)] flex flex-col z-40"
    >
      {/* Logo */}
      <div className="h-[60px] flex items-center gap-2.5 px-4 border-b border-[var(--color-border)] shrink-0">
        <div className="w-8 h-8 rounded-[var(--radius)] gradient-bg glow-pink flex items-center justify-center shrink-0">
          <Sparkles className="w-4 h-4 text-white" />
        </div>
        <AnimatePresence>
          {!collapsed && (
            <motion.span
              initial={{ opacity: 0, x: -8 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: -8 }}
              transition={{ duration: 0.2 }}
              className="gradient-text text-lg font-bold tracking-tight"
            >
              OverCMS
            </motion.span>
          )}
        </AnimatePresence>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto py-4 px-2 space-y-6">
        {sections.map((section) => (
          <div key={section.label}>
            {!collapsed && (
              <p className="text-[10px] uppercase tracking-widest text-[var(--color-subtle)] px-3 mb-1.5">
                {section.label}
              </p>
            )}
            <div className="space-y-0.5">
              {section.items.map((item) => (
                <NavItemLink key={item.to} item={item} collapsed={collapsed} />
              ))}
            </div>
          </div>
        ))}
      </nav>

      {/* Footer */}
      <div className="border-t border-[var(--color-border)] p-2 shrink-0">
        <button
          onClick={onToggle}
          className="w-full h-9 flex items-center justify-center rounded-[var(--radius)] hover:bg-[var(--color-surface-elevated)] text-[var(--color-muted-foreground)] transition-colors"
          aria-label={collapsed ? 'Rozwiń menu' : 'Zwiń menu'}
        >
          {collapsed ? <ChevronRight className="w-4 h-4" /> : <ChevronLeft className="w-4 h-4" />}
        </button>
        {!collapsed && (
          <p className="font-mono text-[10px] text-[var(--color-subtle)] text-center mt-2">
            v{boot.version}
          </p>
        )}
      </div>
    </motion.aside>
  );
}

function NavItemLink({ item, collapsed }: { item: NavItem; collapsed: boolean }) {
  const Icon = item.icon;
  const link = (
    <NavLink
      to={item.to}
      end={item.to === '/'}
      className={({ isActive }) =>
        cn(
          'relative flex items-center gap-2.5 h-9 px-3 rounded-[var(--radius)] text-sm transition-colors',
          isActive
            ? 'bg-[var(--color-primary-muted)] text-[var(--color-primary)]'
            : 'text-[var(--color-muted-foreground)] hover:bg-[var(--color-surface-elevated)] hover:text-[var(--color-foreground)]',
          collapsed && 'justify-center',
        )
      }
    >
      {({ isActive }) => (
        <>
          {isActive && (
            <span className="absolute left-0 w-0.5 h-5 bg-[var(--color-primary)] rounded-r" />
          )}
          <Icon className="w-4 h-4 shrink-0" />
          {!collapsed && <span className="truncate">{item.label}</span>}
        </>
      )}
    </NavLink>
  );

  if (collapsed) {
    return <Tooltip label={item.label}>{link}</Tooltip>;
  }
  return link;
}
