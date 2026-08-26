import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Check,
    Clock,
    Copy,
    ExternalLink,
    MapPin,
    Package,
    Phone,
    Receipt,
    ShieldAlert,
    TrendingUp,
    Truck,
    Unlink,
    User,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { MarketplaceLogo } from '@/components/marketplace-avatar';
import {
    OrderStatusBadge,
    PENDING_PAYMENT,
} from '@/components/orders/order-status-badge';
import { Badge } from '@/components/ui/badge';
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
import { index as claimsIndex } from '@/routes/claims';
import { index } from '@/routes/orders';

type Order = {
    id: number;
    orderNumber: string;
    packageId: string;
    status: string;
    statusLabel: string;
    externalStatus: string;
    connection: string | null;
    marketplace: string | null;
    currency: string;
    placedAt: string | null;
    lastModifiedAt: string | null;
    totals: Record<string, string>;
    financials?: {
        gross: string;
        discount: string;
        commission: string;
        netSales: string;
        totalCost: string | null;
        netPayout: string;
        estimatedProfit: string | null;
        marginPercent: string | null;
    };
    customer: {
        name: string | null;
        email: string | null;
        phone: string | null;
        city: string | null;
        district: string | null;
    };
};

type Line = {
    id: number;
    remoteLineId: string;
    sku: string;
    productName?: string | null;
    barcode: string | null;
    quantity: number;
    unitPrice: string;
    lineTotal?: string;
    cost?: string | null;
    status: string;
    statusLabel: string;
    externalStatus: string;
    vatRate: string | null;
    commission: string | null;
    commissionAmount?: string | null;
    matched: boolean;
    variantSku: string | null;
};

type PackageItem = {
    id: number;
    remotePackageId: string;
    cargoProvider: string | null;
    trackingNumber: string | null;
    trackingLink: string | null;
    status: string;
    statusLabel: string;
    externalStatus: string;
    deci: string | null;
    shippedAt: string | null;
    deliveredAt: string | null;
};

type HistoryEntry = {
    id: number;
    fromStatus: string | null;
    toStatus: string;
    occurredAt: string | null;
    source: string;
};

type Props = {
    order: Order;
    lines: Line[];
    packages: PackageItem[];
    history: HistoryEntry[];
};

const TOTAL_LABELS: Record<string, string> = {
    gross: 'Brüt Tutar',
    discount: 'İndirim',
    shipping: 'Kargo',
    commission: 'Pazaryeri Komisyonu',
    net: 'Ödenen Tutar',
};

