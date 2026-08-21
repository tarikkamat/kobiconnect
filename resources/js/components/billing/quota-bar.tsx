import { cn } from '@/lib/utils';

export type QuotaLevel = 'ok' | 'warning' | 'critical' | 'unlimited';

export type Quota = {
    key: string;
    label: string;
    used: number;
    max: number | null;
    ratio: number | null;
    level: QuotaLevel;
};

/**
 * Kota gostergesi — FRONTEND-PLAN §5: %80 sari, %100 kirmizi.
 *
 * Esigi sunucu belirler (`level`), burada yalnizca renge cevriliyor: yuzde
 * mantigi iki yerde tutulmaz.
 *
 * ponytail: Radix Progress kurulu degil ve bunun icin bir bagimlilik eklemeye
 * degmez — dolgu bir div'in genisligi. Erisilebilirlik icin `role="meter"`
 * degerleri elle veriliyor.
 */
const FILL: Record<QuotaLevel, string> = {
    ok: 'bg-primary',
    warning: 'bg-amber-500',
    critical: 'bg-destructive',
    unlimited: 'bg-muted-foreground/40',
};

export function QuotaBar({ quota }: { quota: Quota }) {
    const percent =
        quota.ratio === null ? 0 : Math.min(100, Math.round(quota.ratio * 100));

    return (
        <div className="grid gap-1.5">
            <div className="flex items-baseline justify-between text-sm">
                <span>{quota.label}</span>
                <span
                    className={cn(
                        'tabular-nums',
                        quota.level === 'critical' && 'text-destructive',
                        quota.level === 'warning' && 'text-amber-600',
                        quota.level === 'unlimited' && 'text-muted-foreground',
                    )}
                >
                    {quota.max === null
                        ? `${quota.used} · limitsiz`
                        : `${quota.used} / ${quota.max}`}
                </span>
            </div>

            <div
                role="meter"
                aria-label={quota.label}
                aria-valuenow={quota.used}
                aria-valuemin={0}
                aria-valuemax={quota.max ?? quota.used}
                className="h-2 w-full overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn('h-full rounded-full', FILL[quota.level])}
                    style={{ width: `${quota.max === null ? 100 : percent}%` }}
                />
            </div>

            {quota.level === 'critical' && (
                <p className="text-xs text-destructive">
                    Kota doldu. Yeni kayıt eklemek için planınızı yükseltin.
                </p>
            )}
            {quota.level === 'warning' && (
                <p className="text-xs text-amber-600">
                    Kotanızın %{percent}'ini kullandınız.
                </p>
            )}
        </div>
    );
}
