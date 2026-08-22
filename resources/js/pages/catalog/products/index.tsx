import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { BulkEditDialog } from '@/components/catalog/bulk-edit-dialog';
import { PermissionButton } from '@/components/catalog/permission-button';
import { ProductStatusBadge } from '@/components/catalog/product-status-badge';
import Heading from '@/components/heading';
import {
    
    MarketplaceAvatarStack
} from '@/components/marketplace-avatar';
import type {MarketplaceChannel} from '@/components/marketplace-avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { usePermission } from '@/hooks/use-permission';
import { create, index, show } from '@/routes/products';

type ProductRow = {
    id: number;
    name: string;
    status: string;
    statusLabel: string;
    brand: string | null;
    variantCount: number;
    stock: number;
    price: string | null;
    /** Urunun yuklendigi pazaryerleri, baglanti basina tek kayit. */
    channels: MarketplaceChannel[];
    createdAt: string | null;
};

type Filters = {
    search: string;
    status: string | null;
    stock: string | null;
    connection: number | null;
    sort: string;
    direction: string;
};

type Props = {
    products: { data: ProductRow[] };
    filters: Filters;
    statuses: { value: string; label: string }[];
    connections: { id: number; name: string }[];
};

/** Radix Select bos deger kabul etmez; "hepsi" secenegi bu sabitle temsil edilir. */
const ALL = 'all';

export default function ProductIndex({
    products,
    filters,
    statuses,
    connections,
}: Props) {
    const can = usePermission();
    const canManage = can('catalog.manage');

    /** Toplu islem secimi. <InfiniteScroll> sayfa ekledikce secim korunur. */
    const [selected, setSelected] = useState<number[]>([]);

    const pageIds = products.data.map((product) => product.id);
    const allOnPageSelected =
        pageIds.length > 0 && pageIds.every((id) => selected.includes(id));

    const toggle = (id: number, checked: boolean): void =>
        setSelected(
            checked
                ? [...selected, id]
                : selected.filter((value) => value !== id),
        );

    const apply = (changes: Partial<Filters>): void => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );

        router.get(
            index.url(undefined, { query }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const sortBy = (column: 'name' | 'created_at'): void =>
        apply({
            sort: column,
            direction:
                filters.sort === column && filters.direction === 'asc'
                    ? 'desc'
                    : 'asc',
        });

    const sortIcon = (column: string) => {
        if (filters.sort !== column) {
            return null;
        }

        return filters.direction === 'asc' ? (
            <ArrowUp className="size-3.5" />
        ) : (
            <ArrowDown className="size-3.5" />
        );
    };

    return (
        <>
            <Head title="Ürünler" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Ürünler"
                        description="Katalogdaki ürünler, stok ve fiyat özetiyle."
                    />

                    {/* Devre disi bir <a> tiklanabilir kalir; buton + visit
                        yetkisiz kullaniciyi gercekten durdurur. */}
                    <PermissionButton
                        check={canManage}
                        onClick={() => router.visit(create.url())}
                    >
                        <Plus className="size-4" />
                        Yeni ürün
                    </PermissionButton>
                </div>

                {selected.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 px-4 py-2 text-sm">
                        <span className="font-medium">
                            {selected.length} ürün seçildi
                        </span>
                        <BulkEditDialog
                            productIds={selected}
                            check={canManage}
                            onApplied={() => setSelected([])}
                        />
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setSelected([])}
                        >
                            Seçimi temizle
                        </Button>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-64 flex-1">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            aria-label="Ürün ara"
                            placeholder="Ürün adı veya açıklamada ara, Enter'a basın"
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
                        <SelectTrigger className="w-40" aria-label="Durum">
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
                        value={filters.stock ?? ALL}
                        onValueChange={(value) =>
                            apply({ stock: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger className="w-40" aria-label="Stok">
                            <SelectValue placeholder="Stok" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tüm stoklar</SelectItem>
                            <SelectItem value="var">Stokta var</SelectItem>
                            <SelectItem value="yok">Stokta yok</SelectItem>
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
                </div>

                {products.data.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        <p>Bu filtrelerle eşleşen ürün yok.</p>
                        <PermissionButton
                            check={canManage}
                            variant="outline"
                            className="mt-4"
                            onClick={() => router.visit(create.url())}
                        >
                            <Plus className="size-4" />
                            İlk ürününüzü ekleyin
                        </PermissionButton>
                    </div>
                ) : (
                    <div className="rounded-xl border">
                        {/* Sayfalama tiklamasi yok: <InfiniteScroll> sonraki sayfayi kendisi ekler. */}
                        <InfiniteScroll data="products" buffer={300}>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">
                                            <Checkbox
                                                aria-label="Sayfadaki tüm ürünleri seç"
                                                checked={allOnPageSelected}
                                                onCheckedChange={(checked) =>
                                                    setSelected(
                                                        checked
                                                            ? [
                                                                  ...new Set([
                                                                      ...selected,
                                                                      ...pageIds,
                                                                  ]),
                                                              ]
                                                            : selected.filter(
                                                                  (id) =>
                                                                      !pageIds.includes(
                                                                          id,
                                                                      ),
                                                              ),
                                                    )
                                                }
                                            />
                                        </TableHead>
                                        <TableHead>
                                            <button
                                                type="button"
                                                className="flex items-center gap-1"
                                                onClick={() => sortBy('name')}
                                            >
                                                Ürün
                                                {sortIcon('name')}
                                            </button>
                                        </TableHead>
                                        <TableHead>Durum</TableHead>
                                        <TableHead>Pazaryerleri</TableHead>
                                        <TableHead className="text-right">
                                            Varyant
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Satılabilir stok
                                        </TableHead>
                                        <TableHead className="text-right">
                                            En düşük fiyat
                                        </TableHead>
                                        <TableHead>
                                            <button
                                                type="button"
                                                className="flex items-center gap-1"
                                                onClick={() =>
                                                    sortBy('created_at')
                                                }
                                            >
                                                Eklenme
                                                {sortIcon('created_at')}
                                            </button>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {products.data.map((product) => (
                                        <TableRow key={product.id}>
                                            <TableCell>
                                                <Checkbox
                                                    aria-label={`${product.name} seç`}
                                                    checked={selected.includes(
                                                        product.id,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggle(
                                                            product.id,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={show({
                                                        product: product.id,
                                                    })}
                                                    instant
                                                    className="font-medium underline-offset-4 hover:underline"
                                                >
                                                    {product.name}
                                                </Link>
                                                <span className="block text-xs text-muted-foreground">
                                                    {product.brand ??
                                                        'Markasız'}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <ProductStatusBadge
                                                    status={product.status}
                                                    label={product.statusLabel}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <MarketplaceAvatarStack
                                                    channels={product.channels}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {product.variantCount}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {product.stock}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {product.price ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {product.createdAt ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </InfiniteScroll>
                    </div>
                )}
            </div>
        </>
    );
}

ProductIndex.layout = {
    breadcrumbs: [
        {
            title: 'Ürünler',
            href: index(),
        },
    ],
};
