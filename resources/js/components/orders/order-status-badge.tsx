import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Kanonik siparis durumlari — pazaryerinin ham durumlari degil.
 * FRONTEND-PLAN.md §8: renk tek basina bilgi tasimaz, metin de vardir.
 */
const VARIANTS = {
    pending_payment: 'outline',
    created: 'default',
    picking: 'secondary',
    invoiced: 'secondary',
    shipped: 'secondary',
    at_collection_point: 'secondary',
    delivered: 'default',
    undelivered: 'destructive',
    unpacked: 'outline',
    unsupplied: 'destructive',
    cancelled: 'destructive',
    returned: 'destructive',
} as const;

/**
 * `pending_payment` (Trendyol `Awaiting`) odeme onayi beklemededir. Trendyol bu
 * siparisler gonderilirse sorumluluk kabul etmiyor, bu yuzden rozet gorsel
 * olarak da ayrisir: amber cerceve + uyari metni.
 */
export const PENDING_PAYMENT = 'pending_payment';

export function OrderStatusBadge({
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
            variant={VARIANTS[status as keyof typeof VARIANTS] ?? 'outline'}
            className={cn(
                status === PENDING_PAYMENT &&
                    'border-amber-500 bg-amber-50 text-amber-900 dark:bg-amber-950 dark:text-amber-100',
                className,
            )}
        >
            {label}
        </Badge>
    );
}
