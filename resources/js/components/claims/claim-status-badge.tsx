import { Badge } from '@/components/ui/badge';

/**
 * Kanonik iade durumlari — pazaryerinin ham durumlari degil.
 * FRONTEND-PLAN.md §8: renk tek basina bilgi tasimaz, metin de vardir.
 */
const VARIANTS: Record<
    string,
    'success' | 'warning' | 'destructive' | 'info' | 'outline'
> = {
    created: 'info',
    waiting_action: 'warning',
    under_review: 'info',
    accepted: 'success',
    rejected: 'destructive',
    cancelled: 'outline',
    unresolved: 'destructive',
};

/** Operatorun aksiyon beklediginin tek isareti; uyari renginde ayrisir. */
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
        <Badge variant={VARIANTS[status] ?? 'outline'} className={className}>
            {label}
        </Badge>
    );
}
