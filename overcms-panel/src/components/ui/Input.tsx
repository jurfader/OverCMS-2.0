import { forwardRef, type InputHTMLAttributes, type TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

const baseInput =
  'w-full rounded-[var(--radius)] border border-[var(--color-border-hover)] ' +
  'bg-[var(--color-surface)] px-3 py-2 text-sm text-[var(--color-foreground)] ' +
  'placeholder:text-[var(--color-subtle)] transition-colors ' +
  'focus-visible:outline-none focus-visible:border-[var(--color-primary)] focus-visible:ring-1 focus-visible:ring-[var(--color-primary)] ' +
  'disabled:opacity-50 disabled:pointer-events-none';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
  ({ className, ...rest }, ref) => (
    <input ref={ref} className={cn(baseInput, 'h-9', className)} {...rest} />
  ),
);
Input.displayName = 'Input';

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(
  ({ className, ...rest }, ref) => (
    <textarea ref={ref} className={cn(baseInput, 'min-h-[80px] resize-none', className)} {...rest} />
  ),
);
Textarea.displayName = 'Textarea';
