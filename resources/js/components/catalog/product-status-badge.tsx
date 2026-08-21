import { Badge } from '@/components/ui/badge';

const VARIANTS = {
    draft: 'secondary',
    active: 'default',
    archived: 'outline',
} as const;

/**
 * Durum rozeti tek sozlukten beslenir ve her ekranda ayni gorunur
 * — FRONTEND-PLAN.md §8. Renk tek basina bilgi tasimaz, metin de vardir.
 */
export function ProductStatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return (
        <Badge variant={VARIANTS[status as keyof typeof VARIANTS] ?? 'outline'}>
            {label}
        </Badge>
    );
}
