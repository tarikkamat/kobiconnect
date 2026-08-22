import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/**
 * Grafiksiz özet kartı. Beş kartın hepsi AYNI iskelette durur: sol üstte
 * etiket, sağ üstte tonlu ikon rozeti, altta büyük sayı + tek satır açıklama.
 * Yükseklik sabittir (`min-h-28`) ve iskeletiyle birebir aynıdır — deferred
 * veri geldiğinde sayfa kaymaz.
 *
 * Kart tıklanabilir ve kendi ekranına götürür — sayı göstermek tek başına işe
 * yaramaz, operatörün oradan devam edebilmesi gerekir.
 */
export function StatTile({
    icon: Icon,
    label,
    value,
    detail,
    href,
    tone = 'neutral',
}: {
    icon: LucideIcon;
    label: string;
    value: string | number;
    /** Tek satır; taşarsa kırpılır ki kart yüksekliği sabit kalsın. */
    detail: string;
    href: string;
    /** `warn` dikkat ister, `alert` müdahale ister; renk tek başına bilgi taşımaz, sayı ve metin de söyler. */
    tone?: 'neutral' | 'warn' | 'alert';
}) {
    return (
        <Card
            className={cn(
                'relative min-h-28 justify-between gap-3 p-4 transition-colors hover:border-ring',
                tone === 'warn' && 'border-amber-500/70',
                tone === 'alert' && 'border-destructive/70',
            )}
        >
            {/* ReUI Card zaten flex-col; header/content yerine serbest yerlesim. */}
            <div className="flex items-start justify-between gap-2">
                <Link
                    href={href}
                    prefetch
                    className="text-sm font-medium text-muted-foreground after:absolute after:inset-0"
                >
                    {label}
                </Link>
                <span
                    className={cn(
                        'flex size-7 shrink-0 items-center justify-center rounded-md',
                        tone === 'neutral' && 'bg-muted text-muted-foreground',
                        tone === 'warn' &&
                            'bg-amber-500/15 text-amber-600 dark:text-amber-400',
                        tone === 'alert' &&
                            'bg-destructive/10 text-destructive',
                    )}
                >
                    <Icon className="size-4" aria-hidden />
                </span>
            </div>

            <div className="min-w-0">
                <p className="text-3xl font-semibold tabular-nums">{value}</p>
                <p className="truncate text-xs text-muted-foreground">
                    {detail}
                </p>
            </div>
        </Card>
    );
}

/** StatTile ile birebir aynı yükseklikte iskelet — kayma olmaz. */
export function StatTileSkeleton() {
    return (
        <Card className="min-h-28 justify-between gap-3 p-4">
            <div className="flex items-start justify-between gap-2">
                <Skeleton className="h-5 w-24" />
                <Skeleton className="size-7 rounded-md" />
            </div>
            <div>
                <Skeleton className="h-9 w-16" />
                <Skeleton className="h-4 w-full" />
            </div>
        </Card>
    );
}
