import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Kanonik iade durumlari — pazaryerinin ham durumlari degil.
 * FRONTEND-PLAN.md §8: renk tek basina bilgi tasimaz, metin de vardir.
 */
const VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    created: 'outline',
    waiting_action: 'default',
    under_review: 'secondary',
    accepted: 'secondary',
    rejected: 'destructive',
    cancelled: 'outline',
    unresolved: 'destructive',
};

/** Operatorun aksiyon beklediginin tek isareti; gorsel olarak da ayrisir. */
export const WAITING_ACTION = 'waiting_action';

export function ClaimStatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label: string;
    className?: string;
}) {
    return (
        <Badge
            variant={VARIANTS[status] ?? 'outline'}
            className={cn(
                status === WAITING_ACTION &&
                    'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
                className,
            )}
        >
            {label}
        </Badge>
    );
}
