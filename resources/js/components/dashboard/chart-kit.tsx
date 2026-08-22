import { Link } from '@inertiajs/react';
import type { ApexOptions } from 'apexcharts';
import { ChevronRight } from 'lucide-react';
import { lazy, Suspense, useMemo } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { AppIcon } from '@/components/channels/app-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardHeading,
    CardTitle,
    CardToolbar,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

/** Backend'in her grafik prop'unda gonderdigi seri meta'si. */
export type ChartSerie = {
    key: string;
    label: string;
    logo: string;
};

/**
 * react-apexcharts modul yuklenirken window'a dokunur; lazy import bunu ilk
 * render'a erteler, modul degerlendirmesi bilesen disinda calismaz.
 */
const LazyApexChart = lazy(() => import('react-apexcharts'));

/**
 * Tum grafiklerin girdigi tek kapi. Apex, SVG'ye cozumlenmis renk yazar ve CSS
 * degiskeni okuyamaz; tema degisince `key={resolvedAppearance}` ile remount
 * edilir ki `useChartColors`'in yeniden cozdugu renkler bastan cizilsin.
 */
export function Chart(props: ComponentProps<typeof LazyApexChart>) {
    const { resolvedAppearance } = useAppearance();

    return (
        <Suspense fallback={null}>
            <LazyApexChart key={resolvedAppearance} {...props} />
        </Suspense>
    );
}

/** `--chart-1..5` + tema token'lari, Apex'in anlayacagi hex/rgb'ye cozulmus. */
export type ChartColors = {
    palette: string[];
    border: string;
    muted: string;
    mutedForeground: string;
    foreground: string;
};

const FALLBACK_COLORS: ChartColors = {
    palette: ['#3b82f6', '#22c55e', '#eab308', '#ef4444', '#a855f7'],
    border: '#e4e4e7',
    muted: '#f4f4f5',
    mutedForeground: '#71717a',
    foreground: '#09090b',
};

/**
 * Seri renkleri MARKA renklerinden degil, `--chart-1..5` paletinden gelir:
 * Trendyol #F27A1A, Hepsiburada #FF6000, n11 #E62E2D — ucu de turuncu/kirmizi,
 * ayni grafikte birbirinden ayirt edilemez ve koyu temada kontrasti duser.
 * Kimlik renkten degil, legend/tooltip'teki LOGODAN okunur.
 */
export function paletteColor(colors: ChartColors, index: number): string {
    return colors.palette[index % colors.palette.length];
}

/**
 * Token'lar oklch tanimli; Apex gradyan/golge hesaplari icin rengi parse etmek
 * zorunda ve oklch'i anlamaz. Canvas `fillStyle` her CSS rengini hex/rgb'ye
 * serilestirir — cozumleme oradan yapilir. Tema degisince (`.dark` sinifi
 * coktan uygulanmistir) renkler yeniden cozulur, grafikler `Chart` icindeki
 * key ile remount olur.
 */
export function useChartColors(): ChartColors {
    const { resolvedAppearance } = useAppearance();

    return useMemo(() => {
        if (typeof document === 'undefined') {
            return FALLBACK_COLORS;
        }

        const styles = getComputedStyle(document.documentElement);
        const context = document.createElement('canvas').getContext('2d');

        if (context === null) {
            return FALLBACK_COLORS;
        }

        const resolve = (token: string, fallback: string): string => {
            const raw = styles.getPropertyValue(token).trim();

            if (raw === '') {
                return fallback;
            }

            context.fillStyle = fallback;
            context.fillStyle = raw;

            return String(context.fillStyle);
        };

        return {
            palette: [
                '--chart-1',
                '--chart-2',
                '--chart-3',
                '--chart-4',
                '--chart-5',
            ].map((token, index) =>
                resolve(token, FALLBACK_COLORS.palette[index]),
            ),
            border: resolve('--border', FALLBACK_COLORS.border),
            muted: resolve('--muted', FALLBACK_COLORS.muted),
            mutedForeground: resolve(
                '--muted-foreground',
                FALLBACK_COLORS.mutedForeground,
            ),
            foreground: resolve('--foreground', FALLBACK_COLORS.foreground),
        };
    }, [resolvedAppearance]);
}

/** Eksen etiketlerinin ortak stili — Apex `labels.style` alanlarina gider. */
export function axisLabelStyle(colors: ChartColors): {
    colors: string;
    fontSize: string;
} {
    return { colors: colors.mutedForeground, fontSize: '12px' };
}

/**
 * Ortak Apex tabani: font kart fontunu miras alir, toolbar/zoom yok, grid
 * cizgileri `--border` tonunda kesikli. Eksen/tooltip her grafik kendi veri
 * tipine gore tanimlar.
 */
