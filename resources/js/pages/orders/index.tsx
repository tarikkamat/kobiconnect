import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Copy,
    Eye,
    Package,
    PackageOpen,
    Search,
    TriangleAlert,
    Unlink,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { MarketplaceLogo } from '@/components/marketplace-avatar';
import { OrderPreviewSheet } from '@/components/orders/order-preview-sheet';
import type { OrderPreviewSummary } from '@/components/orders/order-preview-sheet';
import {
    OrderStatusBadge,
    PENDING_PAYMENT,
} from '@/components/orders/order-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/orders';

type OrderRow = OrderPreviewSummary;

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
 * Operasyonel is akisina gore durum sekmeleri.
 * Poppins & Inter tipografisi ile temiz alt cizgili sekme yapisi.
 */
const STATUS_TABS: { value: string | null; label: string }[] = [
    { value: null, label: 'Tüm Siparişler' },
    { value: 'pending_payment', label: 'Ödeme Bekleyen' },
    { value: 'created', label: 'Gönderime Hazır' },
    { value: 'picking', label: 'Hazırlanıyor' },
    { value: 'shipped', label: 'Kargoda' },
    { value: 'delivered', label: 'Teslim Edildi' },
    { value: 'cancelled', label: 'İptal / İade' },
];

export default function OrderIndex({
    orders,
    filters,
    statuses,
    connections,
    unmatchedTotal,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search ?? '');
    const [copiedOrderId, setCopiedOrderId] = useState<number | null>(null);
    const [selectedOrder, setSelectedOrder] = useState<OrderRow | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const searchInputRef = useRef<HTMLInputElement>(null);

    // Filtre disaridan degisince arama kutusunu esitle. Effect + setState
    // basamakli render uretir; React'in onerdigi yol render sirasinda
    // ayarlamak: https://react.dev/learn/you-might-not-need-an-effect
    const [lastSearch, setLastSearch] = useState(filters.search);

    if (filters.search !== lastSearch) {
        setLastSearch(filters.search);
        setSearchValue(filters.search ?? '');
    }

    // '/' keyboard shortcut to focus search
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (
                e.key === '/' &&
                document.activeElement?.tagName !== 'INPUT' &&
                document.activeElement?.tagName !== 'TEXTAREA'
            ) {
                e.preventDefault();
                searchInputRef.current?.focus();
            }
        };
        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

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

    const copyOrderNumber = (e: React.MouseEvent, order: OrderRow) => {
        e.stopPropagation();
        navigator.clipboard.writeText(order.orderNumber);
        setCopiedOrderId(order.id);
        toast.success(`Sipariş no kopyalandı: ${order.orderNumber}`);
        setTimeout(() => setCopiedOrderId(null), 2000);
    };

    const openPreview = (order: OrderRow) => {
        setSelectedOrder(order);
        setPreviewOpen(true);
    };

    const hasPendingPayment = orders.data.some(
        (order) => order.status === PENDING_PAYMENT,
    );

    const hasActiveFilters = Boolean(
        filters.search ||
        filters.status ||
        filters.connection ||
        filters.unmatched,
    );

    const clearAllFilters = () => {
        setSearchValue('');
        router.get(
            index.url(),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // Active status label lookup
    const activeStatusLabel =
        statuses.find((s) => s.value === filters.status)?.label ??
        filters.status;

    // Active connection name lookup
    const activeConnectionName = connections.find(
        (c) => c.id === filters.connection,
    )?.name;

    return (
        <>
            <Head title="Siparişler" />

            <div className="flex flex-col gap-6 p-4 font-sans sm:p-6 lg:p-8">
                {/* Header */}
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Siparişler"
                        description="Pazaryerlerinden gelen sipariş paketleri ve gönderim durumu."
                    />
                </div>

                {/* Status Tabs */}
                <div className="-mb-2 border-b border-border">
                    <nav
                        className="-mb-px flex space-x-6 overflow-x-auto pb-px"
                        aria-label="Sipariş Durumları"
                    >
                        {STATUS_TABS.map((tab) => {
                            const isSelected =
                                tab.value === null
                                    ? filters.status === null
                                    : filters.status === tab.value;

                            return (
                                <button
                                    key={tab.label}
                                    type="button"
                                    onClick={() => apply({ status: tab.value })}
                                    className={cn(
                                        'inline-flex items-center gap-2 border-b-2 px-1 pb-3 text-sm font-medium whitespace-nowrap transition-colors',
                                        isSelected
                                            ? 'border-primary font-semibold text-primary'
                                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                                    )}
                                >
                                    <span>{tab.label}</span>
                                    {tab.value === 'pending_payment' &&
                                        hasPendingPayment && (
                                            <span className="flex size-2 rounded-full bg-warning" />
                                        )}
                                </button>
                            );
                        })}
                    </nav>
                </div>

                {/* Contextual Alert for Pending Payment */}
                {hasPendingPayment && filters.status !== 'pending_payment' && (
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-warning/25 bg-warning/10 px-4 py-3 text-xs text-warning sm:text-sm">
                        <div className="flex min-w-0 items-center gap-2.5">
                            <TriangleAlert className="size-4 shrink-0" />
                            <p className="truncate">
                                <span className="font-semibold">
                                    Ödeme bekleyen siparişler var:
                                </span>{' '}
                                Ödemesi onaylanmadan gönderilen siparişlerin
                                iptal riskini pazaryeri üstlenmiyor.
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-7 shrink-0 border-warning/40 text-xs text-warning hover:bg-warning/20"
                            onClick={() => apply({ status: 'pending_payment' })}
                        >
                            Bekleyenleri Gör
                        </Button>
                    </div>
                )}

                {/* Contextual Alert for Unmatched Filter */}
                {filters.unmatched && (
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-destructive/25 bg-destructive/10 px-4 py-2.5 text-xs text-destructive sm:text-sm">
                        <div className="flex items-center gap-2.5">
                            <Unlink className="size-4 shrink-0" />
                            <p>
                                <span className="font-semibold">
                                    Eşleşmemiş satır filtresi aktif:
                                </span>{' '}
                                Bu satırlar katalogdaki bir varyantla
                                eşleştirilene kadar stok düşülemez.
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="ghost"
                            className="h-7 text-xs text-destructive hover:bg-destructive/20"
                            onClick={() => apply({ unmatched: false })}
                        >
                            Filtreyi Kaldır
                        </Button>
                    </div>
                )}

                {/* Search & Filter Bar */}
                <div className="flex flex-col gap-2.5">
                    <div className="flex flex-wrap items-center gap-2">
                        {/* Search Input */}
                        <div className="relative min-w-64 flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                ref={searchInputRef}
                                type="search"
                                aria-label="Sipariş ara"
                                placeholder="Sipariş veya paket no ile ara... (Enter)"
                                value={searchValue}
                                onChange={(e) => setSearchValue(e.target.value)}
                                className="h-9 pr-16 pl-9"
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        apply({ search: searchValue });
                                    }
                                }}
                            />
                            <div className="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1">
                                {searchValue && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearchValue('');
                                            apply({ search: '' });
                                        }}
                                        className="rounded p-1 text-muted-foreground hover:bg-secondary hover:text-foreground"
                                        title="Aramayı temizle"
                                    >
                                        <X className="size-3.5" />
                                    </button>
                                )}
                                {!searchValue && (
                                    <kbd className="hidden h-5 items-center gap-1 rounded border border-border bg-muted px-1.5 font-sans text-[10px] text-muted-foreground select-none sm:inline-flex">
                                        /
                                    </kbd>
                                )}
                            </div>
                        </div>

                        {/* Status Select */}
                        <Select
                            value={filters.status ?? ALL}
                            onValueChange={(value) =>
                                apply({ status: value === ALL ? null : value })
                            }
                        >
                            <SelectTrigger
                                className="h-9 w-44"
                                aria-label="Durum Filtresi"
                            >
                                <SelectValue placeholder="Tüm Durumlar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Tüm Durumlar
                                </SelectItem>
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

                        {/* Channel / Connection Select */}
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
                            <SelectTrigger
                                className="h-9 w-44"
                                aria-label="Kanal Filtresi"
                            >
                                <SelectValue placeholder="Tüm Kanallar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Tüm Kanallar
                                </SelectItem>
                                {connections.map((conn) => (
                                    <SelectItem
                                        key={conn.id}
                                        value={String(conn.id)}
                                    >
                                        {conn.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {/* Unmatched Filter Toggle */}
                        <Button
                            type="button"
                            variant={filters.unmatched ? 'default' : 'outline'}
                            size="sm"
                            className={cn(
                                'h-9 gap-1.5',
                                filters.unmatched &&
                                    'border-border bg-secondary text-foreground hover:bg-secondary/80',
                            )}
                            onClick={() =>
                                apply({ unmatched: !filters.unmatched })
                            }
                            aria-label="Eşleşmemiş satırı olan siparişler"
                        >
                            <Unlink className="size-3.5" />
                            <span>Eşleşmemiş Satırlar</span>
                            {unmatchedTotal > 0 && (
                                <Badge
                                    variant="destructive"
                                    className="ml-1 h-4 font-sans text-[10px] font-semibold tabular-nums"
                                >
                                    {unmatchedTotal}
                                </Badge>
                            )}
                        </Button>

                        {/* Clear all filters button if active */}
                        {hasActiveFilters && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-9 text-xs text-muted-foreground hover:text-foreground"
                                onClick={clearAllFilters}
                            >
                                <X className="mr-1 size-3.5" />
                                Temizle
                            </Button>
                        )}
                    </div>

                    {/* Active Filter Chips */}
                    {hasActiveFilters && (
                        <div className="flex flex-wrap items-center gap-1.5 pt-1 text-xs">
                            <span className="text-muted-foreground">
                                Aktif filtreler:
                            </span>
                            {filters.search && (
                                <Badge
                                    variant="secondary"
                                    className="gap-1 border border-border"
                                >
                                    <span>
                                        Arama: &quot;{filters.search}&quot;
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setSearchValue('');
                                            apply({ search: '' });
                                        }}
                                        className="rounded-full hover:bg-muted"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </Badge>
                            )}
                            {filters.status && (
                                <Badge
                                    variant="secondary"
                                    className="gap-1 border border-border"
                                >
                                    <span>Durum: {activeStatusLabel}</span>
                                    <button
                                        type="button"
                                        onClick={() => apply({ status: null })}
                                        className="rounded-full hover:bg-muted"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </Badge>
                            )}
                            {filters.connection && (
                                <Badge
                                    variant="secondary"
                                    className="gap-1 border border-border"
                                >
                                    <span>Kanal: {activeConnectionName}</span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            apply({ connection: null })
                                        }
                                        className="rounded-full hover:bg-muted"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </Badge>
                            )}
                            {filters.unmatched && (
                                <Badge variant="destructive" className="gap-1">
                                    <span>Eşleşmemiş satırlar</span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            apply({ unmatched: false })
                                        }
                                        className="rounded-full hover:bg-destructive/40"
                                    >
                                        <X className="size-3" />
                                    </button>
                                </Badge>
                            )}
                        </div>
                    )}
                </div>

                {/* Orders Content */}
                {orders.data.length === 0 ? (
                    <EmptyState
                        icon={hasActiveFilters ? PackageOpen : Package}
                        title={
                            hasActiveFilters
                                ? 'Filtrelere uygun sipariş bulunamadı'
                                : 'Henüz sipariş kaydı yok'
                        }
                        description={
                            hasActiveFilters
                                ? 'Arama teriminizi veya filtre tercihlerinizi değiştirerek tekrar deneyebilirsiniz.'
                                : 'Pazaryeri bağlantılarınızdan yeni siparişler çekildikçe burada listelenecektir.'
                        }
                        action={
                            hasActiveFilters ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={clearAllFilters}
                                >
                                    Filtreleri Sıfırla
                                </Button>
                            ) : null
                        }
                    />
                ) : (
                    /* Table Container */
                    <div className="overflow-hidden rounded-lg border border-border bg-card">
                        <InfiniteScroll data="orders" buffer={300}>
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-b border-border hover:bg-transparent">
                                        <TableHead className="w-24 min-w-[90px]">
                                            Kanal
                                        </TableHead>
                                        <TableHead className="min-w-[200px]">
                                            Sipariş & Paket
                                        </TableHead>
                                        <TableHead className="min-w-[150px]">
                                            Durum
                                        </TableHead>
                                        <TableHead className="min-w-[160px]">
                                            Müşteri & Teslimat
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Kalem
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Tutar
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Sipariş Tarihi
                                        </TableHead>
                                        <TableHead className="w-20 text-center">
                                            İşlem
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orders.data.map((order) => {
                                        const isPending =
                                            order.status === PENDING_PAYMENT;
                                        const isCopied =
                                            copiedOrderId === order.id;

                                        return (
                                            <TableRow
                                                key={order.id}
                                                onClick={() =>
                                                    openPreview(order)
                                                }
                                                className={cn(
                                                    'cursor-pointer transition-colors duration-150',
                                                    isPending
                                                        ? 'bg-warning/[0.04] hover:bg-warning/[0.08]'
                                                        : 'hover:bg-secondary/50',
                                                )}
                                            >
                                                {/* Marketplace Logo */}
                                                <TableCell className="w-24 min-w-[90px] py-3">
                                                    {order.marketplace ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <div
                                                                    tabIndex={0}
                                                                    className="flex items-center"
                                                                >
                                                                    <MarketplaceLogo
                                                                        code={
                                                                            order.marketplace
                                                                        }
                                                                        name={
                                                                            order.connection ??
                                                                            undefined
                                                                        }
                                                                        height="h-5 sm:h-6"
                                                                    />
                                                                </div>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {order.connection ??
                                                                    order.marketplace}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </TableCell>

                                                {/* Order & Package */}
                                                <TableCell>
                                                    <div className="flex items-center gap-1.5">
                                                        <Link
                                                            href={show({
                                                                order: order.id,
                                                            })}
                                                            instant
                                                            onClick={(e) =>
                                                                e.stopPropagation()
                                                            }
                                                            className="font-sans font-semibold text-foreground tabular-nums underline-offset-4 hover:text-primary hover:underline"
                                                        >
                                                            {order.orderNumber}
                                                        </Link>
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <button
                                                                    type="button"
                                                                    onClick={(
                                                                        e,
                                                                    ) =>
                                                                        copyOrderNumber(
                                                                            e,
                                                                            order,
                                                                        )
                                                                    }
                                                                    className="rounded p-1 text-muted-foreground transition-colors hover:bg-secondary hover:text-foreground"
                                                                    aria-label="Sipariş no kopyala"
                                                                >
                                                                    {isCopied ? (
                                                                        <Check className="size-3 text-success" />
                                                                    ) : (
                                                                        <Copy className="size-3" />
                                                                    )}
                                                                </button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {isCopied
                                                                    ? 'Kopyalandı!'
                                                                    : 'Sipariş No Kopyala'}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                    <span className="block font-sans text-xs text-muted-foreground">
                                                        Paket{' '}
                                                        <span className="font-medium tabular-nums">
                                                            {order.packageId}
                                                        </span>
                                                        {order.connection
                                                            ? ` · ${order.connection}`
                                                            : ''}
                                                    </span>
                                                </TableCell>

                                                {/* Status */}
                                                <TableCell>
                                                    <OrderStatusBadge
                                                        status={order.status}
                                                        label={
                                                            order.statusLabel
                                                        }
                                                    />
                                                    {order.externalStatus && (
                                                        <span className="block font-sans text-[11px] text-muted-foreground">
                                                            {
                                                                order.externalStatus
                                                            }
                                                        </span>
                                                    )}
                                                </TableCell>

                                                {/* Customer & Location */}
                                                <TableCell>
                                                    <div className="text-sm font-medium text-foreground">
                                                        {order.customer ?? '—'}
                                                    </div>
                                                    {order.customerLocation && (
                                                        <span className="block max-w-[180px] truncate text-xs text-muted-foreground">
                                                            {
                                                                order.customerLocation
                                                            }
                                                        </span>
                                                    )}
                                                </TableCell>

                                                {/* Line Count & Unmatched */}
                                                <TableCell className="text-right font-sans tabular-nums">
                                                    <div className="flex items-center justify-end gap-1.5">
                                                        <span className="font-medium">
                                                            {order.lineCount}{' '}
                                                            <span className="text-xs font-normal text-muted-foreground">
                                                                adet
                                                            </span>
                                                        </span>
                                                        {order.unmatchedCount >
                                                            0 && (
                                                            <Badge
                                                                variant="destructive"
                                                                className="h-4 font-sans text-[10px] font-semibold tabular-nums"
                                                            >
                                                                {
                                                                    order.unmatchedCount
                                                                }{' '}
                                                                eşleşmedi
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </TableCell>

                                                {/* Total Price */}
                                                <TableCell className="text-right font-sans font-semibold text-foreground tabular-nums">
                                                    {order.total ?? '—'}
                                                </TableCell>

                                                {/* Placed At */}
                                                <TableCell className="text-right font-sans text-xs text-muted-foreground tabular-nums">
                                                    {order.placedAt ?? '—'}
                                                </TableCell>

                                                {/* Actions */}
                                                <TableCell className="w-20 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="size-7 text-muted-foreground hover:text-foreground"
                                                                    onClick={(
                                                                        e,
                                                                    ) => {
                                                                        e.stopPropagation();
                                                                        openPreview(
                                                                            order,
                                                                        );
                                                                    }}
                                                                    aria-label="Hızlı Önizleme"
                                                                >
                                                                    <Eye className="size-3.5" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Hızlı Önizleme
                                                            </TooltipContent>
                                                        </Tooltip>

                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    asChild
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="size-7 text-muted-foreground hover:text-foreground"
                                                                    onClick={(
                                                                        e,
                                                                    ) =>
                                                                        e.stopPropagation()
                                                                    }
                                                                >
                                                                    <Link
                                                                        href={show(
                                                                            {
                                                                                order: order.id,
                                                                            },
                                                                        )}
                                                                        instant
                                                                        aria-label="Detay Sayfasına Git"
                                                                    >
                                                                        <ChevronRight className="size-3.5" />
                                                                    </Link>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                Detay Sayfası
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </InfiniteScroll>
                    </div>
                )}
            </div>

            {/* Quick Order Preview Slide-over Drawer */}
            <OrderPreviewSheet
                order={selectedOrder}
                open={previewOpen}
                onOpenChange={setPreviewOpen}
            />
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
