import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import {
    ListCard,
    ListCardSkeleton,
    ListRow,
    RowPill,
    RowText,
    toneIcon,
} from './list-card';

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
    href?: string;
};

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
        <ListCard
            title="Uyarılar & Bildirimler"
            badge={
                active > 0 ? (
                    <Badge
                        variant="secondary"
                        className="font-mono tabular-nums"
                    >
                        {active} aktif
                    </Badge>
                ) : null
            }
        >
            {sorted.map((alert) => {
                const on = alert.count > 0;
                const tone = on ? alert.tone : undefined;
                const Icon = alert.icon;

                return (
                    <ListRow
                        key={alert.key}
                        tone={tone}
                        href={on ? alert.href : undefined}
                    >
                        <Icon
                            aria-hidden
                            className={cn('size-4 shrink-0', toneIcon(tone))}
                        />
                        <RowText
                            title={alert.label}
                            detail={alert.detail}
                            muted={!on}
                        />
                        <RowPill tone={tone} className="min-w-6">
                            {alert.count}
                        </RowPill>
                    </ListRow>
                );
            })}
        </ListCard>
    );
}

export { ListCardSkeleton as AlertsCardSkeleton };
