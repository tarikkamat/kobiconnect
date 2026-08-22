import type { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import {
    baseChartOptions,
    Chart,
    ChartCard,
    useChartColors,
} from '@/components/dashboard/chart-kit';
import type { ChartSerie } from '@/components/dashboard/chart-kit';
import { index as ordersRoute } from '@/routes/orders';

export type SalesTarget = {
    demo: boolean;
    /** "Ağustos 2026" — ay adi sunucuda cevrilir. */
    month: string;
    overall: { percent: number; achieved: string; target: string };
    items: (ChartSerie & {
        percent: number;
        achieved: string;
        target: string;
    })[];
};

/**
 * Aylik hedef gerceklesmesi, ic ice halkalar (radialBar). Yuzde 100'de
 * kirpilir: halka tasarsa "hedefin ustunde" bilgisi yerine bozuk bir cizim
 * cikar; gercek tutarlar alttaki listede zaten yazar.
 */
export function SalesTargetChart({ target }: { target: SalesTarget }) {
    const colors = useChartColors();

    const options = useMemo<ApexOptions>(() => {
        const base = baseChartOptions(colors);

        return {
            ...base,
            chart: { ...base.chart, type: 'radialBar' },
            labels: target.items.map((item) => item.label),
            colors: target.items.map(
                (_, index) => colors.palette[index % colors.palette.length],
            ),
            stroke: { lineCap: 'round' },
            plotOptions: {
                radialBar: {
                    hollow: { size: '32%' },
                    track: { background: colors.muted, margin: 4 },
                    dataLabels: {
                        name: {
                            show: true,
                            fontSize: '13px',
                            color: colors.mutedForeground,
                        },
                        value: {
                            show: true,
                            fontSize: '16px',
                            fontWeight: 600,
                            color: colors.foreground,
                            formatter: (value) => `%${Math.round(value)}`,
                        },
                        total: {
                            show: true,
                            label: 'Genel',
                            fontSize: '13px',
                            color: colors.mutedForeground,
                            formatter: () =>
                                `%${Math.round(target.overall.percent)}`,
                        },
                    },
                },
            },
        };
    }, [colors, target]);

    return (
        <ChartCard
            title="Satış hedefi"
            description={`${target.month} · ${target.overall.achieved} / ${target.overall.target}`}
            href={ordersRoute().url}
            demo={target.demo}
        >
            <div className="mx-auto h-60 max-w-[260px]">
                <Chart
                    type="radialBar"
                    options={options}
                    series={target.items.map((item) =>
                        Math.min(100, item.percent),
                    )}
                    height={260}
                    width="100%"
                />
            </div>

            <ul className="mt-2 space-y-1 text-sm">
                {target.items.map((item) => (
                    <li
                        key={item.key}
                        className="flex items-center justify-between gap-2"
                    >
                        <span className="truncate">{item.label}</span>
                        <span className="shrink-0 tabular-nums">
                            {item.achieved}
                            <span className="ml-2 text-muted-foreground">
                                / {item.target}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </ChartCard>
    );
}
