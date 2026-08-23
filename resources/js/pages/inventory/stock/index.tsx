import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { Boxes, Lock, Search, Warehouse } from 'lucide-react';
import { Fragment, useCallback, useMemo, useRef, useState } from 'react';
import { InlineNumberCell } from '@/components/catalog/inline-number-cell';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import type { StockTarget } from '@/components/inventory/stock-adjust-dialog';
import { StockAdjustDialog } from '@/components/inventory/stock-adjust-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Toggle } from '@/components/ui/toggle';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/stock';
import { index as warehousesScreen } from '@/routes/warehouses';

type Cell = {
    warehouseId: number;
    onHand: number;
    reserved: number;
    available: number;
    safetyStock: number;
    low: boolean;
};

type VariantRow = {
    id: number;
    sku: string;
    barcode: string | null;
    productId: number;
    productName: string;
    cells: Cell[];
};

type Warehouse = {
    id: number;
    name: string;
    code: string;
    isDefault: boolean;
};

type Filters = { search: string; low: boolean };

type Props = {
    variants: { data: VariantRow[] };
    warehouses: Warehouse[];
    filters: Filters;
};

const AVAILABLE_HINT =
    'Kullanılabilir stok düzenlenemez: veritabanında "eldeki − rezerve" olarak hesaplanır ve tek doğruluk kaynağı odur. Değiştirmek için eldeki veya rezerve miktarını güncelleyin.';

const STORAGE_KEY = 'kobiconnect_inventory_stock_column_widths';

const DEFAULT_COLUMN_WIDTHS: Record<string, number> = {
    sku: 160,
    product: 320,
};

const DEFAULT_SUBCOLUMN_WIDTHS = {
    onHand: 110,
    reserved: 110,
    available: 130,
    safetyStock: 110,
};

const MIN_COLUMN_WIDTHS: Record<string, number> = {
    sku: 90,
    product: 120,
    onHand: 75,
    reserved: 75,
    available: 90,
    safetyStock: 75,
};

function getInitialWidths(warehouses: Warehouse[]): Record<string, number> {
    const defaults: Record<string, number> = { ...DEFAULT_COLUMN_WIDTHS };
    for (const wh of warehouses) {
        defaults[`wh_${wh.id}_onHand`] = DEFAULT_SUBCOLUMN_WIDTHS.onHand;
        defaults[`wh_${wh.id}_reserved`] = DEFAULT_SUBCOLUMN_WIDTHS.reserved;
        defaults[`wh_${wh.id}_available`] = DEFAULT_SUBCOLUMN_WIDTHS.available;
        defaults[`wh_${wh.id}_safetyStock`] =
            DEFAULT_SUBCOLUMN_WIDTHS.safetyStock;
    }

    if (typeof window === 'undefined') {
        return defaults;
    }

    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            const parsed = JSON.parse(saved);
            return { ...defaults, ...parsed };
        }
    } catch {
        // ignore parse error
    }

    return defaults;
}

function ResizeHandle({
    colKey,
    onMouseDown,
    onDoubleClick,
    isResizing,
}: {
    colKey: string;
    onMouseDown: (
        colKey: string,
        e: React.MouseEvent | React.TouchEvent,
    ) => void;
    onDoubleClick: (colKey: string) => void;
    isResizing: boolean;
}) {
    return (
        <div
            role="separator"
            aria-orientation="vertical"
            aria-label="Sütun genişliğini ayarla"
            title="Genişletmek/daraltmak için sürükleyin, varsayılana dönmek için çift tıklayın"
            onMouseDown={(e) => onMouseDown(colKey, e)}
            onTouchStart={(e) => onMouseDown(colKey, e)}
            onDoubleClick={(e) => {
                e.stopPropagation();
                onDoubleClick(colKey);
            }}
            className="group/resizer absolute top-0 -right-1.5 z-20 flex h-full w-3 cursor-col-resize touch-none items-center justify-center select-none"
        >
            <div
                className={cn(
                    'h-full w-[2px] transition-colors',
                    isResizing
                        ? 'bg-primary'
                        : 'bg-transparent group-hover/resizer:bg-primary/70',
                )}
            />
        </div>
    );
}

