import type { HTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';

export function Card({ className, children, ...rest }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('glass-card rounded-[var(--radius-lg)] p-6', className)} {...rest}>
      {children}
    </div>
  );
}

interface CardHeaderProps {
  title: ReactNode;
  description?: ReactNode;
  actions?: ReactNode;
  className?: string;
}

export function CardHeader({ title, description, actions, className }: CardHeaderProps) {
  return (
    <div className={cn('flex items-start justify-between gap-4 mb-4', className)}>
      <div className="flex flex-col gap-1">
        <h2 className="text-base font-semibold text-[var(--color-foreground)]">{title}</h2>
        {description && (
          <p className="text-sm text-[var(--color-muted-foreground)]">{description}</p>
        )}
      </div>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </div>
  );
}

export function CardFooter({ className, children }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={cn('flex items-center mt-4 pt-4 border-t border-[var(--color-border)]', className)}>
      {children}
    </div>
  );
}
