import type { ApexOptions } from 'apexcharts';
import { TrendingDown, TrendingUp } from 'lucide-react';
import {
    Chart,
    NEGATIVE_COLOR,
    useChartColors,
} from '@/components/dashboard/chart-kit';
import type { ChartColors } from '@/components/dashboard/chart-kit';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

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
 * deger degil. Renk semantiktir: iyi giden mint, kotu giden §7'nin hata rengi.
 * Dolgu cizginin kendi rengini seyreltir, gradyan yok.
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
        colors: [positive ? colors.palette[0] : NEGATIVE_COLOR],
        stroke: { curve: 'smooth', width: 1.5 },
        fill: { type: 'solid', opacity: 0.14 },
        tooltip: { enabled: false },
        grid: {
            show: false,
            padding: {
                top: 0,
                right: 0,
                bottom: 0,
                left: 0,
            },
        },
        xaxis: {
            crosshairs: { show: false },
            tooltip: { enabled: false },
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            show: false,
            min: 0,
            max: (max) => (max > 0 ? max * 1.05 : 1),
            labels: { show: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
            padding: {
                top: 0,
                bottom: 0,
            },
        },
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
                <Card key={item.key} className="gap-0 overflow-hidden py-0 border-border bg-card">
                    <CardContent className="px-4 pt-4 pb-0">
                        <div className="flex items-start justify-between gap-2">
                            <p className="text-sm text-muted-foreground">
                                {item.label}
                            </p>
                            <Badge
                                variant={
                                    item.positive ? 'success' : 'destructive'
                                }
                                className="font-mono tabular-nums"
                            >
                                {item.rising ? (
                                    <TrendingUp aria-hidden />
                                ) : (
                                    <TrendingDown aria-hidden />
                                )}
                                {item.delta}
                            </Badge>
                        </div>

                        <p className="mt-1.5 font-mono text-[28px] leading-none font-medium tabular-nums text-foreground">
                            {item.value}
                        </p>

                        <p className="mt-0.5 text-xs text-muted-foreground">
                            önceki döneme göre
                            {kpis.demo && index === 0 && ' · örnek veri'}
                        </p>
                    </CardContent>

                    <div className="mt-3 h-14 overflow-hidden [&_.apexcharts-canvas]:!block [&_.apexcharts-canvas_svg]:!block [&_.apexcharts-canvas_svg]:!w-full [&_.apexcharts-canvas_svg]:!h-[56px]">
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
                <Card key={slot} className="gap-0 overflow-hidden py-0 border-border bg-card">
                    <CardContent className="px-4 pt-4 pb-0">
                        <div className="flex items-start justify-between gap-2">
                            <Skeleton className="h-5 w-20" />
                            <Skeleton className="h-[22px] w-16 rounded-md" />
                        </div>
                        <Skeleton className="mt-1.5 h-7 w-28" />
                        <Skeleton className="mt-0.5 h-4 w-32" />
                    </CardContent>
                    <Skeleton className="mt-3 h-14 w-full rounded-none" />
                </Card>
            ))}
        </div>
    );
}