export function baseChartOptions(colors: ChartColors): ApexOptions {
    return {
        chart: {
            fontFamily: 'inherit',
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            parentHeightOffset: 0,
        },
        dataLabels: { enabled: false },
        legend: { show: false },
        grid: {
            borderColor: colors.border,
            strokeDashArray: 4,
            xaxis: { lines: { show: false } },
        },
    };
}

/**
 * Bicimlendirme kurali geregi para SUNUCUDA bicimlenir (FRONTEND-PLAN §7);
 * grafik EKSENI ve tooltip'i bunun istisnasidir: kac tick cizilecegi ekran
 * genisligine, tooltip icerigi gezilen noktaya bagli oldugu icin sunucu
 * onlari onceden bicimleyemez. Sozel toplamlar yine sunucudan hazir gelir.
 */
const compact = new Intl.NumberFormat('tr-TR', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const count = new Intl.NumberFormat('tr-TR');

const currency = new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
    maximumFractionDigits: 0,
});

export function formatCompactCurrency(value: number): string {
    return `₺${compact.format(value)}`;
}

export function formatCurrency(value: number): string {
    return currency.format(value);
}

export function formatCount(value: number): string {
    return count.format(value);
}

export function formatDay(iso: string): string {
    return new Date(iso).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'short',
    });
}

export function formatFullDay(iso: string): string {
    return new Date(iso).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

/**
 * Apex'in HTML legend'i yerine kendi legend'imiz: pazaryeri LOGOSU gosterir.
 * `AppIcon`'un beyaz zemin + ince ring'i koyu wordmark'lar koyu temada
 * kaybolmasin diye var, kucuk boyda da ayni isi gorur.
 */
export function SerieLegend({ series }: { series: ChartSerie[] }) {
    return (
        <div className="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
            {series.map((serie) => (
                <span key={serie.key} className="flex items-center gap-1.5">
                    <AppIcon
                        app={{ logo: serie.logo, name: serie.label }}
                        className="size-5 shrink-0 rounded-md p-0.5 ring-1 ring-border"
                    />
                    {serie.label}
                </span>
            ))}
        </div>
    );
}

/**
 * Grafik karti — Metronic/ReUI kart iskeleti: kenarlikli baslik satiri,
 * govdede grafik. `WidgetCard`'in genis tiklama alani BURADA YOK: Apex kartin
 * icini interaktif kullaniyor, `after:inset-0` tooltip'i yutardi. Baslik yine
 * kendi ekranina goturur — "sayi gostermek tek basina ise yaramaz".
 */
export function ChartCard({
    title,
    description,
    href,
    demo = false,
    action,
    className,
    children,
}: {
    title: string;
    description?: string;
    href?: string;
    demo?: boolean;
    action?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <Card className={className}>
            <CardHeader className="py-3.5">
                <CardHeading>
                    <CardTitle className="flex items-center gap-2">
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
                        {demo && (
                            <Badge
                                variant="outline"
                                className="font-normal text-muted-foreground"
                            >
                                Örnek veri
                            </Badge>
                        )}
                    </CardTitle>
                    {description && (
                        <CardDescription>{description}</CardDescription>
                    )}
                </CardHeading>
                {action && <CardToolbar>{action}</CardToolbar>}
            </CardHeader>
            <CardContent className="px-2.5 pt-4 pb-3 sm:px-5">
                {children}
            </CardContent>
        </Card>
    );
}

/**
 * Grafik kartinin iskeleti. Baslik satirlari `ChartCard`'in gercek satir
 * yukseklikleriyle (16px baslik, 20px aciklama) birebir ayni; govde yuksekligi
 * grafigin sabit yuksekligine, `rows` grafik altindaki listeye karsilik
 * gelir — veri gelince sayfa kaymaz.
 */
export function ChartSkeleton({
    height = 260,
    rows = 0,
    className,
}: {
    height?: number;
    rows?: number;
    className?: string;
}) {
    return (
        <Card className={className}>
            <CardHeader className="py-3.5">
                <div className="space-y-1">
                    <Skeleton className="h-4 w-40" />
                    <Skeleton className="h-5 w-56" />
                </div>
            </CardHeader>
            <CardContent className="px-2.5 pt-4 pb-3 sm:px-5">
                <Skeleton className="w-full" style={{ height }} />
                {rows > 0 && (
                    <div className="mt-2 space-y-1">
                        {Array.from({ length: rows }, (_, slot) => (
                            <Skeleton key={slot} className="h-6 w-full" />
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
