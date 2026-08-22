import type { ApexOptions } from 'apexcharts';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import { useMemo } from 'react';
import {
    axisLabelStyle,
    baseChartOptions,
    Chart,
    ChartCard,
    formatCount,
    useChartColors,
} from '@/components/dashboard/chart-kit';
import { index as operationsRoute } from '@/routes/sync/operations';

export type SyncThroughput = {
    demo: boolean;
    /** Sunucuda bicimlenmis basarisizlik orani. */
    failureRate: string;
    rows: { week: string; succeeded: number; failed: number }[];
};

/**
 * Haftalik senkron hacmi, yigilmis alan. Legend ikonu LOGO degil lucide:
 * seriler kanal degil DURUM (basarili `--chart-2` yesil / basarisiz
 * `--chart-4` kirmizi), o yuzden marka isareti yaniltirdi.
 */
export function SyncThroughputChart({
    throughput,
}: {
    throughput: SyncThroughput;
}) {
    const colors = useChartColors();

    const options = useMemo<ApexOptions>(() => {
        const base = baseChartOptions(colors);

        return {
            ...base,
            chart: { ...base.chart, type: 'area', stacked: true },
            colors: [colors.palette[1], colors.palette[3]],
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: { opacityFrom: 0.4, opacityTo: 0.05 },
            },
            xaxis: {
                /* Hafta etiketleri sunucudan hazir gelir; bicimlenmez. */
                categories: throughput.rows.map((row) => row.week),
                axisBorder: { show: false },
                axisTicks: { show: false },
                labels: { rotate: 0, style: axisLabelStyle(colors) },
                tooltip: { enabled: false },
            },
            yaxis: {
                labels: {
                    style: axisLabelStyle(colors),
                    formatter: (value: number) =>
                        formatCount(Math.round(value)),
                },
            },
            tooltip: {
                shared: true,
                intersect: false,
                y: { formatter: (value: number) => formatCount(value) },
            },
        };
    }, [colors, throughput]);

    const series = useMemo(
        () => [
            {
                name: 'Başarılı',
                data: throughput.rows.map((row) => row.succeeded),
            },
            {
                name: 'Başarısız',
                data: throughput.rows.map((row) => row.failed),
            },
        ],
        [throughput],
    );

    return (
        <ChartCard
            title="Senkron hacmi"
            description={`Son 12 hafta · başarısızlık ${throughput.failureRate}`}
            href={operationsRoute().url}
            demo={throughput.demo}
        >
            {/* 204px grafik + 36px legend = iskeletin 240px'i; kayma olmaz. */}
            <div className="h-[204px]">
                <Chart
                    type="area"
                    options={options}
                    series={series}
                    height={204}
                    width="100%"
                />
            </div>
            <div className="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                <span className="flex items-center gap-1.5">
                    <CircleCheck
                        className="size-4"
                        style={{ color: colors.palette[1] }}
                        aria-hidden
                    />
                    Başarılı
                </span>
                <span className="flex items-center gap-1.5">
                    <TriangleAlert
                        className="size-4"
                        style={{ color: colors.palette[3] }}
                        aria-hidden
                    />
                    Başarısız
                </span>
            </div>
        </ChartCard>
    );
}
