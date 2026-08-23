import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export interface EmptyStateProps {
    icon?: LucideIcon;
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    className?: string;
}

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-lg border border-dashed border-border py-14 px-4 text-center',
                className,
            )}
        >
            {Icon && (
                <div className="flex size-12 items-center justify-center rounded-full bg-secondary text-muted-foreground">
                    <Icon className="size-6" />
                </div>
            )}
            <h3 className="mt-4 font-sans font-semibold text-lg sm:text-xl tracking-tight text-foreground">
                {title}
            </h3>
            {description && (
                <p className="mt-1.5 max-w-md text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && (
                <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
                    {action}
                </div>
            )}
        </div>
    );
}

export default EmptyState;
