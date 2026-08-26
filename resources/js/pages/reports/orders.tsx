import { Head } from '@inertiajs/react';
import { CheckCircle2, Clock, ShoppingBag, XCircle } from 'lucide-react';
import { ReportHeader } from '@/components/reports/report-header';
import type { ConnectionItem } from '@/components/reports/report-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as reportsRoute, orders as ordersRoute } from '@/routes/reports';

type StatusItem = {
    status: string;
    label: string;
    count: number;
    percentage: string;
    rawPercentage: number;
};

type Props = {
    range: { from: string; to: string };
    filters: { connection: number | null };
    connections: ConnectionItem[];
    totalOrders: number;
    statusDistribution: StatusItem[];
};

function getStatusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' {
    switch (status) {
        case 'delivered':
        case 'invoiced':
            return 'default';
        case 'shipped':
        case 'picking':
        case 'created':
            return 'secondary';
        case 'cancelled':
        case 'returned':
        case 'undelivered':
        case 'unsupplied':
            return 'destructive';
        default:
            return 'outline';
    }
}

export default function ReportsOrders({
    range,
    filters,
    connections,
    totalOrders,
    statusDistribution,
}: Props) {
    const deliveredCount =
        statusDistribution.find((s) => s.status === 'delivered')?.count ?? 0;
    const cancelledOrReturnedCount = statusDistribution
        .filter((s) =>
            ['cancelled', 'returned', 'undelivered', 'unsupplied'].includes(
                s.status,
            ),
        )
        .reduce((sum, item) => sum + item.count, 0);
    const inProgressCount = statusDistribution
        .filter((s) =>
            [
                'created',
                'picking',
                'shipped',
                'at_collection_point',
                'invoiced',
            ].includes(s.status),
        )
        .reduce((sum, item) => sum + item.count, 0);

    return (
        <>
            <Head title="Sipariş Statü Raporu" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <ReportHeader
                    title="Sipariş ve Statü Dağılımı"
                    description="Seçilen dönemdeki siparişlerin operasyonel süreçleri, teslimat ve iptal/iade oranları."
                    activeTab="orders"
                    range={range}
                    filters={filters}
                    connections={connections}
                />

                {/* Status Summary KPI Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card className="gap-0 overflow-hidden border-border bg-card py-0">
                        <CardContent className="px-4 py-4">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Toplam Sipariş
                                </span>
                                <div className="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <ShoppingBag className="size-3.5" />
                                </div>
                            </div>
                            <div className="mt-2 font-mono text-2xl font-bold tracking-tight text-foreground tabular-nums">
                                {totalOrders} adet
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Seçilen dönem toplamı
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="gap-0 overflow-hidden border-border bg-card py-0">
                        <CardContent className="px-4 py-4">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Teslim Edilen
                                </span>
                                <div className="flex size-7 items-center justify-center rounded-md bg-emerald-500/10 text-emerald-500">
                                    <CheckCircle2 className="size-3.5" />
                                </div>
                            </div>
                            <div className="mt-2 font-mono text-2xl font-bold tracking-tight text-emerald-500 tabular-nums">
                                {deliveredCount} adet
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {totalOrders > 0
                                    ? `%${((deliveredCount / totalOrders) * 100).toFixed(1)} teslim oranı`
                                    : 'Kayıt yok'}
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="gap-0 overflow-hidden border-border bg-card py-0">
                        <CardContent className="px-4 py-4">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Süreci Devam Eden
                                </span>
                                <div className="flex size-7 items-center justify-center rounded-md bg-sky-500/10 text-sky-500">
                                    <Clock className="size-3.5" />
                                </div>
                            </div>
                            <div className="mt-2 font-mono text-2xl font-bold tracking-tight text-sky-500 tabular-nums">
                                {inProgressCount} adet
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Hazırlanan / Kargoda
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="gap-0 overflow-hidden border-border bg-card py-0">
                        <CardContent className="px-4 py-4">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    İptal / İade
                                </span>
                                <div className="flex size-7 items-center justify-center rounded-md bg-rose-500/10 text-rose-500">
                                    <XCircle className="size-3.5" />
                                </div>
                            </div>
                            <div className="mt-2 font-mono text-2xl font-bold tracking-tight text-rose-500 tabular-nums">
                                {cancelledOrReturnedCount} adet
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {totalOrders > 0
                                    ? `%${((cancelledOrReturnedCount / totalOrders) * 100).toFixed(1)} kayıp oranı`
                                    : 'Kayıt yok'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Status Distribution Table */}
                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between border-b border-border px-4 py-3">
                        <div className="space-y-1">
                            <h3 className="text-sm font-semibold text-foreground">
                                Tüm Operasyonel Statülerin Dökümü
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Siparişlerin statü bazında adetsel dağılımı ve
                                toplam içindeki payı.
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            className="font-mono text-xs tabular-nums"
                        >
                            {statusDistribution.length} farklı durum
                        </Badge>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border hover:bg-transparent">
                                <TableHead>Statü Adı</TableHead>
                                <TableHead>Teknik Kod</TableHead>
                                <TableHead className="text-right">
                                    Sipariş Adedi
                                </TableHead>
                                <TableHead className="text-right">
                                    Dağılım Oranı
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {statusDistribution.length > 0 ? (
                                statusDistribution.map((item) => (
                                    <TableRow key={item.status}>
                                        <TableCell className="text-xs font-medium">
                                            <Badge
                                                variant={getStatusBadgeVariant(
                                                    item.status,
                                                )}
                                                className="text-xs font-normal"
                                            >
                                                {item.label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {item.status}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs font-semibold text-foreground tabular-nums">
                                            {item.count} adet
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Badge
                                                variant="secondary"
                                                className="font-mono text-xs tabular-nums"
                                            >
                                                %{item.percentage}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="py-12 text-center text-xs text-muted-foreground"
                                    >
                                        Seçilen tarih aralığında sipariş kaydı
                                        bulunamadı.
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

ReportsOrders.layout = {
    breadcrumbs: [
        {
            title: 'Raporlar',
            href: reportsRoute(),
        },
        {
            title: 'Sipariş Statüleri',
            href: ordersRoute(),
        },
    ],
};