export default function OrderShow({ order, lines, packages, history }: Props) {
    const [copied, setCopied] = useState(false);
    const [copiedPkgId, setCopiedPkgId] = useState<number | null>(null);

    const unmatched = lines.filter((line) => !line.matched).length;
    const isPendingPayment = order.status === PENDING_PAYMENT;
    const financials = order.financials;

    const copyOrderNumber = () => {
        navigator.clipboard.writeText(order.orderNumber);
        setCopied(true);
        toast.success(`Sipariş numarası kopyalandı: ${order.orderNumber}`);
        setTimeout(() => setCopied(false), 2000);
    };

    const copyTrackingNumber = (trackingNumber: string, pkgId: number) => {
        navigator.clipboard.writeText(trackingNumber);
        setCopiedPkgId(pkgId);
        toast.success(`Takip numarası kopyalandı: ${trackingNumber}`);
        setTimeout(() => setCopiedPkgId(null), 2000);
    };

    return (
        <>
            <Head title={`Sipariş ${order.orderNumber}`} />

            <div className="mx-auto flex max-w-7xl flex-col gap-5 p-4 font-sans sm:p-6 lg:p-8">
                {/* Top Navigation & Breadcrumbs */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 h-8 gap-1.5 text-muted-foreground hover:text-foreground"
                        >
                            <Link href={index()}>
                                <ArrowLeft className="size-4" />
                                <span>Siparişler</span>
                            </Link>
                        </Button>
                        <span className="text-muted-foreground/40">/</span>
                        <span className="font-mono text-sm font-semibold text-foreground">
                            {order.orderNumber}
                        </span>
                    </div>

                    <div className="flex items-center gap-2.5">
                        <Button
                            asChild
                            variant="outline"
                            size="sm"
                            className="h-8 gap-1.5 text-xs"
                        >
                            <Link
                                href={claimsIndex.url(undefined, {
                                    query: { search: order.orderNumber },
                                })}
                            >
                                <Receipt className="size-3.5" />
                                <span>İade Talepleri</span>
                                <ExternalLink className="size-3 text-muted-foreground" />
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Main Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div className="space-y-1.5">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                    {order.orderNumber}
                                </h1>
                                <button
                                    type="button"
                                    onClick={copyOrderNumber}
                                    className="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                    title="Sipariş Numarasını Kopyala"
                                    aria-label="Sipariş Numarasını Kopyala"
                                >
                                    {copied ? (
                                        <Check className="size-4 text-success" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                </button>
                                <OrderStatusBadge
                                    status={order.status}
                                    label={order.statusLabel}
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>
                                    Paket No:{' '}
                                    <span className="font-mono font-medium text-foreground">
                                        {order.packageId}
                                    </span>
                                </span>
                                {order.placedAt && (
                                    <div className="flex items-center gap-1">
                                        <Calendar className="size-3.5" />
                                        <span>{order.placedAt}</span>
                                    </div>
                                )}
                                {order.lastModifiedAt && (
                                    <div className="flex items-center gap-1 text-muted-foreground/80">
                                        <Clock className="size-3.5" />
                                        <span>
                                            Son Güncelleme:{' '}
                                            {order.lastModifiedAt}
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Marketplace Tag */}
                        {order.marketplace && (
                            <div className="flex items-center gap-3 self-start rounded-lg border border-border/80 bg-secondary/30 px-3.5 py-2 md:self-center">
                                <MarketplaceLogo
                                    code={order.marketplace}
                                    name={order.connection ?? undefined}
                                    height="h-5 sm:h-6"
                                />
                                {order.connection && (
                                    <div className="border-l border-border pl-3 text-xs">
                                        <span className="block font-semibold text-foreground">
                                            {order.connection}
                                        </span>
                                        {order.externalStatus && (
                                            <span className="block text-[11px] text-muted-foreground">
                                                {order.externalStatus}
                                            </span>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                {/* Contextual Warning Banners */}
                {isPendingPayment && (
                    <div className="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/10 p-3.5 text-sm text-warning shadow-xs">
                        <ShieldAlert className="mt-0.5 size-4.5 shrink-0" />
                        <div className="space-y-0.5">
                            <strong className="text-xs font-semibold">
                                Ödeme Onayı Bekleniyor
                            </strong>
                            <p className="text-xs text-warning/90">
                                Stok ayrıldı, ancak bu siparişi henüz kargoya
                                vermeyiniz. Ödemesi onaylanmadan gönderilen
                                siparişlerin iptal riskini pazaryeri
                                karşılamamaktadır.
                            </p>
                        </div>
                    </div>
                )}

                {unmatched > 0 && (
                    <div className="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/10 p-3.5 text-sm text-destructive shadow-xs">
                        <Unlink className="mt-0.5 size-4.5 shrink-0" />
                        <div className="space-y-0.5">
                            <strong className="text-xs font-semibold">
                                {unmatched} adet satır katalogdaki ürünle
                                eşleşmedi
                            </strong>
                            <p className="text-xs text-destructive/90">
                                Sipariş eksiksiz kaydedildi; ancak eşleştirme
                                yapılana kadar stok bu satırlar için düşülemez.
                            </p>
                        </div>
                    </div>
                )}

                {/* 2-Column Responsive Layout */}
                <div className="grid grid-cols-1 items-start gap-5 lg:grid-cols-3">
                    {/* Left Column (2/3 width on desktop) */}
                    <div className="space-y-5 lg:col-span-2">
                        {/* Order Items Table Card */}
                        <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                            <div className="flex items-center justify-between border-b border-border bg-muted/40 px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <Package className="size-4 text-muted-foreground" />
                                    <h2 className="text-sm font-semibold text-foreground">
                                        Sipariş Kalemleri ({lines.length})
                                    </h2>
                                </div>
                                <span className="text-xs font-medium text-muted-foreground">
                                    Toplam{' '}
                                    {lines.reduce(
                                        (acc, l) => acc + l.quantity,
                                        0,
                                    )}{' '}
                                    adet ürün
                                </span>
                            </div>
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-b border-border text-xs hover:bg-transparent">
                                        <TableHead className="w-12 pl-4"></TableHead>
                                        <TableHead className="min-w-[180px]">
                                            Ürün Bilgisi
                                        </TableHead>
                                        <TableHead className="text-center">
                                            Adet
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Birim Fiyat
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Komisyon / KDV
                                        </TableHead>
                                        <TableHead className="pr-4 text-right">
                                            Tutar
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {lines.map((line) => (
                                        <TableRow
                                            key={line.id}
                                            className="hover:bg-secondary/30"
                                        >
                                            <TableCell className="py-3 pl-4 align-top">
                                                <div className="flex size-9 items-center justify-center rounded-lg border border-border bg-secondary/40 text-muted-foreground">
                                                    <Package className="size-4.5" />
                                                </div>
                                            </TableCell>

                                            <TableCell className="py-3 align-top">
                                                <div className="space-y-0.5">
                                                    <div className="text-xs font-semibold text-foreground">
                                                        {line.productName ||
                                                            line.sku ||
                                                            'Ürün'}
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-2 font-mono text-[11px] text-muted-foreground">
                                                        <span>
                                                            SKU:{' '}
                                                            {line.sku || '—'}
                                                        </span>
                                                        {line.barcode && (
                                                            <>
                                                                <span>·</span>
                                                                <span>
                                                                    Barkod:{' '}
                                                                    {
                                                                        line.barcode
                                                                    }
                                                                </span>
                                                            </>
                                                        )}
                                                    </div>
                                                    <div className="flex flex-wrap items-center gap-1.5 pt-0.5">
                                                        {line.matched ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-success/30 bg-success/5 text-[10px] font-normal text-success"
                                                            >
                                                                Katalog:{' '}
                                                                {
                                                                    line.variantSku
                                                                }
                                                            </Badge>
                                                        ) : (
                                                            <Badge
                                                                variant="destructive"
                                                                className="text-[10px]"
                                                            >
                                                                Eşleşmemiş Satır
                                                            </Badge>
                                                        )}
                                                        {line.cost && (
                                                            <span className="text-[10px] text-muted-foreground">
                                                                (Maliyet:{' '}
                                                                {line.cost})
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>

                                            <TableCell className="py-3 text-center align-top font-mono text-xs tabular-nums">
                                                <span className="font-semibold">
                                                    {line.quantity}
                                                </span>
                                            </TableCell>

                                            <TableCell className="py-3 text-right align-top font-mono text-xs tabular-nums">
                                                <span>{line.unitPrice}</span>
                                            </TableCell>

                                            <TableCell className="space-y-0.5 py-3 text-right align-top text-xs tabular-nums">
                                                {line.commission && (
                                                    <div className="text-xs font-medium text-destructive">
                                                        {line.commission}
                                                        {line.commissionAmount &&
                                                            ` (${line.commissionAmount})`}
                                                    </div>
                                                )}
                                                {line.vatRate && (
                                                    <div className="text-[10px] text-muted-foreground">
                                                        KDV: {line.vatRate}
                                                    </div>
                                                )}
                                            </TableCell>

                                            <TableCell className="py-3 pr-4 text-right align-top font-mono text-xs font-bold text-foreground tabular-nums">
                                                {line.lineTotal ??
                                                    line.unitPrice}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Shipments & Packages Card */}
                        <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                            <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <Truck className="size-4 text-muted-foreground" />
                                    <CardTitle className="text-sm font-semibold">
                                        Kargo & Gönderi Paketleri (
                                        {packages.length})
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3 p-4">
                                {packages.length === 0 ? (
                                    <div className="py-4 text-center text-xs text-muted-foreground">
                                        Bu siparişe ait kargo paketi kaydı henüz
                                        oluşmadı.
                                    </div>
                                ) : (
                                    packages.map((pkg) => (
                                        <div
                                            key={pkg.id}
                                            className="space-y-2.5 rounded-lg border border-border bg-card p-3.5"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-2.5">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs font-semibold text-foreground">
                                                        {pkg.cargoProvider ||
                                                            'Kargo Firması'}
                                                    </span>
                                                    <span className="font-mono text-[11px] text-muted-foreground">
                                                        ({pkg.remotePackageId})
                                                    </span>
                                                </div>
                                                <OrderStatusBadge
                                                    status={pkg.status}
                                                    label={pkg.statusLabel}
                                                />
                                            </div>

                                            <div className="grid grid-cols-1 gap-2.5 text-xs sm:grid-cols-2">
                                                {pkg.trackingNumber && (
                                                    <div>
                                                        <span className="block text-[11px] text-muted-foreground">
                                                            Takip Numarası:
                                                        </span>
                                                        <div className="flex items-center gap-1.5 pt-0.5">
                                                            <span className="font-mono text-xs font-bold text-foreground tabular-nums">
                                                                {
                                                                    pkg.trackingNumber
                                                                }
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    copyTrackingNumber(
                                                                        pkg.trackingNumber!,
                                                                        pkg.id,
                                                                    )
                                                                }
                                                                className="rounded p-0.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                                                title="Kargo Takip No Kopyala"
                                                            >
                                                                {copiedPkgId ===
                                                                pkg.id ? (
                                                                    <Check className="size-3.5 text-success" />
                                                                ) : (
                                                                    <Copy className="size-3.5" />
                                                                )}
                                                            </button>
                                                        </div>
                                                    </div>
                                                )}

                                                {pkg.deci && (
                                                    <div>
                                                        <span className="block text-[11px] text-muted-foreground">
                                                            Desi:
                                                        </span>
                                                        <span className="font-mono text-xs font-medium text-foreground tabular-nums">
                                                            {pkg.deci}
                                                        </span>
                                                    </div>
                                                )}

                                                {pkg.shippedAt && (
                                                    <div>
                                                        <span className="block text-[11px] text-muted-foreground">
                                                            Kargoya Veriliş:
                                                        </span>
                                                        <span className="font-sans text-xs text-foreground tabular-nums">
                                                            {pkg.shippedAt}
                                                        </span>
                                                    </div>
                                                )}

                                                {pkg.deliveredAt && (
                                                    <div>
                                                        <span className="block text-[11px] text-muted-foreground">
                                                            Teslim Tarihi:
                                                        </span>
                                                        <span className="font-sans text-xs text-foreground tabular-nums">
                                                            {pkg.deliveredAt}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>

                                            {pkg.trackingLink && (
                                                <div className="pt-0.5 text-right">
                                                    <a
                                                        href={pkg.trackingLink}
                                                        target="_blank"
                                                        rel="noreferrer noopener"
                                                        className="inline-flex items-center gap-1 text-xs font-medium text-primary underline-offset-4 hover:underline"
                                                    >
                                                        <span>
                                                            Kargo Takip Sayfası
                                                        </span>
                                                        <ExternalLink className="size-3" />
                                                    </a>
                                                </div>
                                            )}
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>

                        {/* Order Timeline / Status History Card */}
                        <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                            <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <Clock className="size-4 text-muted-foreground" />
                                    <CardTitle className="text-sm font-semibold">
                                        Sipariş Durum Akışı & Denetim İzi
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="p-4">
                                {history.length === 0 ? (
                                    <div className="py-3 text-center text-xs text-muted-foreground">
                                        Henüz durum geçmişi kaydı bulunmuyor.
                                    </div>
                                ) : (
                                    <ol className="relative my-1 ml-2.5 space-y-3 border-l border-border/80">
                                        {history.map((entry) => (
                                            <li
                                                key={entry.id}
                                                className="relative ml-4"
                                            >
                                                <span className="absolute top-1 -left-[23px] flex size-3 items-center justify-center rounded-full bg-primary/20 ring-3 ring-card">
                                                    <span className="size-1.5 rounded-full bg-primary" />
                                                </span>
                                                <div className="flex flex-col gap-1 text-xs sm:flex-row sm:items-center sm:justify-between">
                                                    <span className="text-xs font-semibold text-foreground">
                                                        {entry.fromStatus
                                                            ? `${entry.fromStatus} ➔ ${entry.toStatus}`
                                                            : entry.toStatus}
                                                    </span>
                                                    <span className="font-mono text-[11px] text-muted-foreground tabular-nums">
                                                        {entry.occurredAt ??
                                                            '—'}
                                                    </span>
                                                </div>
                                                <span className="text-[10px] text-muted-foreground">
                                                    Kaynak:{' '}
                                                    {entry.source === 'pull'
                                                        ? 'Pazaryeri Senkronu'
                                                        : entry.source}
                                                </span>
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column (1/3 width on desktop) */}
                    <div className="space-y-5">
                        {/* Financial Summary & Deductions Card */}
                        <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                            <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Wallet className="size-4 text-muted-foreground" />
                                        <CardTitle className="text-sm font-semibold">
                                            Finansal Özet & Kesintiler
                                        </CardTitle>
                                    </div>
                                    <Badge
                                        variant="outline"
                                        className="font-mono text-xs tabular-nums"
                                    >
                                        {order.currency}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-3 p-4">
                                <div className="space-y-2 text-xs">
                                    {financials ? (
                                        <>
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">
                                                    Brüt Satış Tutarı:
                                                </span>
                                                <span className="font-mono font-medium text-foreground tabular-nums">
                                                    {financials.gross}
                                                </span>
                                            </div>
                                            {financials.discount !==
                                                '0,00 ₺' && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">
                                                        İndirimler:
                                                    </span>
                                                    <span className="font-mono font-medium text-destructive tabular-nums">
                                                        -{financials.discount}
                                                    </span>
                                                </div>
                                            )}
                                            {financials.commission !==
                                                '0,00 ₺' && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">
                                                        Pazaryeri Komisyonu:
                                                    </span>
                                                    <span className="font-mono font-medium text-destructive tabular-nums">
                                                        -{financials.commission}
                                                    </span>
                                                </div>
                                            )}
                                            {financials.totalCost && (
                                                <div className="flex justify-between">
                                                    <span className="text-muted-foreground">
                                                        Ürün Alış Maliyeti:
                                                    </span>
                                                    <span className="font-mono font-medium text-muted-foreground tabular-nums">
                                                        {financials.totalCost}
                                                    </span>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        Object.entries(order.totals).map(
                                            ([key, value]) => (
                                                <div
                                                    key={key}
                                                    className="flex justify-between"
                                                >
                                                    <span className="text-muted-foreground">
                                                        {TOTAL_LABELS[key] ??
                                                            key}
                                                        :
                                                    </span>
                                                    <span className="font-mono font-medium text-foreground tabular-nums">
                                                        {value}
                                                    </span>
                                                </div>
                                            ),
                                        )
                                    )}
                                </div>

                                <div className="space-y-2 border-t border-border pt-2.5">
                                    <div className="flex items-baseline justify-between">
                                        <span className="text-xs font-semibold text-foreground">
                                            Toplam Ödenen:
                                        </span>
                                        <span className="font-mono text-base font-bold text-foreground tabular-nums">
                                            {financials?.netSales ??
                                                (Object.entries(order.totals)
                                                    .length > 0
                                                    ? (order.totals.net ??
                                                      order.totals.gross)
                                                    : '—')}
                                        </span>
                                    </div>

                                    {financials?.netPayout && (
                                        <div className="space-y-0.5 rounded-lg border border-success/20 bg-success/5 p-2.5">
                                            <div className="flex items-center justify-between text-xs font-semibold text-success">
                                                <span>
                                                    Net Hakediş (Tahmini):
                                                </span>
                                                <span className="font-mono text-xs font-bold tabular-nums">
                                                    {financials.netPayout}
                                                </span>
                                            </div>
                                            <span className="block text-[10px] text-muted-foreground">
                                                Pazaryeri komisyonu düşüldükten
                                                sonra hesaba geçecek tutar.
                                            </span>
                                        </div>
                                    )}

                                    {financials?.estimatedProfit && (
                                        <div className="flex items-center justify-between pt-0.5 text-xs">
                                            <div className="flex items-center gap-1 text-muted-foreground">
                                                <TrendingUp className="size-3.5" />
                                                <span>Tahmini Net Kâr:</span>
                                            </div>
                                            <span className="font-mono font-bold text-foreground tabular-nums">
                                                {financials.estimatedProfit} (
                                                {financials.marginPercent})
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Customer & Shipping Information Card */}
                        <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                            <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <User className="size-4 text-muted-foreground" />
                                    <CardTitle className="text-sm font-semibold">
                                        Müşteri & Teslimat (KVKK)
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-2.5 p-4 text-xs">
                                <div>
                                    <span className="block text-[11px] font-medium text-muted-foreground">
                                        Alıcı Müşteri:
                                    </span>
                                    <span className="text-xs font-semibold text-foreground">
                                        {order.customer.name ?? '—'}
                                    </span>
                                </div>

                                <div>
                                    <span className="block text-[11px] font-medium text-muted-foreground">
                                        Teslimat Bölgesi:
                                    </span>
                                    <div className="flex items-center gap-1.5 pt-0.5 font-medium text-foreground">
                                        <MapPin className="size-3.5 shrink-0 text-muted-foreground" />
                                        <span>
                                            {[
                                                order.customer.district,
                                                order.customer.city,
                                            ]
                                                .filter(Boolean)
                                                .join(', ') || '—'}
                                        </span>
                                    </div>
                                </div>

                                {order.customer.phone && (
                                    <div>
                                        <span className="block text-[11px] font-medium text-muted-foreground">
                                            İletişim Telefon:
                                        </span>
                                        <div className="flex items-center gap-1.5 pt-0.5 font-mono text-foreground">
                                            <Phone className="size-3 shrink-0 text-muted-foreground" />
                                            <span>{order.customer.phone}</span>
                                        </div>
                                    </div>
                                )}

                                {order.customer.email && (
                                    <div>
                                        <span className="block text-[11px] font-medium text-muted-foreground">
                                            E-posta Adresi:
                                        </span>
                                        <span className="block truncate pt-0.5 font-sans text-xs text-foreground">
                                            {order.customer.email}
                                        </span>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Channel & Store Details Card */}
                        <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                            <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                <div className="flex items-center gap-2">
                                    <Building2 className="size-4 text-muted-foreground" />
                                    <CardTitle className="text-sm font-semibold">
                                        Pazaryeri & Bağlantı
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-2 p-4 text-xs">
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Kanal / Mağaza:
                                    </span>
                                    <span className="font-semibold text-foreground">
                                        {order.connection || 'Ana Mağaza'}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Pazaryeri Durumu:
                                    </span>
                                    <Badge
                                        variant="outline"
                                        className="font-mono text-[11px]"
                                    >
                                        {order.externalStatus || '—'}
                                    </Badge>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-muted-foreground">
                                        Para Birimi:
                                    </span>
                                    <span className="font-mono font-medium text-foreground">
                                        {order.currency}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

OrderShow.layout = {
    breadcrumbs: [
        {
            title: 'Siparişler',
            href: index(),
        },
    ],
};
