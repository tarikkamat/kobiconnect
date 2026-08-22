import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { Search, TriangleAlert, Unlink } from 'lucide-react';
import Heading from '@/components/heading';
import { MarketplaceAvatar } from '@/components/marketplace-avatar';
import {
    OrderStatusBadge,
    PENDING_PAYMENT,
} from '@/components/orders/order-status-badge';
import { Badge } from '@/components/ui/badge';
import { DataTable  } from '@/components/ui/data-table';
import type {DataTableColumns} from '@/components/ui/data-table';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Toggle } from '@/components/ui/toggle';
import { index, show } from '@/routes/orders';

type OrderRow = {
    id: number;
    orderNumber: string;
    packageId: string;
    status: string;
    statusLabel: string;
    externalStatus: string;
    connection: string | null;
    /** Bagli kanalin pazaryeri kodu; logo yolu bundan turetilir. */
    marketplace: string | null;
    customer: string | null;
    total: string | null;
    placedAt: string | null;
    lineCount: number;
    unmatchedCount: number;
};

type Filters = {
    search: string;
    status: string | null;
    connection: number | null;
    unmatched: boolean;
};

type Props = {
    orders: { data: OrderRow[] };
    filters: Filters;
    statuses: { value: string; label: string }[];
    connections: { id: number; name: string }[];
    unmatchedTotal: number;
};

/** Radix Select bos deger kabul etmez; "hepsi" secenegi bu sabitle temsil edilir. */
const ALL = 'all';

/**
 * Kolon tanimlari modul kapsaminda durur: her render'da yeniden uretilirse
 * TanStack tum kolon modelini bosuna kurar.
 */
const COLUMNS: DataTableColumns<OrderRow> = [
    {
        id: 'marketplace',
        header: 'Kanal',
        meta: { className: 'w-16' },
        cell: ({ row }) =>
            row.original.marketplace ? (
                <MarketplaceAvatar
                    code={row.original.marketplace}
                    name={row.original.connection ?? undefined}
                    size="lg"
                />
            ) : (
                <span className="text-muted-foreground">—</span>
            ),
    },
    {
        id: 'order',
        header: 'Sipariş',
        cell: ({ row }) => (
            <>
                <Link
                    href={show({ order: row.original.id })}
                    instant
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.original.orderNumber}
                </Link>
                <span className="block text-xs text-muted-foreground">
                    Paket {row.original.packageId}
                    {row.original.connection
                        ? ` · ${row.original.connection}`
                        : ''}
                </span>
            </>
        ),
    },
    {
        id: 'status',
        header: 'Durum',
        cell: ({ row }) => (
            <>
                <OrderStatusBadge
                    status={row.original.status}
                    label={row.original.statusLabel}
                />
                {/* Ham pazaryeri durumu bilgi olarak durur: bilinmeyen bir
                    deger varsayilana katlanmaz. */}
                <span className="block text-xs text-muted-foreground">
                    {row.original.externalStatus}
                </span>
            </>
        ),
    },
    {
        id: 'customer',
        header: 'Müşteri',
        cell: ({ row }) => row.original.customer ?? '—',
    },
    {
        id: 'lines',
        header: 'Kalem',
        meta: { className: 'text-right tabular-nums' },
        cell: ({ row }) => (
            <>
                {row.original.lineCount}
                {row.original.unmatchedCount > 0 && (
                    <Badge variant="destructive" className="ml-2">
                        {row.original.unmatchedCount} eşleşmemiş
                    </Badge>
                )}
            </>
        ),
    },
    {
        id: 'total',
        header: 'Tutar',
        meta: { className: 'text-right tabular-nums' },
        cell: ({ row }) => row.original.total ?? '—',
    },
    {
        id: 'placedAt',
        header: 'Sipariş tarihi',
        meta: { className: 'text-muted-foreground' },
        cell: ({ row }) => row.original.placedAt ?? '—',
    },
];

export default function OrderIndex({
    orders,
    filters,
    statuses,
    connections,
    unmatchedTotal,
}: Props) {
    const apply = (changes: Partial<Filters>): void => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) =>
                    value !== null && value !== '' && value !== false,
            ),
        );

        router.get(
            index.url(undefined, { query }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const hasPendingPayment = orders.data.some(
        (order) => order.status === PENDING_PAYMENT,
    );

    return (
        <>
            <Head title="Siparişler" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Siparişler"
                    description="Pazaryerlerinden çekilen sipariş paketleri."
                />

                {hasPendingPayment && (
                    <div className="flex items-start gap-3 rounded-lg border border-amber-500 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        <p>
                            <span className="font-medium">
                                Ödeme bekleyen siparişler var.
                            </span>{' '}
                            Bu siparişlerin ödemesi henüz onaylanmadı; stok
                            ayrılır ama <strong>henüz hazırlamayın</strong>.
                            Pazaryeri, ödemesi onaylanmadan gönderilen
                            siparişlerin iptal riskini üstlenmiyor.
                        </p>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-64 flex-1">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            aria-label="Sipariş ara"
                            placeholder="Sipariş veya paket numarası, Enter'a basın"
                            defaultValue={filters.search}
                            className="pl-8"
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    apply({
                                        search: event.currentTarget.value,
                                    });
                                }
                            }}
                        />
                    </div>

                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) =>
                            apply({ status: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger className="w-48" aria-label="Durum">
                            <SelectValue placeholder="Durum" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tüm durumlar</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.connection === null
                                ? ALL
                                : String(filters.connection)
                        }
                        onValueChange={(value) =>
                            apply({
                                connection:
                                    value === ALL ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger className="w-44" aria-label="Kanal">
                            <SelectValue placeholder="Kanal" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tüm kanallar</SelectItem>
                            {connections.map((connection) => (
                                <SelectItem
                                    key={connection.id}
                                    value={String(connection.id)}
                                >
                                    {connection.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Eslesmemis satirlar ayri bir ekran degil, ayni listenin
                        filtresi: satir her zaman siparisi baglaminda incelenir. */}
                    <Toggle
                        variant="outline"
                        pressed={filters.unmatched}
                        onPressedChange={(pressed) =>
                            apply({ unmatched: pressed })
                        }
                        aria-label="Eşleşmemiş satırı olan siparişler"
                    >
                        <Unlink className="size-4" />
                        Eşleşmemiş satırlar
                        {unmatchedTotal > 0 && (
                            <Badge variant="destructive">
                                {unmatchedTotal}
                            </Badge>
                        )}
                    </Toggle>
                </div>

                {orders.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Bu filtrelerle eşleşen sipariş yok.
                    </p>
                ) : (
                    <div className="rounded-xl border">
                        {/* Sayfalama tiklamasi yok: <InfiniteScroll> sonraki sayfayi kendisi ekler. */}
                        <InfiniteScroll data="orders" buffer={300}>
                            <DataTable
                                columns={COLUMNS}
                                data={orders.data}
                                getRowId={(order) => String(order.id)}
                                rowClassName={(order) =>
                                    order.status === PENDING_PAYMENT
                                        ? 'bg-amber-50/60 dark:bg-amber-950/30'
                                        : undefined
                                }
                            />
                        </InfiniteScroll>
                    </div>
                )}
            </div>
        </>
    );
}

OrderIndex.layout = {
    breadcrumbs: [
        {
            title: 'Siparişler',
            href: index(),
        },
    ],
};
