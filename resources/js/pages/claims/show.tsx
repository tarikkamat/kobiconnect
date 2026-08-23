import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Info, Package, Receipt, ShoppingBag } from 'lucide-react';
import { ClaimStatusBadge } from '@/components/claims/claim-status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/claims';
import { show as orderShow } from '@/routes/orders';

type Props = {
    claim: {
        id: number;
        remoteClaimId: string;
        status: string;
        statusLabel: string;
        externalStatus: string;
        reason: string | null;
        openedAt: string | null;
    };
    order: {
        id: number;
        orderNumber: string;
        packageId: string;
        connection: string | null;
        placedAt: string | null;
    };
    items: {
        id: number;
        remoteItemId: string;
        quantity: number;
        status: string;
        statusLabel: string;
        externalStatus: string;
        reason: string | null;
        sku: string | null;
        barcode: string | null;
        unitPrice: string | null;
    }[];
};

function Field({
    label,
    children,
    mono = false,
}: {
    label: string;
    children: string;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className={mono ? 'text-sm font-mono tabular-nums font-medium text-foreground' : 'text-sm font-medium text-foreground'}>
                {children}
            </dd>
        </div>
    );
}

export default function ClaimShow({ claim, order, items }: Props) {
    return (
        <>
            <Head title={`İade ${claim.remoteClaimId}`} />

            <div className="flex flex-col gap-5 p-4 sm:p-6 lg:p-8 font-sans max-w-7xl mx-auto">
                {/* Top Navigation & Breadcrumbs */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 h-8 gap-1.5 text-muted-foreground hover:text-foreground"
                        >
                            <Link href={index()}>
                                <ArrowLeft className="size-4" />
                                <span>İade Talepleri</span>
                            </Link>
                        </Button>
                        <span className="text-muted-foreground/40">/</span>
                        <span className="font-mono text-sm text-foreground font-semibold">
                            {claim.remoteClaimId}
                        </span>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <Button asChild variant="outline" size="sm" className="h-8 gap-1.5 text-xs">
                            <Link href={orderShow({ order: order.id })}>
                                <ShoppingBag className="size-3.5" />
                                <span>Siparişi Görüntüle</span>
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Main Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 sm:p-5 shadow-xs">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div className="space-y-1.5">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="font-sans text-xl sm:text-2xl font-bold tracking-tight text-foreground">
                                    İade {claim.remoteClaimId}
                                </h1>
                                <ClaimStatusBadge
                                    status={claim.status}
                                    label={claim.statusLabel}
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>
                                    Sipariş No:{' '}
                                    <Link
                                        href={orderShow({ order: order.id })}
                                        className="font-mono font-medium text-foreground underline-offset-4 hover:underline"
                                    >
                                        {order.orderNumber}
                                    </Link>
                                </span>
                                {order.connection && (
                                    <span>
                                        Pazaryeri:{' '}
                                        <span className="font-semibold text-foreground">
                                            {order.connection}
                                        </span>
                                    </span>
                                )}
                                {claim.openedAt && (
                                    <span>
                                        Açılma Tarihi:{' '}
                                        <span className="font-mono text-foreground">
                                            {claim.openedAt}
                                        </span>
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Contextual Warning Note */}
                <div className="flex items-start gap-3 rounded-lg border border-border bg-muted/40 p-3.5 text-xs text-muted-foreground shadow-xs">
                    <Info className="mt-0.5 size-4 shrink-0 text-foreground" />
                    <p>
                        İade onay ve red işlemleri doğrudan pazaryeri paneli üzerinden yapılır; bu ekran senkronize edilen talebin güncel durumunu gösterir.
                    </p>
                </div>

                {/* 2 Cards Grid: Claim Info & Order Info */}
                <div className="grid gap-5 md:grid-cols-2">
                    <Card className="gap-0 py-0 overflow-hidden border-border bg-card shadow-xs">
                        <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                            <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                <Receipt className="size-4 text-muted-foreground" />
                                Talep Bilgisi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-4">
                            <dl className="grid grid-cols-2 gap-4">
                                <Field label="Pazaryeri durumu">
                                    {claim.externalStatus}
                                </Field>
                                <Field label="Açılma Tarihi" mono>
                                    {claim.openedAt ?? '—'}
                                </Field>
                                <div className="col-span-2">
                                    <dt className="text-xs text-muted-foreground">İade Sebebi</dt>
                                    <dd className="text-sm font-medium text-foreground mt-0.5">
                                        {claim.reason ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0 overflow-hidden border-border bg-card shadow-xs">
                        <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                            <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                <ShoppingBag className="size-4 text-muted-foreground" />
                                Bağlı Sipariş Bilgisi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-4">
                            <dl className="grid grid-cols-2 gap-4">
                                <div>
                                    <dt className="text-xs text-muted-foreground">Sipariş Numarası</dt>
                                    <dd className="text-sm">
                                        <Link
                                            href={orderShow({ order: order.id })}
                                            instant
                                            className="font-mono font-bold text-primary underline underline-offset-4"
                                        >
                                            {order.orderNumber}
                                        </Link>
                                    </dd>
                                </div>
                                <Field label="Paket No" mono>
                                    {order.packageId}
                                </Field>
                                <Field label="Kanal / Mağaza">
                                    {order.connection ?? '—'}
                                </Field>
                                <Field label="Sipariş Tarihi" mono>
                                    {order.placedAt ?? '—'}
                                </Field>
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                {/* Items Table */}
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                    <div className="flex items-center justify-between border-b border-border bg-muted/40 px-4 py-3">
                        <div className="flex items-center gap-2">
                            <Package className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold text-foreground">
                                İade Edilen Kalemler ({items.length})
                            </h2>
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border hover:bg-transparent text-xs">
                                <TableHead className="w-24">Kalem ID</TableHead>
                                <TableHead>SKU</TableHead>
                                <TableHead>Barkod</TableHead>
                                <TableHead className="text-right">Adet</TableHead>
                                <TableHead className="text-right">Birim Fiyat</TableHead>
                                <TableHead>Durum</TableHead>
                                <TableHead>Sebep</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.map((item) => (
                                <TableRow key={item.id} className="hover:bg-secondary/30 text-xs">
                                    <TableCell className="font-mono tabular-nums font-medium text-foreground py-3">
                                        {item.remoteItemId}
                                    </TableCell>
                                    <TableCell className="font-mono tabular-nums font-medium py-3">
                                        {item.sku ?? '—'}
                                    </TableCell>
                                    <TableCell className="font-mono text-muted-foreground tabular-nums py-3">
                                        {item.barcode ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right font-mono tabular-nums font-bold py-3">
                                        {item.quantity}
                                    </TableCell>
                                    <TableCell className="text-right font-mono tabular-nums font-medium py-3">
                                        {item.unitPrice ?? '—'}
                                    </TableCell>
                                    <TableCell className="py-3">
                                        <div className="space-y-0.5">
                                            <ClaimStatusBadge
                                                status={item.status}
                                                label={item.statusLabel}
                                            />
                                            {item.externalStatus && (
                                                <span className="block text-[10px] text-muted-foreground">
                                                    {item.externalStatus}
                                                </span>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="max-w-48 truncate text-muted-foreground py-3">
                                        {item.reason ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ClaimShow.layout = {
    breadcrumbs: [
        {
            title: 'İadeler',
            href: index(),
        },
    ],
};
