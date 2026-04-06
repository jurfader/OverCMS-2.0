import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { cn } from '@/lib/cn';

type Variant = 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
type Size = 'sm' | 'default' | 'lg' | 'icon';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  icon?: ReactNode;
}

const variantClasses: Record<Variant, string> = {
  default:
    'gradient-bg text-white hover:glow-pink shadow-[var(--shadow-pink)]',
  destructive:
    'bg-[var(--color-destructive)] text-white hover:opacity-90',
  outline:
    'border border-[var(--color-border-hover)] bg-transparent text-[var(--color-foreground)] hover:bg-[var(--color-surface-elevated)]',
  secondary:
    'bg-[var(--color-surface-elevated)] text-[var(--color-foreground)] hover:bg-[var(--color-surface-hover)]',
  ghost:
    'bg-transparent text-[var(--color-foreground)] hover:bg-[var(--color-surface-elevated)]',
  link:
    'bg-transparent text-[var(--color-primary)] underline-offset-4 hover:underline p-0 h-auto',
};

const sizeClasses: Record<Size, string> = {
  sm: 'h-8 text-xs px-3',
  default: 'h-9 px-4 text-sm',
  lg: 'h-11 px-6 text-sm',
  icon: 'h-9 w-9 p-0',
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = 'default', size = 'default', icon, className, children, ...rest }, ref) => {
    return (
      <button
        ref={ref}
        className={cn(
          'inline-flex items-center justify-center gap-1.5 rounded-[var(--radius)] font-medium transition-all',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primary)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]',
          'disabled:opacity-50 disabled:pointer-events-none',
          variantClasses[variant],
          sizeClasses[size],
          className,
        )}
        {...rest}
      >
        {icon && <span className="w-3.5 h-3.5 inline-flex items-center justify-center">{icon}</span>}
        {children}
      </button>
    );
  },
);
Button.displayName = 'Button';
