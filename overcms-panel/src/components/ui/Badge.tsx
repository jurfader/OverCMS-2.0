import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';

type Variant = 'default' | 'success' | 'warning' | 'destructive' | 'outline' | 'secondary';

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: Variant;
  children: ReactNode;
}

const variants: Record<Variant, string> = {
  default: 'bg-[var(--color-primary-muted)] text-[var(--color-primary)]',
  success: 'bg-[color-mix(in_srgb,var(--color-success)_15%,transparent)] text-[var(--color-success)]',
  warning: 'bg-[color-mix(in_srgb,var(--color-warning)_15%,transparent)] text-[var(--color-warning)]',
  destructive: 'bg-[color-mix(in_srgb,var(--color-destructive)_15%,transparent)] text-[var(--color-destructive)]',
  outline: 'border border-[var(--color-border-hover)] text-[var(--color-foreground)]',
  secondary: 'bg-[var(--color-secondary-muted)] text-[var(--color-secondary)]',
};

export function Badge({ variant = 'default', className, children, ...rest }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium',
        variants[variant],
        className,
      )}
      {...rest}
    >
      {children}
    </span>
  );
}
