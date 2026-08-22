import type { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import {
    baseChartOptions,
    Chart,
    ChartCard,
    formatCurrency,
    useChartColors,
} from '@/components/dashboard/chart-kit';
import type { ChartSerie } from '@/components/dashboard/chart-kit';
import { MarketplaceAvatar } from '@/components/marketplace-avatar';
import { index as connectionsRoute } from '@/routes/apps';

export type ChannelShare = {
    demo: boolean;
    /** Sunucuda bicimlenmis 30 gunluk toplam. */
    total: string;
    items: (ChartSerie & {
        value: number;
        formatted: string;
        share: string;
    })[];
};

/**
 * Kanal payi — hangi pazaryeri cirosu tasiyor, halka (donut) grafik. Dilim
 * degerleri satis trendinin son 30 gununun toplamiyla birebir tutar: ikisi de
 * ayni matristen gelir. Legend yerine alttaki logolu liste okunur.
 */
export function ChannelShareChart({
    share,
    className,
}: {
    share: ChannelShare;
    className?: string;
}) {
    const colors = useChartColors();

    const options = useMemo<ApexOptions>(() => {
        const base = baseChartOptions(colors);

        return {
            ...base,
            chart: { ...base.chart, type: 'donut' },
            labels: share.items.map((item) => item.label),
            colors: share.items.map(
                (_, index) => colors.palette[index % colors.palette.length],
            ),
            stroke: { colors: ['transparent'] },
            plotOptions: {
                pie: {
                    donut: {
                        size: '72%',
                        labels: {
                            show: true,
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
                                formatter: (value) =>
                                    formatCurrency(Number(value)),
                            },
                            total: {
                                show: true,
                                label: 'Toplam',
                                fontSize: '13px',
                                color: colors.mutedForeground,
                                /* Toplam sunucuda bicimlenmis gelir. */
                                formatter: () => share.total,
                            },
                        },
                    },
                },
            },
            tooltip: {
                y: {
                    formatter: (_value, opts) =>
                        share.items[opts?.seriesIndex ?? 0]?.formatted ?? '',
                },
            },
        };
    }, [colors, share]);

    return (
        <ChartCard
            className={className}
            title="Kanal payı"
            description={`Seçili dönem · ${share.total}`}
            href={connectionsRoute().url}
            demo={share.demo}
        >
            <div className="mx-auto h-60 max-w-[260px]">
                <Chart
                    type="donut"
                    options={options}
                    series={share.items.map((item) => item.value)}
                    height={240}
                    width="100%"
                />
            </div>

            <ul className="mt-2 space-y-1 text-sm">
                {share.items.map((item) => (
                    <li
                        key={item.key}
                        className="flex items-center justify-between gap-2"
                    >
                        <span className="flex min-w-0 items-center gap-2">
                            <MarketplaceAvatar
                                code={item.key}
                                name={item.label}
                                size="sm"
                            />
                            <span className="truncate">{item.label}</span>
                        </span>
                        <span className="shrink-0 tabular-nums">
                            {item.formatted}
                            <span className="ml-2 text-muted-foreground">
                                {item.share}
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </ChartCard>
    );
}
