import { Head } from '@inertiajs/react';
import { MarketplaceAvatar } from '@/components/marketplace-avatar';
import {
    ReportHeader,
    type ConnectionItem,
} from '@/components/reports/report-header';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { channels as channelsRoute, index as reportsRoute } from '@/routes/reports';

type ChannelRow = {
    id: number;
    name: string;
    marketplace: string;
    orderCount: number;
    itemCount: number;
    grossSales: string;
    rawGrossSales: number;
    commissionTotal: string;
    rawCommissionTotal: number;
    shippingTotal: string;
    rawShippingTotal: number;
    penaltyTotal: string;
    rawPenaltyTotal: number;
    totalDeductions: string;
    netEarnings: string;
    rawNetEarnings: number;
    avgCommissionRate: string;
    sharePercentage: string;
    rawShare: number;
};

type Kpis = {
    grossSales: string;
    commissionTotal: string;
    netEarnings: string;
    orderCount: number;
    itemCount: number;
};

type Props = {
    range: { from: string; to: string };
    filters: { connection: number | null };
    connections: ConnectionItem[];
    kpis: Kpis;
    channelBreakdown: ChannelRow[];
};

export default function ReportsChannels({
    range,
    filters,
    connections,
    channelBreakdown,
}: Props) {
    return (
        <>
            <Head title="Kanal Performans Raporu" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <ReportHeader
                    title="Pazaryeri ve Kanal Dağılımı"
                    description="Her satış kanalının sipariş hacmi, brüt cirosu, komisyon kesintisi ve net hakediş performansı."
                    activeTab="channels"
                    range={range}
                    filters={filters}
                    connections={connections}
                />

                {/* Channel Breakdown Table */}
                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between border-b border-border px-4 py-3">
                        <div className="space-y-1">
                            <h3 className="text-sm font-semibold text-foreground">
                                Kanal Bazlı Satış & Hakediş Tablosu
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Aktif bağlı pazaryerlerinin seçilen tarih aralığındaki finansal dökümü.
                            </p>
                        </div>
                        <Badge variant="outline" className="font-mono text-xs tabular-nums">
                            {channelBreakdown.length} kanal
                        </Badge>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border hover:bg-transparent">
                                <TableHead className="w-[220px]">Kanal</TableHead>
                                <TableHead className="text-right">Sipariş</TableHead>
                                <TableHead className="text-right">Satılan Ürün</TableHead>
                                <TableHead className="text-right">Brüt Satış</TableHead>
                                <TableHead className="text-right">Komisyon</TableHead>
                                <TableHead className="text-right">Kargo & Cezalar</TableHead>
                                <TableHead className="text-right">Net Hakediş</TableHead>
                                <TableHead className="text-right">Ort. Komisyon</TableHead>
                                <TableHead className="text-right">Ciro Payı</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {channelBreakdown.length > 0 ? (
                                channelBreakdown.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-medium">
                                            <div className="flex items-center gap-2.5">
                                                <MarketplaceAvatar
                                                    marketplace={row.marketplace}
                                                    className="size-5"
                                                />
                                                <div className="flex flex-col">
                                                    <span className="text-xs font-semibold">
                                                        {row.name}
                                                    </span>
                                                    <span className="text-[10px] text-muted-foreground uppercase">
                                                        {row.marketplace}
                                                    </span>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-right tabular-nums">
                                            {row.orderCount}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-right tabular-nums">
                                            {row.itemCount} adet
                                        </TableCell>
                                        <TableCell className="font-mono font-semibold text-xs text-right text-foreground tabular-nums">
                                            {row.grossSales}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-right text-rose-500 tabular-nums">
                                            {row.commissionTotal}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-right text-amber-500 tabular-nums">
                                            {row.penaltyTotal
                                                ? `${row.shippingTotal} (+${row.penaltyTotal})`
                                                : row.shippingTotal}
                                        </TableCell>
                                        <TableCell className="font-mono font-semibold text-xs text-right text-emerald-500 tabular-nums">
                                            {row.netEarnings}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-right tabular-nums">
                                            %{row.avgCommissionRate}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Badge
                                                variant="secondary"
                                                className="font-mono text-[11px] tabular-nums"
                                            >
                                                %{row.sharePercentage}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={9}
                                        className="py-12 text-center text-xs text-muted-foreground"
                                    >
                                        Seçilen tarih aralığında kanal sipariş verisi bulunamadı.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ReportsChannels.layout = {
    breadcrumbs: [
        {
            title: 'Raporlar',
            href: reportsRoute(),
        },
        {
            title: 'Kanal Dağılımı',
            href: channelsRoute(),
        },
    ],
};
