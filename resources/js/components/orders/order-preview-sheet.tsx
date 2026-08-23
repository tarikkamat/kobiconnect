import { Link } from '@inertiajs/react';
import {
    Check,
    Copy,
    ExternalLink,
    Loader2,
    Package,
    ShieldAlert,
    Truck,
    Unlink,
    User,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { MarketplaceAvatar } from '@/components/marketplace-avatar';
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
        barcode: string | null;
        quantity: number;
        unitPrice: string;
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
    commission: 'Komisyon',
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
                // If fetch fails, keep basic summary
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

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-full overflow-y-auto sm:max-w-lg font-sans"
            >
                <SheetHeader className="border-b border-border pb-4">
                    <div className="flex items-center gap-3">
                        {order.marketplace ? (
                            <MarketplaceAvatar
                                code={order.marketplace}
                                name={order.connection ?? undefined}
                                size="md"
                            />
                        ) : (
                            <div className="flex size-8 items-center justify-center rounded-lg border border-border bg-secondary">
                                <Package className="size-4 text-muted-foreground" />
                            </div>
                        )}
                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <SheetTitle className="font-sans text-base font-semibold tracking-tight">
                                    {order.orderNumber}
                                </SheetTitle>
                                <button
                                    type="button"
                                    onClick={copyOrderNumber}
                                    className="rounded p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                    title="Sipariş Numarasını Kopyala"
                                    aria-label="Sipariş Numarasını Kopyala"
                                >
                                    {copied ? (
                                        <Check className="size-3.5 text-success" />
                                    ) : (
                                        <Copy className="size-3.5" />
                                    )}
                                </button>
                            </div>
                            <SheetDescription className="font-sans text-xs">
                                Paket {order.packageId}
                                {order.connection ? ` · ${order.connection}` : ''}
                            </SheetDescription>
                        </div>
                    </div>

                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                            <OrderStatusBadge
                                status={order.status}
                                label={order.statusLabel}
                            />
                            {order.externalStatus && (
                                <span className="font-sans text-[11px] text-muted-foreground">
                                    ({order.externalStatus})
                                </span>
                            )}
                        </div>
                        {order.placedAt && (
                            <span className="font-sans text-xs text-muted-foreground tabular-nums">
                                {order.placedAt}
                            </span>
                        )}
                    </div>
                </SheetHeader>

                <div className="space-y-5 py-2">
                    {isPendingPayment && (
                        <div className="flex items-start gap-2.5 rounded-lg border border-warning/25 bg-warning/10 p-3 text-xs text-warning">
                            <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                            <div>
                                <strong className="font-medium">
                                    Ödeme Onayı Bekleniyor:
                                </strong>{' '}
                                Stok ayrılmıştır ancak ödeme onaylanana kadar
                                paketi kargoya vermeyiniz.
                            </div>
                        </div>
                    )}

                    {order.unmatchedCount > 0 && (
                        <div className="flex items-start gap-2.5 rounded-lg border border-destructive/25 bg-destructive/10 p-3 text-xs text-destructive">
                            <Unlink className="mt-0.5 size-4 shrink-0" />
                            <div>
                                <strong className="font-sans font-semibold">
                                    {order.unmatchedCount} adet satır
                                </strong>{' '}
                                katalogdaki ürün varyantlarıyla eşleşmedi.
                            </div>
                        </div>
                    )}

                    {/* Müşteri Bilgileri */}
                    <div className="space-y-2 rounded-lg border border-border bg-secondary/30 p-3.5">
                        <div className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                            <User className="size-3.5" />
                            <span>Müşteri Bilgisi (KVKK Korumalı)</span>
                        </div>
                        <div className="grid grid-cols-2 gap-2 pt-1 text-xs">
                            <div>
                                <span className="text-muted-foreground">
                                    Alıcı:
                                </span>
                                <p className="font-medium text-foreground">
                                    {customer?.name ?? order.customer ?? '—'}
                                </p>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    Teslimat Bölgesi:
                                </span>
                                <p className="font-medium text-foreground">
                                    {[customer?.district, customer?.city]
                                        .filter(Boolean)
                                        .join(' / ') ||
                                        order.customerLocation ||
                                        '—'}
                                </p>
                            </div>
                            {customer?.phone && (
                                <div>
                                    <span className="text-muted-foreground">
                                        Telefon:
                                    </span>
                                    <p className="font-sans text-foreground tabular-nums font-medium">
                                        {customer.phone}
                                    </p>
                                </div>
                            )}
                            {customer?.email && (
                                <div>
                                    <span className="text-muted-foreground">
                                        E-posta:
                                    </span>
                                    <p className="font-sans text-foreground">
                                        {customer.email}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Sipariş Kalemleri */}
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-xs">
                            <span className="font-medium text-foreground">
                                Sipariş Satırları ({order.lineCount})
                            </span>
                            {loading && (
                                <Loader2 className="size-3 animate-spin text-muted-foreground" />
                            )}
                        </div>

                        {loading && lines.length === 0 ? (
                            <div className="space-y-2">
                                <Skeleton className="h-12 w-full rounded-md" />
                                <Skeleton className="h-12 w-full rounded-md" />
                            </div>
                        ) : lines.length > 0 ? (
                            <div className="divide-y divide-border overflow-hidden rounded-lg border border-border bg-card">
                                {lines.map((line) => (
                                    <div
                                        key={line.id}
                                        className="flex items-center justify-between gap-3 p-3 text-xs"
                                    >
                                        <div className="min-w-0 flex-1 space-y-0.5">
                                            <div className="flex items-center gap-1.5">
                                                <span className="font-sans font-medium text-foreground">
                                                    {line.sku || '—'}
                                                </span>
                                                {!line.matched && (
                                                    <Badge
                                                        variant="destructive"
                                                        className="h-4 text-[10px]"
                                                    >
                                                        Eşleşmedi
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 font-sans text-[11px] text-muted-foreground">
                                                {line.barcode && (
                                                    <span>
                                                        Barkod: {line.barcode}
                                                    </span>
                                                )}
                                                {line.matched &&
                                                    line.variantSku && (
                                                        <span>
                                                            Katalog:{' '}
                                                            {line.variantSku}
                                                        </span>
                                                    )}
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <div className="font-sans font-semibold text-foreground tabular-nums">
                                                {line.unitPrice}
                                            </div>
                                            <div className="font-sans text-[11px] text-muted-foreground tabular-nums">
                                                {line.quantity} adet
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="rounded-lg border border-border p-3 text-center text-xs text-muted-foreground">
                                {order.lineCount} adet ürün kalemi bulunuyor.
                            </div>
                        )}
                    </div>

                    {/* Kargo Bilgisi */}
                    {packages.length > 0 && (
                        <div className="space-y-2">
                            <div className="flex items-center gap-2 text-xs font-medium text-foreground">
                                <Truck className="size-3.5 text-muted-foreground" />
                                <span>Kargo ve Teslimat</span>
                            </div>
                            <div className="space-y-2">
                                {packages.map((pkg) => (
                                    <div
                                        key={pkg.id}
                                        className="rounded-lg border border-border bg-card p-3 text-xs"
                                    >
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium text-foreground">
                                                {pkg.cargoProvider ??
                                                    'Kargo Firması'}
                                            </span>
                                            <OrderStatusBadge
                                                status={pkg.status}
                                                label={pkg.statusLabel}
                                            />
                                        </div>
                                        {pkg.trackingNumber && (
                                            <div className="mt-2 flex items-center justify-between font-sans text-xs">
                                                <span className="text-muted-foreground">
                                                    Takip No:
                                                </span>
                                                <span className="font-medium text-foreground tabular-nums">
                                                    {pkg.trackingNumber}
                                                </span>
                                            </div>
                                        )}
                                        {pkg.trackingLink && (
                                            <div className="mt-2 text-right">
                                                <a
                                                    href={pkg.trackingLink}
                                                    target="_blank"
                                                    rel="noreferrer noopener"
                                                    className="inline-flex items-center gap-1 text-xs text-primary underline-offset-4 hover:underline"
                                                >
                                                    Kargo Takip Sayfası
                                                    <ExternalLink className="size-3" />
                                                </a>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Tutar Özeti */}
                    <div className="space-y-1.5 rounded-lg border border-border bg-card p-3.5">
                        <div className="text-xs font-medium text-foreground">
                            Tutar Özeti
                        </div>
                        <div className="space-y-1 pt-1 text-xs">
                            {Object.entries(totals).length > 0 ? (
                                Object.entries(totals).map(([key, val]) => (
                                    <div
                                        key={key}
                                        className="flex justify-between font-sans"
                                    >
                                        <span className="text-muted-foreground">
                                            {TOTAL_LABELS[key] ?? key}
                                        </span>
                                        <span
                                            className={
                                                key === 'net'
                                                    ? 'font-semibold text-foreground tabular-nums'
                                                    : 'text-muted-foreground tabular-nums'
                                            }
                                        >
                                            {val}
                                        </span>
                                    </div>
                                ))
                            ) : (
                                <div className="flex justify-between font-sans">
                                    <span className="text-muted-foreground">
                                        Toplam Tutar
                                    </span>
                                    <span className="font-semibold text-foreground tabular-nums">
                                        {order.total ?? '—'}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <SheetFooter className="mt-4 border-t border-border pt-4">
                    <Button asChild className="w-full font-sans">
                        <Link href={show({ order: order.id })} instant>
                            Tüm Detay Sayfasına Git
                            <ExternalLink className="ml-1.5 size-4" />
                        </Link>
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
