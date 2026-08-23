import { Head } from '@inertiajs/react';
import type { ApexOptions } from 'apexcharts';
import { useMemo } from 'react';
import {
    axisLabelStyle,
    baseChartOptions,
    Chart,
    ChartCard,
    type ChartColors,
    formatCompactCurrency,
    formatCurrency,
    formatDay,
    formatFullDay,
    NEGATIVE_COLOR,
    useChartColors,
} from '@/components/dashboard/chart-kit';
import {
    ReportHeader,
    type ConnectionItem,
} from '@/components/reports/report-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { index as reportsRoute } from '@/routes/reports';

type Kpis = {
    rawGrossSales: number;
    grossSales: string;
    commissionTotal: string;
    rawCommissionTotal: number;
    shippingTotal: string;
    rawShippingTotal: number;
    cargoPenaltyTotal: string;
    rawCargoPenaltyTotal: number;
    latePenaltyTotal: string;
    rawLatePenaltyTotal: number;
    totalPenalties: string;
    rawTotalPenalties: number;
    totalDeductions: string;
    rawTotalDeductions: number;
    netEarnings: string;
    rawNetEarnings: number;
    orderCount: number;
    itemCount: number;
    avgOrderValue: string;
    avgCommissionRate: string;
};

type TrendRow = {
    date: string;
    formattedDate: string;
    orderCount: number;
    grossSales: string;
    rawGrossSales: number;
    commissionTotal: string;
    rawCommissionTotal: number;
    shippingAndPenalty: string;
    rawShippingAndPenalty: number;
    totalDeductions: string;
    rawTotalDeductions: number;
    netEarnings: string;
    rawNetEarnings: number;
};

type Props = {
    range: { from: string; to: string };
    filters: { connection: number | null };
    connections: ConnectionItem[];
    kpis: Kpis;
    salesTrend: TrendRow[];
};

