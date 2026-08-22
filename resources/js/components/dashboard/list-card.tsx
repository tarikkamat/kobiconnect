import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/** `warn` dikkat ister, `alert` müdahale ister; yoksa satır sakin durur. */
export type RowTone = 'warn' | 'alert' | undefined;

/**
 * Renk tek basina bilgi tasimaz — her satirda sayi ve metin de ayni seyi
 * soyler. Sol seritteki renk yalnizca TARAMAYI hizlandirir.
 */
const TONE = {
    warn: {
        bar: 'border-l-amber-500',
        row: 'bg-amber-500/5',
        icon: 'text-amber-600 dark:text-amber-500',
        pill: 'bg-amber-500 text-white',
    },
    alert: {
        bar: 'border-l-destructive',
        row: 'bg-destructive/5',
        icon: 'text-destructive',
        pill: 'bg-destructive text-white',
    },
} as const;

export function toneIcon(tone: RowTone): string {
    return tone ? TONE[tone].icon : 'text-muted-foreground';
}

/**
 * Panelin liste karti: kenarlikli baslik satiri, govdede kenardan kenara
 * satirlar. `Card`'in kendi `py-6`+`gap-6`'si sifirlanir, yoksa son satirin
 * altinda beyaz bosluk kalir.
 */
export function ListCard({
    title,
    href,
    badge,
    className,
    children,
}: {
    title: string;
    href?: string;
    /** Baslik satirinin sag ucundaki ozet — "3 aktif", "2 hatalı"… */
    badge?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card className={cn('gap-0 py-0', className)}>
            <CardHeader className="flex-row items-center justify-between gap-2 border-b py-4">
                <CardTitle className="text-sm font-medium">
                    {href ? (
                        <Link
                            href={href}
                            prefetch
                            className="flex items-center gap-1 underline-offset-4 hover:underline"
                        >
                            {title}
                            <ChevronRight className="size-3.5" />
                        </Link>
                    ) : (
                        title
                    )}
                </CardTitle>
                {badge}
            </CardHeader>

            <CardContent className="px-0">
                <ul>{children}</ul>
            </CardContent>
        </Card>
    );
}

/**
 * Liste satiri. Sol serit `tone` ile renklenir; `href` VERILMEZSE satir
 * tiklanmaz — "sorun yok" satirinin gidecegi ekran bos liste gosterirdi.
 */
export function ListRow({
    tone,
    href,
    children,
}: {
    tone?: RowTone;
    href?: string;
    children: ReactNode;
}) {
    const style = tone ? TONE[tone] : undefined;

    const row = (
        <div
            className={cn(
                'flex items-center gap-3 border-l-2 px-4 py-2.5 text-sm',
                style ? `${style.bar} ${style.row}` : 'border-l-transparent',
            )}
        >
            {children}
        </div>
    );

    return (
        <li className="border-b last:border-b-0">
            {href ? (
                <Link
                    href={href}
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
}

/** Satirin sag ucundaki sayi/durum hapi. */
export function RowPill({
    tone,
    className,
    children,
}: {
    tone?: RowTone;
    className?: string;
    children: ReactNode;
}) {
    return (
        <span
            className={cn(
                'ml-auto shrink-0 rounded-full px-2 py-0.5 text-center text-xs font-semibold tabular-nums',
                tone ? TONE[tone].pill : 'text-muted-foreground',
                className,
            )}
        >
            {children}
        </span>
    );
}

/** Satirin ana metni: ustte baslik, altta aciklama. */
export function RowText({
    title,
    detail,
    muted = false,
}: {
    title: ReactNode;
    detail: ReactNode;
    /** Sorunsuz satir one cikmaz. */
    muted?: boolean;
}) {
    return (
        <div className="min-w-0 flex-1">
            <div
                className={cn(
                    'truncate',
                    muted ? 'text-muted-foreground' : 'font-medium',
                )}
            >
                {title}
            </div>
            <div className="truncate text-xs text-muted-foreground">
                {detail}
            </div>
        </div>
    );
}

/** Kart bos oldugunda: satir yerine tek cumle, ayni ic bosluklarla. */
export function ListEmpty({ children }: { children: ReactNode }) {
    return (
        <li className="px-4 py-3 text-sm text-muted-foreground">{children}</li>
    );
}

/** Satir sayisi gercek kartla ayni; veri gelince yerlesim kaymaz. */
export function ListCardSkeleton({
    rows = 4,
    className,
}: {
    rows?: number;
    className?: string;
}) {
    return (
        <Card className={cn('gap-0 py-0', className)}>
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
