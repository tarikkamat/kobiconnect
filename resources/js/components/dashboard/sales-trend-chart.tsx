import type { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import {
    axisLabelStyle,
    baseChartOptions,
    Chart,
    ChartCard,
    formatCompactCurrency,
    formatCurrency,
    formatDay,
    formatFullDay,
    SerieLegend,
    useChartColors,
} from '@/components/dashboard/chart-kit';
import type { ChartSerie } from '@/components/dashboard/chart-kit';
import { index as ordersRoute } from '@/routes/orders';

export type SalesTrend = {
    demo: boolean;
    series: ChartSerie[];
    /** `{ date: '2026-08-22', trendyol: 12345.6, ... }` */
    rows: Record<string, string | number>[];
};

/**
 * Pazaryeri basina gunluk ciro, yigilmis alan (gradyan dolgu, yumusak cizgi).
 * Donem sayfanin ustundeki seciciden gelir; sunucu satirlari kesilmis gonderir.
 */
export function SalesTrendChart({ trend }: { trend: SalesTrend }) {
    const colors = useChartColors();

    const series = useMemo(
        () =>
            trend.series.map((serie) => ({
                name: serie.label,
                data: trend.rows.map((row) => Number(row[serie.key] ?? 0)),
            })),
        [trend],
    );

    const options = useMemo<ApexOptions>(() => {
        const base = baseChartOptions(colors);

        return {
            ...base,
            chart: { ...base.chart, type: 'area', stacked: true },
            colors: trend.series.map(
                (_, index) => colors.palette[index % colors.palette.length],
            ),
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: { opacityFrom: 0.5, opacityTo: 0.05 },
            },
            xaxis: {
                categories: trend.rows.map((row) => String(row.date)),
                axisBorder: { show: false },
                axisTicks: { show: false },
                tickAmount: 6,
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
                    formatter: (value: number) => formatCompactCurrency(value),
                },
            },
            tooltip: {
                shared: true,
                intersect: false,
                /* Kategori ekseninde `value` ISO tarihin kendisidir. */
                x: { formatter: (value) => formatFullDay(String(value)) },
                y: { formatter: (value: number) => formatCurrency(value) },
            },
        };
    }, [colors, trend]);

    return (
        <ChartCard
            title="Satış trendi"
            description="Pazaryeri bazında günlük ciro · seçili dönem"
            href={ordersRoute().url}
            demo={trend.demo}
        >
            {/* 224px grafik + 36px legend = iskeletin 260px'i; kayma olmaz. */}
            <div className="h-56">
                <Chart
                    type="area"
                    options={options}
                    series={series}
                    height={224}
                    width="100%"
                />
            </div>
            <SerieLegend series={trend.series} />
        </ChartCard>
    );
}
