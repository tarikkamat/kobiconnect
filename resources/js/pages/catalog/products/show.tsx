import { Form, Head, router, WhenVisible } from '@inertiajs/react';
import { Trash2, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Catalog/ProductController';
import { InlineNumberCell } from '@/components/catalog/inline-number-cell';
import { PermissionButton } from '@/components/catalog/permission-button';
import { ProductStatusBadge } from '@/components/catalog/product-status-badge';
import { toastError } from '@/components/catalog/toast-error';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { usePermission } from '@/hooks/use-permission';
import { destroy, index } from '@/routes/products';
import { price as priceRoute, stock as stockRoute } from '@/routes/variants';

type VariantRow = {
    id: number;
    sku: string;
    barcode: string | null;
    onHand: number;
    available: number;
    price: number | null;
    priceFormatted: string | null;
};

type Props = {
    product: {
        id: number;
        name: string;
        description: string | null;
        brandId: number | null;
        categoryId: number | null;
        status: string;
        statusLabel: string;
        /** Silme uyarisinin dayanagi: urun pazaryerinde yayinda olabilir. */
        listingCount: number;
    };
    variants: VariantRow[];
    images?: { id: number; url: string; position: number }[];
    warehouse: { id: number; name: string } | null;
    brands: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
};

/** Radix Select bos deger kabul etmez; "secilmedi" bu sabitle temsil edilir. */
const NONE = 'none';

/** Bicimlenmis deger sunucudan gelir; iyimser guncelleme sirasinda ham deger gosterilir. */
const priceDisplay = (variant: VariantRow): string => {
    if (variant.priceFormatted !== null) {
        return variant.priceFormatted;
    }

    return variant.price === null ? 'Fiyat yok' : String(variant.price);
};

const TABS = [
    { value: 'genel', label: 'Genel' },
    { value: 'varyantlar', label: 'Varyantlar' },
    { value: 'gorseller', label: 'Görseller' },
];

export default function ProductShow({
    product,
    variants,
    images,
    warehouse,
    brands,
    categories,
    statuses,
}: Props) {
    const can = usePermission();
    const canManage = can('catalog.manage');
    const canStock = can('stock.manage');

    const [tab, setTab] = useState('genel');
    const [brandId, setBrandId] = useState(product.brandId);
    const [categoryId, setCategoryId] = useState(product.categoryId);

    /**
     * Stok ve fiyat degisiklikleri `optimistic` visit ile aninda gosterilir;
     * istek basarisiz olursa Inertia degeri geri alir — FRONTEND-PLAN.md §3.
     */
    const patchVariant = (
        url: string,
        payload: Record<string, number>,
        patch: (variant: VariantRow) => VariantRow,
        variantId: number,
    ): void => {
        router.patch(url, payload, {
            preserveScroll: true,
            optimistic: (props) => ({
                variants: (props.variants as VariantRow[]).map((variant) =>
                    variant.id === variantId ? patch(variant) : variant,
                ),
            }),
            // Geri alma otomatik; sebebi kullaniciya soylemek bize dusuyor.
            onError: toastError,
        });
    };

    return (
        <>
            <Head title={product.name} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Heading title={product.name} />
                        <ProductStatusBadge
                            status={product.status}
                            label={product.statusLabel}
                        />
                    </div>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="outline">
                                <Trash2 className="size-4" />
                                Sil
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Ürünü sil</DialogTitle>
                                <DialogDescription>
                                    {product.listingCount > 0 ? (
                                        <span className="flex items-start gap-2 text-warning">
                                            <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                            <span>
                                                Bu ürünün{' '}
                                                <strong>
                                                    <span className="font-mono tabular-nums">
                                                        {product.listingCount}
                                                    </span>{' '}
                                                    kanal listelemesi
                                                </strong>{' '}
                                                var; ürün şu anda pazaryerinde
                                                yayında olabilir. Silmek
                                                listelemeyi pazaryerinden
                                                kaldırmaz — önce kanaldan
                                                yayından kaldırın.
                                            </span>
                                        </span>
                                    ) : (
                                        '“' +
                                        product.name +
                                        '” ve bütün varyantları silinecek. Bu işlem geri alınamaz.'
                                    )}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter>
                                <PermissionButton
                                    check={canManage}
                                    variant="destructive"
                                    onClick={() =>
                                        router.delete(
                                            destroy.url({
                                                product: product.id,
                                            }),
                                            {
                                                // Sunucu acik onay ister; diyalogdaki
                                                // "Sil" tiklamasi o onaydir.
                                                data: {
                                                    acknowledge_listings: true,
                                                },
                                                onError: toastError,
                                            },
                                        )
                                    }
                                >
                                    Sil
                                </PermissionButton>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={tab}
                    onValueChange={(value) => value && setTab(value)}
                    className="w-fit"
                >
                    {TABS.map((item) => (
                        <ToggleGroupItem key={item.value} value={item.value}>
                            {item.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>

                {tab === 'genel' && (
                    <Form
                        {...ProductController.update.form({
                            product: product.id,
                        })}
                        options={{ preserveScroll: true }}
                        className="max-w-2xl space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Ürün adı</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={product.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Açıklama
                                    </Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        rows={6}
                                        defaultValue={product.description ?? ''}
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Marka</Label>
                                    {/* Radix Select bos degeri tasiyamadigi icin deger gizli alanla gider. */}
                                    <input
                                        type="hidden"
                                        name="brand_id"
                                        value={brandId ?? ''}
                                    />
                                    <Select
                                        value={
                                            brandId === null
                                                ? NONE
                                                : String(brandId)
                                        }
                                        onValueChange={(value) =>
                                            setBrandId(
                                                value === NONE
                                                    ? null
                                                    : Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger aria-label="Marka">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                Markasız
                                            </SelectItem>
                                            {brands.map((brand) => (
                                                <SelectItem
                                                    key={brand.id}
                                                    value={String(brand.id)}
                                                >
                                                    {brand.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.brand_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Kategori</Label>
                                    <input
                                        type="hidden"
                                        name="category_id"
                                        value={categoryId ?? ''}
                                    />
                                    <Select
                                        value={
                                            categoryId === null
                                                ? NONE
                                                : String(categoryId)
                                        }
                                        onValueChange={(value) =>
                                            setCategoryId(
                                                value === NONE
                                                    ? null
                                                    : Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger aria-label="Kategori">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                Kategorisiz
                                            </SelectItem>
                                            {categories.map((category) => (
                                                <SelectItem
                                                    key={category.id}
                                                    value={String(category.id)}
                                                >
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.category_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Durum</Label>
                                    <Select
                                        name="status"
                                        defaultValue={product.status}
                                    >
                                        <SelectTrigger aria-label="Durum">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                    <InputError message={errors.status} />
                                </div>

                                <PermissionButton
                                    check={canManage}
                                    type="submit"
                                    disabled={processing}
                                >
                                    Kaydet
                                </PermissionButton>
                            </>
                        )}
                    </Form>
                )}

                {tab === 'varyantlar' && (
                    <div className="overflow-hidden rounded-lg border border-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>SKU</TableHead>
                                    <TableHead>Barkod</TableHead>
                                    <TableHead>
                                        {warehouse === null
                                            ? 'Stok'
                                            : `Stok (${warehouse.name})`}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Satılabilir
                                    </TableHead>
                                    <TableHead>Liste fiyatı</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {variants.map((variant) => (
                                    <TableRow key={variant.id}>
                                        <TableCell className="font-mono font-medium tabular-nums">
                                            {variant.sku}
                                        </TableCell>
                                        <TableCell className="font-mono text-muted-foreground tabular-nums">
                                            {variant.barcode ?? '—'}
                                        </TableCell>
                                        <TableCell className="w-36">
                                            <InlineNumberCell
                                                value={variant.onHand}
                                                display={String(variant.onHand)}
                                                check={canStock}
                                                label={`${variant.sku} stok`}
                                                onCommit={(value) =>
                                                    patchVariant(
                                                        stockRoute.url({
                                                            variant: variant.id,
                                                        }),
                                                        { on_hand: value },
                                                        (current) => ({
                                                            ...current,
                                                            onHand: value,
                                                        }),
                                                        variant.id,
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-mono tabular-nums">
                                            {variant.available}
                                        </TableCell>
                                        <TableCell className="w-40">
                                            <InlineNumberCell
                                                value={variant.price}
                                                display={priceDisplay(variant)}
                                                check={canManage}
                                                step="0.01"
                                                label={`${variant.sku} fiyat`}
                                                onCommit={(value) =>
                                                    patchVariant(
                                                        priceRoute.url({
                                                            variant: variant.id,
                                                        }),
                                                        { list_price: value },
                                                        (current) => ({
                                                            ...current,
                                                            price: value,
                                                            // Bicimlendirme sunucuda; iyimser pencerede ham deger gorunur.
                                                            priceFormatted:
                                                                null,
                                                        }),
                                                        variant.id,
                                                    )
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {/* Gorseller sekmesi acilana kadar veri cekilmez. */}
                {tab === 'gorseller' && (
                    <WhenVisible
                        data="images"
                        fallback={
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                {[0, 1, 2, 3].map((key) => (
                                    <Skeleton
                                        key={key}
                                        className="aspect-square w-full"
                                    />
                                ))}
                            </div>
                        }
                    >
                        {images === undefined || images.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-border p-8 text-center font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                                Bu ürüne ait görsel yok.
                            </p>
                        ) : (
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                {images.map((image) => (
                                    <img
                                        key={image.id}
                                        src={image.url}
                                        alt={`${product.name} görseli`}
                                        loading="lazy"
                                        className="aspect-square w-full rounded-lg border border-border object-cover"
                                    />
                                ))}
                            </div>
                        )}
                    </WhenVisible>
                )}
            </div>
        </>
    );
}

ProductShow.layout = {
    breadcrumbs: [
        {
            title: 'Ürünler',
            href: index(),
        },
    ],
};
