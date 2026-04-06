import { useState, type ReactNode } from 'react';
import { cn } from '@/lib/cn';

interface TooltipProps {
  label: string;
  side?: 'right' | 'top' | 'bottom' | 'left';
  children: ReactNode;
}

export function Tooltip({ label, side = 'right', children }: TooltipProps) {
  const [open, setOpen] = useState(false);

  const positions: Record<NonNullable<TooltipProps['side']>, string> = {
    right: 'left-full ml-2 top-1/2 -translate-y-1/2',
    top: 'bottom-full mb-2 left-1/2 -translate-x-1/2',
    bottom: 'top-full mt-2 left-1/2 -translate-x-1/2',
    left: 'right-full mr-2 top-1/2 -translate-y-1/2',
  };

  return (
    <span
      className="relative inline-flex"
      onMouseEnter={() => setOpen(true)}
      onMouseLeave={() => setOpen(false)}
      onFocus={() => setOpen(true)}
      onBlur={() => setOpen(false)}
    >
      {children}
      {open && (
        <span
          role="tooltip"
          className={cn(
            'absolute z-50 whitespace-nowrap glass rounded-[var(--radius-sm)] px-3 py-1.5 text-xs text-[var(--color-foreground)] pointer-events-none animate-fade-in',
            positions[side],
          )}
        >
          {label}
        </span>
      )}
    </span>
  );
}
