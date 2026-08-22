import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/**
 * Bir uyari satiri. `detail` sayinin ARKASINDAKI cumleyi soyler — "3" tek
 * basina ne yapilacagini anlatmaz, "3 siparişte, eşlenmeden hazırlanamaz"
 * anlatir. Sifir olan satir listeden dusmez: sessizlik de bilgidir, ama
 * soluk durur ve tiklanmaz.
 */
export type Alert = {
    key: string;
    label: string;
    detail: string;
    count: number;
    icon: LucideIcon;
    /** `warn` dikkat ister, `alert` müdahale ister. */
    tone: 'warn' | 'alert';
    href: string;
};

/** Renk tek basina bilgi tasimaz: ikon, sayi ve `detail` metni de soyler. */
const TONE = {
    warn: {
        bar: 'border-l-amber-500',
        row: 'bg-amber-500/5',
        icon: 'text-amber-600 dark:text-amber-500',
        badge: 'bg-amber-500 text-white',
    },
    alert: {
        bar: 'border-l-destructive',
        row: 'bg-destructive/5',
        icon: 'text-destructive',
        badge: 'bg-destructive text-white',
    },
} as const;

/**
 * Uyarilar seridi — eskiden bes ayri StatTile'di. Tek kartta toplanmasinin
 * sebebi: operator sabah panele bakinca "bugun neye mudahale etmem lazim"
 * sorusunun cevabini TEK yerde gormeli, bes kartin sayilarini tek tek
 * taramamali. Sifir olmayan satirlar basa cikar.
 */
export function AlertsCard({ alerts }: { alerts: Alert[] }) {
    const sorted = [...alerts].sort(
        (a, b) =>
            Number(b.count > 0) - Number(a.count > 0) || b.count - a.count,
    );
    const active = alerts.filter((alert) => alert.count > 0).length;

    return (
        <Card className="gap-0 py-0">
            <CardHeader className="flex-row items-center justify-between border-b py-4">
                <CardTitle className="text-sm font-medium">
                    Uyarılar &amp; Bildirimler
                </CardTitle>
                {active > 0 ? (
                    <Badge variant="secondary" className="tabular-nums">
                        {active} aktif
                    </Badge>
                ) : null}
            </CardHeader>

            <CardContent className="px-0">
                <ul>
                    {sorted.map((alert) => {
                        const on = alert.count > 0;
                        const tone = TONE[alert.tone];
                        const Icon = alert.icon;

                        const row = (
                            <div
                                className={cn(
                                    'flex items-center gap-3 border-l-2 px-4 py-2.5 text-sm',
                                    on
                                        ? `${tone.bar} ${tone.row}`
                                        : 'border-l-transparent',
                                )}
                            >
                                <Icon
                                    aria-hidden
                                    className={cn(
                                        'size-4 shrink-0',
                                        on
                                            ? tone.icon
                                            : 'text-muted-foreground',
                                    )}
                                />
                                <div className="min-w-0">
                                    <div
                                        className={
                                            on
                                                ? 'font-medium'
                                                : 'text-muted-foreground'
                                        }
                                    >
                                        {alert.label}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {alert.detail}
                                    </div>
                                </div>
                                <span
                                    className={cn(
                                        'ml-auto min-w-6 rounded-full px-2 py-0.5 text-center text-xs font-semibold tabular-nums',
                                        on
                                            ? tone.badge
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    {alert.count}
                                </span>
                            </div>
                        );

                        return (
                            <li
                                key={alert.key}
                                className="border-b last:border-b-0"
                            >
                                {/* Sifir olan satirin gidecegi yer yok: acilan
                                    ekran bos liste gosterirdi. */}
                                {on ? (
                                    <Link
                                        href={alert.href}
                                        prefetch
                                        className="block transition-colors hover:brightness-95"
                                    >
                                        {row}
                                    </Link>
                                ) : (
                                    row
                                )}
                            </li>
                        );
                    })}
                </ul>
            </CardContent>
        </Card>
    );
}

/** Satir sayisi gercek kartla ayni; veri gelince yerlesim kaymaz. */
export function AlertsCardSkeleton({ rows = 4 }: { rows?: number }) {
    return (
        <Card className="gap-0 py-0">
            <CardHeader className="border-b py-4">
                <Skeleton className="h-5 w-44" />
            </CardHeader>
            <CardContent className="px-0">
                {Array.from({ length: rows }, (_, slot) => (
                    <div
                        key={slot}
                        className="flex items-center gap-3 border-b px-4 py-2.5 last:border-b-0"
                    >
                        <Skeleton className="size-4 shrink-0 rounded" />
                        <div className="w-full space-y-1">
                            <Skeleton className="h-4 w-32" />
                            <Skeleton className="h-3 w-48" />
                        </div>
                        <Skeleton className="size-6 shrink-0 rounded-full" />
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