export default function StockIndex({ variants, warehouses, filters }: Props) {
    const canManage = usePermission()('stock.manage');
    const [adjusting, setAdjusting] = useState<StockTarget | null>(null);

    const [columnWidths, setColumnWidths] = useState<Record<string, number>>(
        () => getInitialWidths(warehouses),
    );
    const [resizingCol, setResizingCol] = useState<string | null>(null);

    const columnWidthsRef = useRef(columnWidths);
    columnWidthsRef.current = columnWidths;

    const handleMouseDown = useCallback(
        (colKey: string, e: React.MouseEvent | React.TouchEvent) => {
            e.preventDefault();
            e.stopPropagation();

            const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
            const startX = clientX;
            const startWidth = columnWidthsRef.current[colKey] ?? 120;

            const subType = colKey.startsWith('wh_')
                ? colKey.split('_')[2]
                : colKey;
            const minWidth = MIN_COLUMN_WIDTHS[subType] ?? 70;

            setResizingCol(colKey);
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            let currentWidth = startWidth;

            const onMove = (moveEvent: MouseEvent | TouchEvent) => {
                const currentX =
                    'touches' in moveEvent
                        ? moveEvent.touches[0].clientX
                        : moveEvent.clientX;
                const deltaX = currentX - startX;
                const newWidth = Math.max(
                    minWidth,
                    Math.round(startWidth + deltaX),
                );
                currentWidth = newWidth;

                setColumnWidths((prev) => ({
                    ...prev,
                    [colKey]: newWidth,
                }));
            };

            const onEnd = () => {
                setResizingCol(null);
                document.body.style.cursor = '';
                document.body.style.userSelect = '';

                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onEnd);
                window.removeEventListener('touchmove', onMove);
                window.removeEventListener('touchend', onEnd);

                try {
                    const updated = {
                        ...columnWidthsRef.current,
                        [colKey]: currentWidth,
                    };
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
                } catch {
                    // ignore
                }
            };

            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onEnd);
            window.addEventListener('touchmove', onMove);
            window.addEventListener('touchend', onEnd);
        },
        [],
    );

    const handleDoubleClick = useCallback((colKey: string) => {
        const subType = colKey.startsWith('wh_')
            ? (colKey.split('_')[2] as keyof typeof DEFAULT_SUBCOLUMN_WIDTHS)
            : (colKey as keyof typeof DEFAULT_COLUMN_WIDTHS);
        const defaultWidth = colKey.startsWith('wh_')
            ? (DEFAULT_SUBCOLUMN_WIDTHS[
                  subType as keyof typeof DEFAULT_SUBCOLUMN_WIDTHS
              ] ?? 110)
            : (DEFAULT_COLUMN_WIDTHS[colKey] ?? 160);

        setColumnWidths((prev) => {
            const next = { ...prev, [colKey]: defaultWidth };
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
            } catch {
                // ignore
            }
            return next;
        });
    }, []);

    const totalWidth = useMemo(() => {
        let sum =
            (columnWidths.sku ?? DEFAULT_COLUMN_WIDTHS.sku) +
            (columnWidths.product ?? DEFAULT_COLUMN_WIDTHS.product);
        for (const warehouse of warehouses) {
            sum +=
                (columnWidths[`wh_${warehouse.id}_onHand`] ??
                    DEFAULT_SUBCOLUMN_WIDTHS.onHand) +
                (columnWidths[`wh_${warehouse.id}_reserved`] ??
                    DEFAULT_SUBCOLUMN_WIDTHS.reserved) +
                (columnWidths[`wh_${warehouse.id}_available`] ??
                    DEFAULT_SUBCOLUMN_WIDTHS.available) +
                (columnWidths[`wh_${warehouse.id}_safetyStock`] ??
                    DEFAULT_SUBCOLUMN_WIDTHS.safetyStock);
        }
        return sum;
    }, [columnWidths, warehouses]);

    const apply = (changes: Partial<Filters>): void => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) => value !== '' && value !== false,
            ),
        );

        router.get(
            index.url(undefined, { query }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    /** Satir ici duzenleme; 422 donerse Inertia degeri kendisi geri alir. */
    const patch = (
        variantId: number,
        warehouseId: number,
        payload: Record<string, number>,
    ): void => {
        router.patch(
            update.url({ variant: variantId, warehouse: warehouseId }),
            payload,
            { preserveScroll: true, onError: toastError },
        );
    };

    if (warehouses.length === 0) {
        return (
            <>
                <Head title="Stok Durumu" />

                <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                    <Heading title="Stok Durumu" />
                    <EmptyState
                        icon={Warehouse}
                        title="Depo tanımlanmamış"
                        description="Stok her zaman bir depoya yazılır; stok takibi yapabilmek için en az bir depo gerekir."
                        action={
                            <Button asChild variant="outline" size="sm">
                                <Link href={warehousesScreen()}>
                                    Depo tanımla
                                </Link>
                            </Button>
                        }
                    />
                </div>
            </>
        );
    }

    const hasActiveFilters = Boolean(filters.search || filters.low);

    return (
        <>
            <Head title="Stok Durumu" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <Heading
                    title="Stok Durumu"
                    badge={
                        <Badge variant="outline">Varyant × depo matrisi</Badge>
                    }
                    description="Eldeki miktarın değişimi sebep ister ve iz bırakır."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative max-w-sm flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            aria-label="SKU, barkod veya ürün adı ara"
                            placeholder="SKU, barkod veya ürün adı"
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

                    <Toggle
                        variant="outline"
                        pressed={filters.low}
                        onPressedChange={(low) => apply({ low })}
                    >
                        Kritik stok
                    </Toggle>
                </div>

                {variants.data.length === 0 ? (
                    <EmptyState
                        icon={hasActiveFilters ? Boxes : Warehouse}
                        title={
                            hasActiveFilters
                                ? 'Filtrelere uygun varyant bulunamadı'
                                : 'Henüz varyant kaydı yok'
                        }
                        description={
                            hasActiveFilters
                                ? 'Arama teriminizi veya filtre tercihlerinizi değiştirerek tekrar deneyebilirsiniz.'
                                : 'Kataloğunuzdaki ürünler ve varyantları burada listelenir.'
                        }
                        action={
                            hasActiveFilters ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        apply({ search: '', low: false })
                                    }
                                >
                                    Filtreleri Sıfırla
                                </Button>
                            ) : null
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border">
                        {/* Sayfalama tiklamasi yok: <InfiniteScroll> sonraki sayfayi kendisi ekler. */}
                        <InfiniteScroll data="variants" buffer={300}>
                            <Table
                                style={{
                                    width: `${totalWidth}px`,
                                    minWidth: '100%',
                                    tableLayout: 'fixed',
                                }}
                            >
                                <colgroup>
                                    <col
                                        style={{
                                            width: `${columnWidths.sku ?? DEFAULT_COLUMN_WIDTHS.sku}px`,
                                        }}
                                    />
                                    <col
                                        style={{
                                            width: `${columnWidths.product ?? DEFAULT_COLUMN_WIDTHS.product}px`,
                                        }}
                                    />
                                    {warehouses.map((warehouse) => (
                                        <Fragment key={warehouse.id}>
                                            <col
                                                style={{
                                                    width: `${columnWidths[`wh_${warehouse.id}_onHand`] ?? DEFAULT_SUBCOLUMN_WIDTHS.onHand}px`,
                                                }}
                                            />
                                            <col
                                                style={{
                                                    width: `${columnWidths[`wh_${warehouse.id}_reserved`] ?? DEFAULT_SUBCOLUMN_WIDTHS.reserved}px`,
                                                }}
                                            />
                                            <col
                                                style={{
                                                    width: `${columnWidths[`wh_${warehouse.id}_available`] ?? DEFAULT_SUBCOLUMN_WIDTHS.available}px`,
                                                }}
                                            />
                                            <col
                                                style={{
                                                    width: `${columnWidths[`wh_${warehouse.id}_safetyStock`] ?? DEFAULT_SUBCOLUMN_WIDTHS.safetyStock}px`,
                                                }}
                                            />
                                        </Fragment>
                                    ))}
                                </colgroup>

                                <TableHeader>
                                    <TableRow>
                                        <TableHead
                                            rowSpan={2}
                                            className="relative select-none"
                                        >
                                            <span className="truncate">
                                                SKU
                                            </span>
                                            <ResizeHandle
                                                colKey="sku"
                                                onMouseDown={handleMouseDown}
                                                onDoubleClick={
                                                    handleDoubleClick
                                                }
                                                isResizing={
                                                    resizingCol === 'sku'
                                                }
                                            />
                                        </TableHead>
                                        <TableHead
                                            rowSpan={2}
                                            className="relative select-none"
                                        >
                                            <span className="truncate">
                                                Ürün
                                            </span>
                                            <ResizeHandle
                                                colKey="product"
                                                onMouseDown={handleMouseDown}
                                                onDoubleClick={
                                                    handleDoubleClick
                                                }
                                                isResizing={
                                                    resizingCol === 'product'
                                                }
                                            />
                                        </TableHead>
                                        {warehouses.map((warehouse) => (
                                            <TableHead
                                                key={warehouse.id}
                                                colSpan={4}
                                                className="relative border-l text-center select-none"
                                            >
                                                <span className="truncate">
                                                    {warehouse.name}
                                                    {warehouse.isDefault &&
                                                        ' (varsayılan)'}
                                                </span>
                                                <ResizeHandle
                                                    colKey={`wh_${warehouse.id}_safetyStock`}
                                                    onMouseDown={
                                                        handleMouseDown
                                                    }
                                                    onDoubleClick={
                                                        handleDoubleClick
                                                    }
                                                    isResizing={
                                                        resizingCol ===
                                                        `wh_${warehouse.id}_safetyStock`
                                                    }
                                                />
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                    <TableRow>
                                        {warehouses.map((warehouse) => (
                                            <Fragment key={warehouse.id}>
                                                <TableHead className="relative border-l text-right select-none">
                                                    <span className="truncate">
                                                        Eldeki
                                                    </span>
                                                    <ResizeHandle
                                                        colKey={`wh_${warehouse.id}_onHand`}
                                                        onMouseDown={
                                                            handleMouseDown
                                                        }
                                                        onDoubleClick={
                                                            handleDoubleClick
                                                        }
                                                        isResizing={
                                                            resizingCol ===
                                                            `wh_${warehouse.id}_onHand`
                                                        }
                                                    />
                                                </TableHead>
                                                <TableHead className="relative text-right select-none">
                                                    <span className="truncate">
                                                        Rezerve
                                                    </span>
                                                    <ResizeHandle
                                                        colKey={`wh_${warehouse.id}_reserved`}
                                                        onMouseDown={
                                                            handleMouseDown
                                                        }
                                                        onDoubleClick={
                                                            handleDoubleClick
                                                        }
                                                        isResizing={
                                                            resizingCol ===
                                                            `wh_${warehouse.id}_reserved`
                                                        }
                                                    />
                                                </TableHead>
                                                <TableHead className="relative text-right select-none">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span
                                                                tabIndex={0}
                                                                className="inline-flex items-center gap-1"
                                                            >
                                                                Kullanılabilir
                                                                <Lock className="size-3" />
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent className="max-w-xs">
                                                            {AVAILABLE_HINT}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <ResizeHandle
                                                        colKey={`wh_${warehouse.id}_available`}
                                                        onMouseDown={
                                                            handleMouseDown
                                                        }
                                                        onDoubleClick={
                                                            handleDoubleClick
                                                        }
                                                        isResizing={
                                                            resizingCol ===
                                                            `wh_${warehouse.id}_available`
                                                        }
                                                    />
                                                </TableHead>
                                                <TableHead className="relative text-right select-none">
                                                    <span className="truncate">
                                                        Güvenlik
                                                    </span>
                                                    <ResizeHandle
                                                        colKey={`wh_${warehouse.id}_safetyStock`}
                                                        onMouseDown={
                                                            handleMouseDown
                                                        }
                                                        onDoubleClick={
                                                            handleDoubleClick
                                                        }
                                                        isResizing={
                                                            resizingCol ===
                                                            `wh_${warehouse.id}_safetyStock`
                                                        }
                                                    />
                                                </TableHead>
                                            </Fragment>
                                        ))}
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {variants.data.map((variant) => (
                                        <TableRow key={variant.id}>
                                            <TableCell className="overflow-hidden font-medium text-ellipsis whitespace-nowrap tabular-nums">
                                                {variant.sku}
                                            </TableCell>
                                            <TableCell
                                                className="truncate overflow-hidden text-muted-foreground"
                                                title={variant.productName}
                                            >
                                                {variant.productName}
                                            </TableCell>

                                            {variant.cells.map(
                                                (cell, cellIndex) => (
                                                    <Fragment
                                                        key={cell.warehouseId}
                                                    >
                                                        <TableCell className="overflow-hidden border-l text-right">
                                                            {/* Eldeki stok satir ici degistirilmez: sebep sorulur. */}
                                                            <PermissionButton
                                                                check={
                                                                    canManage
                                                                }
                                                                variant="ghost"
                                                                size="sm"
                                                                className="tabular-nums"
                                                                aria-label={`${variant.sku} · ${warehouses[cellIndex]?.name ?? ''} eldeki stoğu düzelt`}
                                                                onClick={() =>
                                                                    setAdjusting(
                                                                        {
                                                                            variantId:
                                                                                variant.id,
                                                                            sku: variant.sku,
                                                                            warehouseId:
                                                                                cell.warehouseId,
                                                                            warehouseName:
                                                                                warehouses[
                                                                                    cellIndex
                                                                                ]
                                                                                    ?.name ??
                                                                                '',
                                                                            onHand: cell.onHand,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                {cell.onHand}
                                                            </PermissionButton>
                                                        </TableCell>

                                                        <TableCell className="overflow-hidden text-right">
                                                            <InlineNumberCell
                                                                value={
                                                                    cell.reserved
                                                                }
                                                                display={String(
                                                                    cell.reserved,
                                                                )}
                                                                check={
                                                                    canManage
                                                                }
                                                                label={`${variant.sku} rezerve stok`}
                                                                onCommit={(
                                                                    value,
                                                                ) =>
                                                                    patch(
                                                                        variant.id,
                                                                        cell.warehouseId,
                                                                        {
                                                                            reserved:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </TableCell>

                                                        <TableCell className="overflow-hidden text-right">
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <span
                                                                        tabIndex={
                                                                            0
                                                                        }
                                                                        className={cn(
                                                                            'inline-flex items-center gap-1 tabular-nums',
                                                                            cell.low &&
                                                                                'font-medium text-destructive',
                                                                        )}
                                                                    >
                                                                        {
                                                                            cell.available
                                                                        }
                                                                        <Lock
                                                                            className="size-3 text-muted-foreground"
                                                                            aria-hidden
                                                                        />
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent className="max-w-xs">
                                                                    {
                                                                        AVAILABLE_HINT
                                                                    }
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </TableCell>

                                                        <TableCell className="overflow-hidden text-right">
                                                            <InlineNumberCell
                                                                value={
                                                                    cell.safetyStock
                                                                }
                                                                display={String(
                                                                    cell.safetyStock,
                                                                )}
                                                                check={
                                                                    canManage
                                                                }
                                                                label={`${variant.sku} güvenlik stoğu`}
                                                                onCommit={(
                                                                    value,
                                                                ) =>
                                                                    patch(
                                                                        variant.id,
                                                                        cell.warehouseId,
                                                                        {
                                                                            safety_stock:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                        </TableCell>
                                                    </Fragment>
                                                ),
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </InfiniteScroll>
                    </div>
                )}
            </div>

            <StockAdjustDialog
                target={adjusting}
                onClose={() => setAdjusting(null)}
            />
        </>
    );
}

StockIndex.layout = {
    breadcrumbs: [
        {
            title: 'Stok Durumu',
            href: index(),
        },
    ],
};
