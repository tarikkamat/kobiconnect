import { Link } from '@inertiajs/react';
import {
    Check,
    Copy,
    ExternalLink,
    Loader2,
    MapPin,
    Package,
    Phone,
    ShieldAlert,
    Truck,
    Unlink,
    User,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { MarketplaceLogo } from '@/components/marketplace-avatar';
import {
    OrderStatusBadge,
    PENDING_PAYMENT,
} from '@/components/orders/order-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { show } from '@/routes/orders';

export type OrderPreviewSummary = {
    id: number;
    orderNumber: string;
    packageId: string;
    status: string;
    statusLabel: string;
    externalStatus: string;
    connection: string | null;
    marketplace: string | null;
    customer: string | null;
    customerLocation?: string | null;
    total: string | null;
    placedAt: string | null;
    lineCount: number;
    unmatchedCount: number;
};

type OrderDetailResponse = {
    order: {
        id: number;
        orderNumber: string;
        packageId: string;
        status: string;
        statusLabel: string;
        externalStatus: string;
        connection: string | null;
        marketplace?: string | null;
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
    lines: {
        id: number;
        remoteLineId: string;
        sku: string;
        productName?: string | null;
        barcode: string | null;
        quantity: number;
        unitPrice: string;
        lineTotal?: string;
        status: string;
        statusLabel: string;
        externalStatus: string;
        vatRate: string | null;
        commission: string | null;
        matched: boolean;
        variantSku: string | null;
    }[];
    packages: {
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
    }[];
};

const TOTAL_LABELS: Record<string, string> = {
    gross: 'Brüt Tutar',
    discount: 'İndirim',
    shipping: 'Kargo',
    commission: 'Pazaryeri Komisyonu',
    net: 'Ödenen Tutar',
};

export function OrderPreviewSheet({
    order,
    open,
    onOpenChange,
}: {
    order: OrderPreviewSummary | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [detail, setDetail] = useState<OrderDetailResponse | null>(null);
    const [loading, setLoading] = useState(false);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!open || !order) {
            setDetail(null);
            return;
        }

        let isMounted = true;
        setLoading(true);

        fetch(show.url({ order: order.id }), {
            headers: {
                Accept: 'application/json',
            },
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data: OrderDetailResponse | null) => {
                if (isMounted && data) {
                    setDetail(data);
                }
            })
            .catch(() => {
                // Keep basic summary on error
            })
            .finally(() => {
                if (isMounted) {
                    setLoading(false);
                }
            });

        return () => {
            isMounted = false;
        };
    }, [open, order]);

    if (!order) {
        return null;
    }

    const copyOrderNumber = (e: React.MouseEvent) => {
        e.stopPropagation();
        navigator.clipboard.writeText(order.orderNumber);
        setCopied(true);
        toast.success(`Sipariş numarası kopyalandı: ${order.orderNumber}`);
        setTimeout(() => setCopied(false), 2000);
    };

    const isPendingPayment = order.status === PENDING_PAYMENT;
    const customer = detail?.order.customer;
    const lines = detail?.lines ?? [];
    const packages = detail?.packages ?? [];
    const totals = detail?.order.totals ?? {};
    const financials = detail?.order.financials;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-full overflow-y-auto sm:max-w-xl md:max-w-2xl font-sans p-6 sm:p-8"
            >
                {/* Header */}
                <SheetHeader className="border-b border-border pb-5">
                    <div className="flex items-start justify-between gap-4">
                        <div className="min-w-0 flex-1 space-y-1">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <SheetTitle className="font-sans text-xl font-bold tracking-tight text-foreground">
                                    {order.orderNumber}
                                </SheetTitle>
                                <button
                                    type="button"
                                    onClick={copyOrderNumber}
                                    className="rounded-md p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                    title="Sipariş Numarasını Kopyala"
                                    aria-label="Sipariş Numarasını Kopyala"
                                >
                                    {copied ? (
                                        <Check className="size-4 text-success" />
                                    ) : (
                                        <Copy className="size-4" />
                                    )}
                                </button>
                            </div>
                            <SheetDescription className="font-sans text-xs text-muted-foreground">
                                Paket No:{' '}
                                <span className="font-mono font-medium text-foreground">
                                    {order.packageId}
                                </span>
                                {order.placedAt ? ` · ${order.placedAt}` : ''}
                            </SheetDescription>
                        </div>

                        {/* Marketplace Logo */}
                        {order.marketplace && (
                            <div className="flex shrink-0 items-center justify-end">
                                <MarketplaceLogo
                                    code={order.marketplace}
                                    name={order.connection ?? undefined}
                                    height="h-6 sm:h-7"
                                />
                            </div>
                        )}
                    </div>

                    {/* Status and Connection Tags */}
                    <div className="mt-3 flex flex-wrap items-center gap-2 pt-1">
                        <OrderStatusBadge
                            status={order.status}
                            label={order.statusLabel}
                        />
                        {order.externalStatus && (
                            <Badge variant="outline" className="text-xs font-normal text-muted-foreground">
                                Pazaryeri: {order.externalStatus}
                            </Badge>
                        )}
                        {order.connection && (
                            <Badge variant="secondary" className="text-xs">
                                {order.connection}
                            </Badge>
                        )}
                    </div>
                </SheetHeader>

                {/* Body Content */}
                <div className="space-y-6 py-5">
                    {isPendingPayment && (
                        <div className="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/10 p-3.5 text-xs text-warning">
                            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                            <div>
                                <strong className="font-semibold">
                                    Ödeme Onayı Bekleniyor:
                                </strong>{' '}
                                Stok ayrılmıştır ancak ödeme onaylanana kadar paketi kargoya vermeyiniz.
                            </div>
                        </div>
                    )}

                    {order.unmatchedCount > 0 && (
                        <div className="flex items-start gap-3 rounded-lg border border-destructive/30 bg-destructive/10 p-3.5 text-xs text-destructive">
                            <Unlink className="mt-0.5 size-4 shrink-0" />
                            <div>
                                <strong className="font-semibold">
                                    {order.unmatchedCount} adet satır
                                </strong>{' '}
                                katalogdaki ürün varyantlarıyla eşleşmedi. Eşleştirme yapılana kadar bu satırlar için stok düşülemez.
                            </div>
                        </div>
                    )}

                    {/* Müşteri ve Teslimat Bilgisi */}
                    <div className="rounded-xl border border-border bg-card p-4 space-y-3">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            <User className="size-3.5" />
                            <span>Müşteri & Teslimat Bilgisi (KVKK)</span>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div>
                                <span className="block text-xs text-muted-foreground">Alıcı Adı:</span>
                                <span className="font-medium text-foreground">
                                    {customer?.name ?? order.customer ?? '—'}
                                </span>
                            </div>
                            <div>
                                <span className="block text-xs text-muted-foreground">Teslimat Şehir / İlçe:</span>
                                <div className="flex items-center gap-1 font-medium text-foreground">
                                    <MapPin className="size-3.5 text-muted-foreground shrink-0" />
                                    <span>
                                        {[customer?.district, customer?.city].filter(Boolean).join(', ') ||
                                            order.customerLocation ||
                                            '—'}
                                    </span>
                                </div>
                            </div>
                            {customer?.phone && (
                                <div>
                                    <span className="block text-xs text-muted-foreground">İletişim Telefon:</span>
                                    <div className="flex items-center gap-1 font-mono text-sm text-foreground">
                                        <Phone className="size-3 text-muted-foreground shrink-0" />
                                        <span>{customer.phone}</span>
                                    </div>
                                </div>
                            )}
                            {customer?.email && (
                                <div>
                                    <span className="block text-xs text-muted-foreground">E-posta Adresi:</span>
                                    <span className="font-sans text-xs text-foreground truncate block">
                                        {customer.email}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sipariş Satırları */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-foreground">
                                Sipariş Kalemleri ({order.lineCount})
                            </h3>
                            {loading && (
                                <Loader2 className="size-4 animate-spin text-muted-foreground" />
                            )}
                        </div>

                        {loading && lines.length === 0 ? (
                            <div className="space-y-2.5">
                                <Skeleton className="h-16 w-full rounded-xl" />
                                <Skeleton className="h-16 w-full rounded-xl" />
                            </div>
                        ) : lines.length > 0 ? (
                            <div className="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card">
                                {lines.map((line) => (
                                    <div
                                        key={line.id}
                                        className="flex items-start justify-between gap-4 p-4 text-sm"
                                    >
                                        <div className="flex items-start gap-3 min-w-0 flex-1">
                                            <div className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-secondary/50 text-muted-foreground">
                                                <Package className="size-4.5" />
                                            </div>
                                            <div className="min-w-0 flex-1 space-y-1">
                                                <div className="font-medium text-foreground line-clamp-1">
                                                    {line.productName || line.sku || 'Ürün'}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground font-mono">
                                                    <span>SKU: {line.sku || '—'}</span>
                                                    {line.barcode && <span>Barkod: {line.barcode}</span>}
                                                </div>
                                                <div className="pt-0.5">
                                                    {line.matched ? (
                                                        <Badge variant="outline" className="text-[11px] text-success border-success/30 bg-success/5 font-normal">
                                                            Katalogla Eşleşti ({line.variantSku})
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="destructive" className="text-[11px]">
                                                            Eşleşmemiş Satır
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="text-right shrink-0">
                                            <div className="font-semibold text-foreground tabular-nums">
                                                {line.unitPrice}
                                            </div>
                                            <div className="text-xs text-muted-foreground font-mono">
                                                x {line.quantity} adet
                                            </div>
                                            {line.commission && (
                                                <div className="text-[11px] text-muted-foreground">
                                                    Komisyon: {line.commission}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-xl border border-border p-4 text-center text-xs text-muted-foreground">
                                {order.lineCount} adet ürün kalemi bulunuyor.
                            </div>
                        )}
                    </div>

                    {/* Kargo ve Teslimat Bilgisi */}
                    {packages.length > 0 && (
                        <div className="space-y-3">
                            <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                                <Truck className="size-4 text-muted-foreground" />
                                <span>Kargo ve Teslimat</span>
                            </h3>
                            <div className="space-y-2.5">
                                {packages.map((pkg) => (
                                    <div
                                        key={pkg.id}
                                        className="rounded-xl border border-border bg-card p-4 text-xs space-y-2"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-semibold text-sm text-foreground">
                                                {pkg.cargoProvider ?? 'Kargo Firması'}
                                            </span>
                                            <OrderStatusBadge
                                                status={pkg.status}
                                                label={pkg.statusLabel}
                                            />
                                        </div>
                                        {pkg.trackingNumber && (
                                            <div className="flex items-center justify-between pt-1">
                                                <span className="text-muted-foreground">Takip Numarası:</span>
                                                <span className="font-mono font-medium text-foreground tabular-nums">
                                                    {pkg.trackingNumber}
                                                </span>
                                            </div>
                                        )}
                                        {pkg.trackingLink && (
                                            <div className="pt-1 text-right">
                                                <a
                                                    href={pkg.trackingLink}
                                                    target="_blank"
                                                    rel="noreferrer noopener"
                                                    className="inline-flex items-center gap-1 text-xs text-primary font-medium underline-offset-4 hover:underline"
                                                >
                                                    Kargo Takip Sayfası
                                                    <ExternalLink className="size-3.5" />
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Finansal & Tutar Özeti */}
                    <div className="rounded-xl border border-border bg-card p-4 space-y-3">
                        <div className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Finansal Özet & Kesintiler
                        </div>
                        <div className="space-y-2 text-sm divide-y divide-border/60">
                            <div className="space-y-1.5 pb-2">
                                {financials ? (
                                    <>
                                        <div className="flex justify-between text-muted-foreground text-xs">
                                            <span>Brüt Tutar:</span>
                                            <span className="tabular-nums font-medium text-foreground">{financials.gross}</span>
                                        </div>
                                        {financials.discount !== '0,00 ₺' && (
                                            <div className="flex justify-between text-muted-foreground text-xs">
                                                <span>İndirimler:</span>
                                                <span className="tabular-nums font-medium text-destructive">-{financials.discount}</span>
                                            </div>
                                        )}
                                        {financials.commission !== '0,00 ₺' && (
                                            <div className="flex justify-between text-muted-foreground text-xs">
                                                <span>Pazaryeri Komisyonu:</span>
                                                <span className="tabular-nums font-medium text-destructive">-{financials.commission}</span>
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    Object.entries(totals).map(([key, val]) => (
                                        <div key={key} className="flex justify-between text-muted-foreground text-xs">
                                            <span>{TOTAL_LABELS[key] ?? key}:</span>
                                            <span className="tabular-nums font-medium text-foreground">{val}</span>
                                        </div>
                                    ))
                                )}
                            </div>

                            <div className="flex items-center justify-between pt-2">
                                <span className="font-semibold text-sm text-foreground">Toplam Ödenen:</span>
                                <span className="font-bold text-base text-foreground tabular-nums">
                                    {financials?.netSales ?? order.total ?? '—'}
                                </span>
                            </div>

                            {financials?.netPayout && (
                                <div className="flex items-center justify-between pt-2 text-xs font-medium text-success">
                                    <span>Satıcıya Geçecek Tahmini Tutar:</span>
                                    <span className="font-bold text-sm tabular-nums">{financials.netPayout}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <SheetFooter className="mt-2 border-t border-border pt-4">
                    <Button asChild className="w-full font-sans h-10">
                        <Link href={show({ order: order.id })} instant>
                            Tüm Detay Sayfasına Git
                            <ExternalLink className="ml-2 size-4" />
                        </Link>
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
