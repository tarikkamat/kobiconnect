import type { ApexOptions } from 'apexcharts';
import { TrendingDown, TrendingUp } from 'lucide-react';
import { Chart, useChartColors } from '@/components/dashboard/chart-kit';
import type { ChartColors } from '@/components/dashboard/chart-kit';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type Kpis = {
    demo: boolean;
    items: {
        key: string;
        label: string;
        /** Sunucuda bicimlenmis — para/yuzde/adet ayrimi burada bilinmez. */
        value: string;
        delta: string;
        rising: boolean;
        /** Yon IYI mi: iade artarsa ok yukari ama renk kirmizi. */
        positive: boolean;
        spark: { index: number; value: number }[];
    }[];
};

/**
 * Sparkline: eksensiz, tooltipsiz mini alan grafigi — burada okunacak sey yon,
 * deger degil. Renk semantiktir: iyi giden `--chart-2` (yesil), kotu giden
 * `--chart-4` (kirmizi).
 */
function sparkOptions(colors: ChartColors, positive: boolean): ApexOptions {
    return {
        chart: {
            type: 'area',
            sparkline: { enabled: true },
            background: 'transparent',
            animations: { enabled: false },
            parentHeightOffset: 0,
        },
        colors: [positive ? colors.palette[1] : colors.palette[3]],
        stroke: { curve: 'smooth', width: 1.5 },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.02 },
        },
        tooltip: { enabled: false },
    };
}

/**
 * Ust serit — dort ozet sayi, her biri son 12 haftanin mini cizgisiyle.
 * Kartin alt padding'i YOK: sparkline alt kenara yapisik biter, altinda bos
 * serit kalmaz.
 */
export function KpiStrip({ kpis }: { kpis: Kpis }) {
    const colors = useChartColors();

    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {kpis.items.map((item, index) => (
                <Card key={item.key} className="overflow-hidden">
                    <CardContent className="px-4 pt-4 pb-0">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-sm text-muted-foreground">
                                {item.label}
                            </p>
                            <Badge
                                variant="outline"
                                className={cn(
                                    'tabular-nums',
                                    item.positive
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-rose-600 dark:text-rose-400',
                                )}
                            >
                                {item.rising ? (
                                    <TrendingUp aria-hidden />
                                ) : (
                                    <TrendingDown aria-hidden />
                                )}
                                {item.delta}
                            </Badge>
                        </div>

                        <p className="mt-1.5 text-2xl font-semibold tabular-nums">
                            {item.value}
                        </p>

                        <p className="mt-0.5 text-xs text-muted-foreground">
                            önceki döneme göre
                            {kpis.demo && index === 0 && ' · örnek veri'}
                        </p>
                    </CardContent>

                    <div className="mt-3 h-14">
                        <Chart
                            type="area"
                            options={sparkOptions(colors, item.positive)}
                            series={[
                                {
                                    name: item.label,
                                    data: item.spark.map(
                                        (point) => point.value,
                                    ),
                                },
                            ]}
                            height={56}
                            width="100%"
                        />
                    </div>
                </Card>
            ))}
        </div>
    );
}

/** KPI kartıyla birebir aynı yükseklikte iskelet — veri gelince kayma olmaz. */
export function KpiStripSkeleton() {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            {[0, 1, 2, 3].map((slot) => (
                <Card key={slot} className="overflow-hidden">
                    <CardContent className="px-4 pt-4 pb-0">
                        <div className="flex items-start justify-between gap-2">
                            <Skeleton className="h-5 w-20" />
                            <Skeleton className="h-[22px] w-16 rounded-md" />
                        </div>
                        <Skeleton className="mt-1.5 h-8 w-28" />
                        <Skeleton className="mt-0.5 h-4 w-32" />
                    </CardContent>
                    <Skeleton className="mt-3 h-14 w-full rounded-none" />
                </Card>
            ))}
        </div>
    );
}
