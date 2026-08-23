import { Head, Link } from '@inertiajs/react';
import { ExternalLink, TriangleAlert, Unlink } from 'lucide-react';
import Heading from '@/components/heading';
import {
    OrderStatusBadge,
    PENDING_PAYMENT,
} from '@/components/orders/order-status-badge';
import { Badge } from '@/components/ui/badge';
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

type Line = {
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
};

type Package = {
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
    packages: Package[];
    history: HistoryEntry[];
};

const TOTAL_LABELS: Record<string, string> = {
    gross: 'Brüt tutar',
    discount: 'İndirim',
    shipping: 'Kargo',
    commission: 'Komisyon',
    net: 'Ödenen tutar',
};

export default function OrderShow({ order, lines, packages, history }: Props) {
    const unmatched = lines.filter((line) => !line.matched).length;

    return (
        <>
            <Head title={`Sipariş ${order.orderNumber}`} />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title={`Sipariş ${order.orderNumber}`}
                    description={`Paket ${order.packageId}${order.connection ? ` · ${order.connection}` : ''}`}
                />

                <Link
                    href={claimsIndex.url(undefined, {
                        query: { search: order.orderNumber },
                    })}
                    className="text-sm underline underline-offset-4"
                >
                    Bu siparişin iade taleplerini gör
                </Link>
                {order.status === PENDING_PAYMENT && (
                    <div className="flex items-start gap-3 rounded-lg border border-warning/25 bg-warning/10 px-4 py-3 text-sm text-warning">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            <span className="font-medium">
                                Ödeme onayı bekleniyor.
                            </span>{' '}
                            Stok ayrıldı, ama bu siparişi{' '}
                            <strong>henüz hazırlamayın</strong>. Ödemesi
                            onaylanmadan gönderilen siparişlerin iptal riskini
                            pazaryeri üstlenmiyor.
                        </p>
                    </div>
                )}

                {unmatched > 0 && (
                    <div className="flex items-start gap-3 rounded-lg border border-dashed border-border px-4 py-3 text-sm text-muted-foreground">
                        <Unlink className="mt-0.5 size-4 shrink-0" />
                        <p>
                            <span className="font-mono tabular-nums">
                                {unmatched}
                            </span>{' '}
                            satır katalogdaki bir varyantla eşleşmedi. Sipariş
                            eksiksiz kaydedildi; eşleştirme yapılana kadar stok
                            bu satırlar için düşülemez.
                        </p>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Durum</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <OrderStatusBadge
                                status={order.status}
                                label={order.statusLabel}
                            />
                            <p className="text-muted-foreground">
                                Pazaryeri durumu: {order.externalStatus || '—'}
                            </p>
                            <p className="text-muted-foreground">
                                Sipariş tarihi:{' '}
                                <span className="font-mono tabular-nums">
                                    {order.placedAt ?? '—'}
                                </span>
                            </p>
                            <p className="text-muted-foreground">
                                Son güncelleme:{' '}
                                <span className="font-mono tabular-nums">
                                    {order.lastModifiedAt ?? '—'}
                                </span>
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Tutarlar</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            {Object.entries(order.totals).length === 0 ? (
                                <p className="text-muted-foreground">—</p>
                            ) : (
                                Object.entries(order.totals).map(
                                    ([key, value]) => (
                                        <p
                                            key={key}
                                            className="flex justify-between gap-4"
                                        >
                                            <span className="text-muted-foreground">
                                                {TOTAL_LABELS[key] ?? key}
                                            </span>
                                            <span className="font-mono tabular-nums">
                                                {value}
                                            </span>
                                        </p>
                                    ),
                                )
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Müşteri</CardTitle>
                        </CardHeader>
                        {/* KVKK: ad kisaltilmis, e-posta ve telefon maskeli,
                            adres il/ilce duzeyinde. Tam adres ve kimlik
                            numarasi bu ekrana hic gelmez. */}
                        <CardContent className="space-y-1 text-sm">
                            <p>{order.customer.name ?? '—'}</p>
                            <p className="text-muted-foreground">
                                {order.customer.email ?? '—'}
                            </p>
                            <p className="text-muted-foreground">
                                {order.customer.phone ?? '—'}
                            </p>
                            <p className="text-muted-foreground">
                                {[order.customer.district, order.customer.city]
                                    .filter(Boolean)
                                    .join(' / ') || '—'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Sipariş satırları</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Ürün</TableHead>
                                    <TableHead>Durum</TableHead>
                                    <TableHead className="text-right">
                                        Adet
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Birim fiyat
                                    </TableHead>
                                    <TableHead className="text-right">
                                        KDV / Komisyon
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            <span className="font-mono font-medium tabular-nums">
                                                {line.sku || '—'}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                Barkod:{' '}
                                                <span className="font-mono tabular-nums">
                                                    {line.barcode ?? '—'}
                                                </span>
                                            </span>
                                            {line.matched ? (
                                                <span className="block text-xs text-muted-foreground">
                                                    Katalog:{' '}
                                                    <span className="font-mono tabular-nums">
                                                        {line.variantSku}
                                                    </span>
                                                </span>
                                            ) : (
                                                <Badge
                                                    variant="destructive"
                                                    className="mt-1"
                                                >
                                                    Eşleşmedi
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <OrderStatusBadge
                                                status={line.status}
                                                label={line.statusLabel}
                                            />
                                            <span className="block text-xs text-muted-foreground">
                                                {line.externalStatus}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right font-mono tabular-nums">
                                            {line.quantity}
                                        </TableCell>
                                        <TableCell className="text-right font-mono tabular-nums">
                                            {line.unitPrice}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-muted-foreground tabular-nums">
                                            {line.vatRate ?? '—'} /{' '}
                                            {line.commission ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Kargo paketleri</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            {packages.length === 0 ? (
                                <p className="text-muted-foreground">
                                    Bu siparişe ait paket kaydı yok.
                                </p>
                            ) : (
                                packages.map((shipment) => (
                                    <div
                                        key={shipment.id}
                                        className="space-y-1 rounded-lg border border-border p-3"
                                    >
                                        <div className="flex items-center gap-2">
                                            <OrderStatusBadge
                                                status={shipment.status}
                                                label={shipment.statusLabel}
                                            />
                                            <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                                {shipment.remotePackageId}
                                            </span>
                                        </div>
                                        <p className="text-muted-foreground">
                                            {shipment.cargoProvider ?? '—'}
                                        </p>
                                        {/* Kargo takip numarasi int64'u asar,
                                            uctan uca string tasinir. */}
                                        <p className="font-mono text-xs break-all tabular-nums">
                                            {shipment.trackingNumber ?? '—'}
                                        </p>
                                        {shipment.trackingLink && (
                                            <a
                                                href={shipment.trackingLink}
                                                target="_blank"
                                                rel="noreferrer noopener"
                                                className="inline-flex items-center gap-1 text-xs underline underline-offset-4"
                                            >
                                                Kargo takip
                                                <ExternalLink className="size-3" />
                                            </a>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Kargoya veriliş:{' '}
                                            <span className="font-mono tabular-nums">
                                                {shipment.shippedAt ?? '—'}
                                            </span>{' '}
                                            · Teslim:{' '}
                                            <span className="font-mono tabular-nums">
                                                {shipment.deliveredAt ?? '—'}
                                            </span>
                                        </p>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Durum geçmişi</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {history.length === 0 ? (
                                <p className="text-muted-foreground">
                                    Kayıt yok.
                                </p>
                            ) : (
                                <ol className="space-y-2">
                                    {history.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="flex justify-between gap-4 border-b border-border pb-2 last:border-0"
                                        >
                                            <span>
                                                {entry.fromStatus
                                                    ? `${entry.fromStatus} → ${entry.toStatus}`
                                                    : entry.toStatus}
                                            </span>
                                            <span className="font-mono text-muted-foreground tabular-nums">
                                                {entry.occurredAt ?? '—'}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </CardContent>
                    </Card>
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