function sparkOptions(
    colors: ChartColors,
    positive: boolean,
    customColor?: string,
): ApexOptions {
    return {
        chart: {
            type: 'area',
            sparkline: { enabled: true },
            background: 'transparent',
            animations: { enabled: false },
            parentHeightOffset: 0,
        },
        colors: [customColor ?? (positive ? colors.palette[0] : NEGATIVE_COLOR)],
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

export default function ReportsIndex({
    range,
    filters,
    connections,
    kpis,
    salesTrend,
}: Props) {
    const colors = useChartColors();

    const chartOptions = useMemo<ApexOptions>(() => {
        const base = baseChartOptions(colors);

        return {
            ...base,
            chart: {
                ...base.chart,
                type: 'area',
                toolbar: { show: false },
                parentHeightOffset: 0,
            },
            colors: ['#18e299', '#f04438', '#f59e0b', '#38bdf8'],
            stroke: { curve: 'smooth', width: 2 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 90, 100],
                },
            },
            grid: {
                ...base.grid,
                padding: {
                    top: 0,
                    right: 12,
                    bottom: 0,
                    left: 12,
                },
            },
            xaxis: {
                categories: salesTrend.map((row) => row.date),
                axisBorder: { show: false },
                axisTicks: { show: false },
                tickAmount: 6,
                labels: {
                    rotate: 0,
                    style: axisLabelStyle(colors),
                    formatter: (val: string) => formatDay(val),
                },
                tooltip: { enabled: false },
            },
            yaxis: {
                labels: {
                    style: axisLabelStyle(colors),
                    formatter: (val: number) => formatCompactCurrency(val),
                },
            },
            tooltip: {
                ...base.tooltip,
                shared: true,
                intersect: false,
                x: { formatter: (val) => formatFullDay(String(val)) },
                y: { formatter: (val: number) => formatCurrency(val) },
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'right',
                labels: { colors: colors.mutedForeground },
                itemMargin: { horizontal: 8, vertical: 0 },
            },
        };
    }, [colors, salesTrend]);

    const chartSeries = useMemo(() => {
        return [
            {
                name: 'Brüt Satış',
                data: salesTrend.map((row) => row.rawGrossSales),
            },
            {
                name: 'Komisyon Kesintisi',
                data: salesTrend.map((row) => row.rawCommissionTotal),
            },
            {
                name: 'Kargo & Cezalar',
                data: salesTrend.map((row) => row.rawShippingAndPenalty),
            },
            {
                name: 'Net Kazanç',
                data: salesTrend.map((row) => row.rawNetEarnings),
            },
        ];
    }, [salesTrend]);

    const totalShippingAndPenalties = kpis.rawShippingTotal + kpis.rawTotalPenalties;

    return (
        <>
            <Head title="Finans ve Satış Raporu" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <ReportHeader
                    title="Finans ve Satış Raporu"
                    description="Brüt satış cironuz, komisyon ve lojistik kesintileri ile net hakediş trendi."
                    activeTab="index"
                    range={range}
                    filters={filters}
                    connections={connections}
                />

                {/* Dashboard-style KPI Strip with Sparklines */}
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {/* Gross Sales */}
                    <Card className="gap-0 overflow-hidden py-0 border-border bg-card">
                        <CardContent className="px-4 pt-4 pb-0">
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm text-muted-foreground">
                                    Toplam Brüt Satış
                                </p>
                                <Badge variant="success" className="font-mono tabular-nums">
                                    {kpis.orderCount} sipariş
                                </Badge>
                            </div>

                            <p className="mt-1.5 font-mono text-[28px] leading-none font-medium tabular-nums text-foreground">
                                {kpis.grossSales}
                            </p>

                            <p className="mt-1 text-xs text-muted-foreground">
                                {kpis.itemCount} adet ürün satışı
                            </p>
                        </CardContent>

                        <div className="mt-3 h-14 overflow-hidden [&_.apexcharts-canvas]:!block [&_.apexcharts-canvas_svg]:!block [&_.apexcharts-canvas_svg]:!w-full [&_.apexcharts-canvas_svg]:!h-[56px]">
                            <Chart
                                type="area"
                                options={sparkOptions(colors, true, colors.palette[0])}
                                series={[
                                    {
                                        name: 'Brüt Satış',
                                        data: salesTrend.map((r) => r.rawGrossSales),
                                    },
                                ]}
                                height={56}
                                width="100%"
                            />
                        </div>
                    </Card>

                    {/* Marketplace Commission */}
                    <Card className="gap-0 overflow-hidden py-0 border-border bg-card">
                        <CardContent className="px-4 pt-4 pb-0">
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm text-muted-foreground">
                                    Pazaryeri Komisyonu
                                </p>
                                <Badge variant="destructive" className="font-mono tabular-nums">
                                    %{kpis.avgCommissionRate}
                                </Badge>
                            </div>

                            <p className="mt-1.5 font-mono text-[28px] leading-none font-medium tabular-nums text-rose-500">
                                {kpis.commissionTotal}
                            </p>

                            <p className="mt-1 text-xs text-muted-foreground">
                                Ortalama komisyon kesintisi
                            </p>
                        </CardContent>

                        <div className="mt-3 h-14 overflow-hidden [&_.apexcharts-canvas]:!block [&_.apexcharts-canvas_svg]:!block [&_.apexcharts-canvas_svg]:!w-full [&_.apexcharts-canvas_svg]:!h-[56px]">
                            <Chart
                                type="area"
                                options={sparkOptions(colors, false, '#f04438')}
                                series={[
                                    {
                                        name: 'Komisyon',
                                        data: salesTrend.map((r) => r.rawCommissionTotal),
                                    },
                                ]}
                                height={56}
                                width="100%"
                            />
                        </div>
                    </Card>

                    {/* Shipping & Penalties */}
                    <Card className="gap-0 overflow-hidden py-0 border-border bg-card">
                        <CardContent className="px-4 pt-4 pb-0">
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm text-muted-foreground">
                                    Kargo & Cezalar
                                </p>
                                {kpis.rawTotalPenalties > 0 ? (
                                    <Badge variant="destructive" className="font-mono tabular-nums">
                                        Ceza: {kpis.totalPenalties}
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary" className="font-mono tabular-nums">
                                        Kargo
                                    </Badge>
                                )}
                            </div>

                            <p className="mt-1.5 font-mono text-[28px] leading-none font-medium tabular-nums text-amber-500">
                                {formatCurrency(totalShippingAndPenalties)}
                            </p>

                            <p className="mt-1 text-xs text-muted-foreground truncate">
                                Kargo: {kpis.shippingTotal}
                            </p>
                        </CardContent>

                        <div className="mt-3 h-14 overflow-hidden [&_.apexcharts-canvas]:!block [&_.apexcharts-canvas_svg]:!block [&_.apexcharts-canvas_svg]:!w-full [&_.apexcharts-canvas_svg]:!h-[56px]">
                            <Chart
                                type="area"
                                options={sparkOptions(colors, false, '#f59e0b')}
                                series={[
                                    {
                                        name: 'Kargo & Ceza',
                                        data: salesTrend.map((r) => r.rawShippingAndPenalty),
                                    },
                                ]}
                                height={56}
                                width="100%"
                            />
                        </div>
                    </Card>

                    {/* Net Earnings */}
                    <Card className="gap-0 overflow-hidden py-0 border-border bg-card">
                        <CardContent className="px-4 pt-4 pb-0">
                            <div className="flex items-start justify-between gap-2">
                                <p className="text-sm text-muted-foreground">
                                    Net Hakediş (Kazanç)
                                </p>
                                <Badge variant="success" className="font-mono tabular-nums">
                                    Sepet: {kpis.avgOrderValue}
                                </Badge>
                            </div>

                            <p className="mt-1.5 font-mono text-[28px] leading-none font-medium tabular-nums text-emerald-500">
                                {kpis.netEarnings}
                            </p>

                            <p className="mt-1 text-xs text-muted-foreground">
                                Brüt satış eksi tüm kesintiler
                            </p>
                        </CardContent>

                        <div className="mt-3 h-14 overflow-hidden [&_.apexcharts-canvas]:!block [&_.apexcharts-canvas_svg]:!block [&_.apexcharts-canvas_svg]:!w-full [&_.apexcharts-canvas_svg]:!h-[56px]">
                            <Chart
                                type="area"
                                options={sparkOptions(colors, true, '#18e299')}
                                series={[
                                    {
                                        name: 'Net Kazanç',
                                        data: salesTrend.map((r) => r.rawNetEarnings),
                                    },
                                ]}
                                height={56}
                                width="100%"
                            />
                        </div>
                    </Card>
                </div>

                {/* Sales & Deductions Trend Chart */}
                <ChartCard
                    title="Satış, Komisyon ve Ceza Trendi"
                    description="Günlük bazda brüt satış, pazaryeri komisyonu, kargo/ceza kesintisi ve net hakediş grafiği."
                >
                    <div className="h-72 sm:h-80">
                        <Chart
                            type="area"
                            height="100%"
                            options={chartOptions}
                            series={chartSeries}
                        />
                    </div>
                </ChartCard>
            </div>
        </>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Raporlar',
            href: reportsRoute(),
        },
        {
            title: 'Finans ve Satış',
            href: reportsRoute(),
        },
    ],
};
