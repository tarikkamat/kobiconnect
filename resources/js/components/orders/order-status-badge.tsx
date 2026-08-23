import { Badge } from '@/components/ui/badge';

/**
 * Kanonik siparis durumlari — pazaryerinin ham durumlari degil.
 * FRONTEND-PLAN.md §8: renk tek basina bilgi tasimaz, metin de vardir.
 */
const VARIANTS = {
    pending_payment: 'warning',
    created: 'info',
    picking: 'info',
    invoiced: 'info',
    shipped: 'info',
    at_collection_point: 'info',
    delivered: 'success',
    undelivered: 'destructive',
    unpacked: 'warning',
    unsupplied: 'destructive',
    cancelled: 'destructive',
    returned: 'destructive',
} as const;

/**
 * `pending_payment` (Trendyol `Awaiting`) odeme onayi beklemededir. Trendyol bu
 * siparisler gonderilirse sorumluluk kabul etmiyor, bu yuzden rozet uyari
 * renginde ayrisir.
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
            className={className}
        >
            {label}
        </Badge>
    );
}
