import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { Lock, Search } from 'lucide-react';
import { Fragment, useState } from 'react';
import { InlineNumberCell } from '@/components/catalog/inline-number-cell';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import Heading from '@/components/heading';
import type { StockTarget } from '@/components/inventory/stock-adjust-dialog';
import { StockAdjustDialog } from '@/components/inventory/stock-adjust-dialog';
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

export default function StockIndex({ variants, warehouses, filters }: Props) {
    const canManage = usePermission()('stock.manage');
    const [adjusting, setAdjusting] = useState<StockTarget | null>(null);

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

                <div className="flex flex-col gap-4 p-4">
                    <Heading title="Stok Durumu" />
                    <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        <p>
                            Stok her zaman bir depoya yazılır; henüz depo
                            tanımlanmamış.
                        </p>
                        <Button asChild variant="outline" className="mt-3">
                            <Link href={warehousesScreen()}>Depo tanımla</Link>
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Stok Durumu" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Stok Durumu"
                    description="Varyant × depo matrisi. Eldeki miktarın değişimi sebep ister ve iz bırakır."
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
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Bu filtrelerle eşleşen varyant yok.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        {/* Sayfalama tiklamasi yok: <InfiniteScroll> sonraki sayfayi kendisi ekler. */}
                        <InfiniteScroll data="variants" buffer={300}>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead rowSpan={2}>SKU</TableHead>
                                        <TableHead rowSpan={2}>Ürün</TableHead>
                                        {warehouses.map((warehouse) => (
                                            <TableHead
                                                key={warehouse.id}
                                                colSpan={4}
                                                className="border-l text-center"
                                            >
                                                {warehouse.name}
                                                {warehouse.isDefault &&
                                                    ' (varsayılan)'}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                    <TableRow>
                                        {warehouses.map((warehouse) => (
                                            <Fragment key={warehouse.id}>
                                                <TableHead className="border-l text-right">
                                                    Eldeki
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Rezerve
                                                </TableHead>
                                                <TableHead className="text-right">
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
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Güvenlik
                                                </TableHead>
                                            </Fragment>
                                        ))}
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {variants.data.map((variant) => (
                                        <TableRow key={variant.id}>
                                            <TableCell className="font-medium whitespace-nowrap">
                                                {variant.sku}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {variant.productName}
                                            </TableCell>

                                            {variant.cells.map(
                                                (cell, cellIndex) => (
                                                    <Fragment
                                                        key={cell.warehouseId}
                                                    >
                                                        <TableCell className="border-l text-right">
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

                                                        <TableCell className="text-right">
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

                                                        <TableCell className="text-right">
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

                                                        <TableCell className="text-right">
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
