import { useEffect, useState } from 'react';
import type { Theme } from './types';

const KEY = 'overcms-theme';

function readInitial(): Theme {
  if (typeof window === 'undefined') return 'dark';
  const stored = window.localStorage.getItem(KEY) as Theme | null;
  if (stored === 'dark' || stored === 'light') return stored;
  return 'dark';
}

function apply(theme: Theme): void {
  const html = document.documentElement;
  html.classList.toggle('dark', theme === 'dark');
  html.classList.toggle('light', theme === 'light');
}

export function useTheme(): [Theme, (next: Theme) => void, () => void] {
  const [theme, setThemeState] = useState<Theme>(readInitial);

  useEffect(() => {
    apply(theme);
    window.localStorage.setItem(KEY, theme);
  }, [theme]);

  const setTheme = (next: Theme) => setThemeState(next);
  const toggle = () => setThemeState((t) => (t === 'dark' ? 'light' : 'dark'));

  return [theme, setTheme, toggle];
}
