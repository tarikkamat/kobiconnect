import type { ApexOptions } from 'apexcharts';
import { useMemo, useState } from 'react';
import {
    axisLabelStyle,
    baseChartOptions,
    Chart,
    formatCompactCurrency,
    formatCount,
    formatCurrency,
    formatDay,
    formatFullDay,
    NEGATIVE_COLOR,
    useChartColors,
} from '@/components/dashboard/chart-kit';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type OrderVolume = {
    demo: boolean;
    /** Sunucuda bicimlenmis 90 gunluk toplamlar. */
    totals: { orders: string; revenue: string; returns: string };
    rows: { date: string; orders: number; revenue: number; returns: number }[];
};

type Metric = 'orders' | 'revenue' | 'returns';

/**
 * Iade "kotu" metriktir ve palet bastan sona yesil-sari; rengini paletten
 * degil §7'nin hata renginden alir — `palette: null` bunu isaretler.
 */
const CONFIG: Record<Metric, { label: string; palette: number | null }> = {
    orders: { label: 'Sipariş', palette: 0 },
    revenue: { label: 'Ciro', palette: 1 },
    returns: { label: 'İade', palette: null },
};

const METRICS: Metric[] = ['orders', 'revenue', 'returns'];

/**
 * Gunluk hacim, sutun grafik. Uc metrik AYNI bar'da donusumlu gosterilir; yan
 * yana uc grafik yerine tek grafik + ustte toplamlar, cunku karsilastirilan
 * sey ayni gun.
 */
export function OrderVolumeChart({
    volume,
    className,
}: {
    volume: OrderVolume;
    className?: string;
}) {
    const [metric, setMetric] = useState<Metric>('orders');
    const colors = useChartColors();

    const options = useMemo<ApexOptions>(() => {
        const base = baseChartOptions(colors);
        const isRevenue = metric === 'revenue';
        const slot = CONFIG[metric].palette;

        return {
            ...base,
            chart: { ...base.chart, type: 'bar' },
            colors: [slot === null ? NEGATIVE_COLOR : colors.palette[slot]],
            plotOptions: {
                bar: {
                    columnWidth: '55%',
                    borderRadius: 2,
                    borderRadiusApplication: 'end',
                },
            },
            xaxis: {
                categories: volume.rows.map((row) => row.date),
                axisBorder: { show: false },
                axisTicks: { show: false },
                tickAmount: 8,
                labels: {
                    rotate: 0,
                    style: axisLabelStyle(colors),
                    formatter: (value: string) => formatDay(value),
                },
                tooltip: { enabled: false },
            },
            yaxis: {
                labels: {
                    style: axisLabelStyle(colors),
                    /* Iade gibi kucuk sayimlarda kusuratli tick ("0,8")
                       uremesin; para ekseninde serbest. */
                    formatter: (value: number) =>
                        isRevenue
                            ? formatCompactCurrency(value)
                            : formatCount(Math.round(value)),
                },
            },
            tooltip: {
                ...base.tooltip,
                x: { formatter: (value) => formatFullDay(String(value)) },
                y: {
                    formatter: (value: number) =>
                        isRevenue ? formatCurrency(value) : formatCount(value),
                },
            },
        };
    }, [colors, metric, volume]);

    const series = useMemo(
        () => [
            {
                name: CONFIG[metric].label,
                data: volume.rows.map((row) => row[metric]),
            },
        ],
        [metric, volume],
    );

    return (
        <Card className={cn('gap-0 py-0', className)}>
            <div className="flex flex-col items-stretch border-b sm:flex-row">
                <div className="flex flex-1 flex-col justify-center gap-1 px-5 pt-4 pb-3 sm:py-5">
                    <CardTitle className="flex items-center gap-2">
                        Sipariş hacmi
                        {volume.demo && (
                            <Badge
                                variant="outline"
                                className="font-normal text-muted-foreground"
                            >
                                Örnek veri
                            </Badge>
                        )}
                    </CardTitle>
                    <CardDescription>Son 90 gün, günlük</CardDescription>
                </div>

                <div className="flex">
                    {METRICS.map((key) => (
                        <button
                            key={key}
                            type="button"
                            data-active={metric === key}
                            onClick={() => setMetric(key)}
                            className={cn(
                                'flex flex-1 flex-col justify-center gap-1 border-t px-4 py-3 text-left transition-colors duration-200',
                                'even:border-l data-[active=true]:bg-muted sm:border-t-0 sm:border-l sm:px-5 sm:py-4',
                                'last:border-l',
                            )}
                        >
                            <span className="text-xs text-muted-foreground">
                                {CONFIG[key].label}
                            </span>
                            <span className="font-mono text-lg leading-none font-medium tabular-nums sm:text-2xl">
                                {volume.totals[key]}
                            </span>
                        </button>
                    ))}
                </div>
            </div>

            <CardContent className="px-2.5 py-4 sm:px-5">
                <div className="h-60">
                    <Chart
                        type="bar"
                        options={options}
                        series={series}
                        height={240}
                        width="100%"
                    />
                </div>
            </CardContent>
        </Card>
    );
}

/**
 * Bu kartin basligi genel `ChartSkeleton`'dan farkli (kenarlikli baslik +
 * uc toplam kutusu); ayni iskeletle beklerse veri gelince yerlesim kayar.
 */
export function OrderVolumeSkeleton({ className }: { className?: string }) {
    return (
        <Card className={cn('gap-0 py-0', className)}>
            <div className="flex flex-col items-stretch border-b sm:flex-row">
                <div className="flex flex-1 flex-col justify-center gap-1 px-5 pt-4 pb-3 sm:py-5">
                    <Skeleton className="h-6 w-32" />
                    <Skeleton className="h-5 w-28" />
                </div>
                <div className="flex">
                    {METRICS.map((key) => (
                        <div
                            key={key}
                            className="flex flex-1 flex-col justify-center gap-1 border-t px-4 py-3 sm:border-t-0 sm:border-l sm:px-5 sm:py-4"
                        >
                            <Skeleton className="h-4 w-10" />
                            <Skeleton className="h-[18px] w-14 sm:h-6" />
                        </div>
                    ))}
                </div>
            </div>
            <div className="px-2.5 py-4 sm:px-5">
                <Skeleton className="h-60 w-full" />
            </div>
        </Card>
    );
}
