import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    FolderTree,
    Layers,
    Loader2,
    Package,
    Save,
    Sparkles,
    Tag,
    Trash2,
    TriangleAlert,
    Wand2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import ProductController from '@/actions/App/Http/Controllers/Catalog/ProductController';
import {
    AttributeVariantMatrix,
    VariantItem,
} from '@/components/catalog/attribute-variant-matrix';
import {
    ChannelConnectionItem,
    ChannelPricingManager,
} from '@/components/catalog/channel-pricing-manager';
import { InlineNumberCell } from '@/components/catalog/inline-number-cell';
import {
    MediaGalleryAiStudio,
    ProductImageItem,
} from '@/components/catalog/media-gallery-ai-studio';
import { PermissionButton } from '@/components/catalog/permission-button';
import { ProductStatusBadge } from '@/components/catalog/product-status-badge';
import { toastError } from '@/components/catalog/toast-error';
import {
    MarketplaceAvatarStack,
    MarketplaceChannel,
} from '@/components/marketplace-avatar';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { SearchableSelect } from '@/components/ui/searchable-select';
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
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { destroy, index } from '@/routes/products';
import { price as priceRoute, stock as stockRoute } from '@/routes/variants';

type VariantRow = {
    id: number;
    sku: string;
    barcode: string | null;
    attributes: Record<string, string> | null;
    imageUrl?: string | null;
    onHand: number;
    available: number;
    price: number | null;
    priceFormatted: string | null;
    cost?: number | null;
    currency?: string;
};

type Props = {
    product: {
        id: number;
        name: string;
        description: string | null;
        status: string;
        statusLabel: string;
        brandId: number | null;
        categoryId: number | null;
        listingCount: number;
        channels?: MarketplaceChannel[];
    };
    variants: VariantRow[];
    images?: ProductImageItem[];
    channelConnections?: ChannelConnectionItem[];
    activeChannelIds?: number[];
    brands: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    warehouse: { id: number; name: string } | null;
};

const NONE = 'none';

function priceDisplay(variant: VariantRow): string {
    return (
        variant.priceFormatted ??
        (variant.price !== null && variant.price !== undefined
            ? variant.price.toFixed(2)
            : '—')
    );
}

export default function ProductShow({
    product,
    variants: initialVariants,
    images: initialImages = [],
    channelConnections = [],
    activeChannelIds = [],
    brands,
    categories,
    statuses,
    warehouse,
}: Props) {
    const can = usePermission();
    const canManage = can('catalog.manage');
    const canStock = can('stock.manage');

    const [name, setName] = useState(product.name);
    const [description, setDescription] = useState(product.description ?? '');
    const [status, setStatus] = useState(product.status);
    const [brandId, setBrandId] = useState<number | null>(product.brandId);
    const [categoryId, setCategoryId] = useState<number | null>(
        product.categoryId,
    );
    const [images, setImages] = useState<ProductImageItem[]>(initialImages);
    const [selectedChannelIds, setSelectedChannelIds] =
        useState<number[]>(activeChannelIds);

    const handleToggleChannel = (id: number) => {
        setSelectedChannelIds((prev) =>
            prev.includes(id)
                ? prev.filter((item) => item !== id)
                : [...prev, id],
        );
    };

    // Optimistic variant patch
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
            onError: toastError,
        });
    };

    return (
        <>
            <Head title={product.name} />

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
                                <span>Ürünler</span>
                            </Link>
                        </Button>
                        <span className="text-muted-foreground/40">/</span>
                        <span className="font-mono text-sm font-semibold text-foreground">
                            {product.name}
                        </span>
                    </div>

                    <div className="flex items-center gap-2.5">
                        {/* Delete Dialog */}
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs text-destructive hover:text-destructive"
                                >
                                    <Trash2 className="size-3.5" />
                                    Ürünü Sil
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
                                                        <span className="tabular-nums">
                                                            {
                                                                product.listingCount
                                                            }
                                                        </span>{' '}
                                                        kanal listelemesi
                                                    </strong>{' '}
                                                    var; ürün şu anda
                                                    pazaryerinde yayında
                                                    olabilir. Silmek listelemeyi
                                                    pazaryerinden kaldırmaz —
                                                    önce kanaldan yayından
                                                    kaldırın.
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
                </div>

                {/* Main Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                    <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                        <div className="space-y-1.5">
                            <div className="flex flex-wrap items-center gap-2.5">
                                <h1 className="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                                    {product.name}
                                </h1>
                                <ProductStatusBadge
                                    status={product.status}
                                    label={product.statusLabel}
                                />
                            </div>

                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
                                <span>
                                    Toplam Varyant:{' '}
                                    <span className="font-mono font-medium text-foreground">
                                        {initialVariants.length} adet
                                    </span>
                                </span>
                                {product.channels &&
                                product.channels.length > 0 ? (
                                    <div className="flex items-center gap-2">
                                        <span>Kanal Listelemesi:</span>
                                        <MarketplaceAvatarStack
                                            channels={product.channels}
                                            size="md"
                                        />
                                    </div>
                                ) : product.listingCount > 0 ? (
                                    <span>
                                        Kanal Listelemesi:{' '}
                                        <span className="font-mono font-medium text-foreground">
                                            {product.listingCount} kanal
                                        </span>
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Edit Form */}
                <Form
                    {...ProductController.update.form({
                        product: product.id,
                    })}
                    options={{ preserveScroll: true }}
                    className="grid grid-cols-1 gap-5 lg:grid-cols-12"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Hidden Image Inputs for Updating Images */}
                            {images.map((img, idx) => (
                                <span key={img.id}>
                                    <input
                                        type="hidden"
                                        name={`images[${idx}][url]`}
                                        value={img.url}
                                    />
                                    <input
                                        type="hidden"
                                        name={`images[${idx}][position]`}
                                        value={idx}
                                    />
                                </span>
                            ))}

                            {/* LEFT COLUMN: Main Information, Images, Variants, Channels (8 cols) */}
                            <div className="space-y-5 lg:col-span-8">
                                {/* 1. Basic Details */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Package className="size-4 text-primary" />
                                            Temel Bilgiler
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Ürünün pazaryerlerinde ve mağazada
                                            görünen başlığı ve açıklaması.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="name"
                                                className="text-xs font-medium"
                                            >
                                                Ürün Adı{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                value={name}
                                                onChange={(e) =>
                                                    setName(e.target.value)
                                                }
                                                className="h-9 text-xs font-medium"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="description"
                                                className="text-xs font-medium"
                                            >
                                                Ürün Açıklaması
                                            </Label>
                                            <Textarea
                                                id="description"
                                                name="description"
                                                rows={4}
                                                value={description}
                                                onChange={(e) =>
                                                    setDescription(
                                                        e.target.value,
                                                    )
                                                }
                                                className="text-xs leading-relaxed"
                                            />
                                            <InputError
                                                message={errors.description}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* 2. Media Gallery & AI Studio Refactoring */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Wand2 className="size-4 text-primary" />
                                            Görseller & AI Stüdyo Refactor
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Ürün görsellerini yönetin veya
                                            mevcut fotoğrafları sihirli değnekle
                                            stüdyo kalitesine dönüştürün.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="p-4">
                                        <MediaGalleryAiStudio
                                            images={images}
                                            setImages={setImages}
                                            productName={name}
                                        />
                                    </CardContent>
                                </Card>

                                {/* 3. Variant List & Quick Inline Stock/Price Editor */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Layers className="size-4 text-primary" />
                                            Varyantlar & Fiyat/Stok Yönetimi
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Varyantların stok ve fiyatlarını tek
                                            tıkla canlı olarak
                                            düzenleyebilirsiniz.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="overflow-hidden rounded-lg border border-border bg-card">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow className="border-b border-border text-xs hover:bg-transparent">
                                                        <TableHead>
                                                            SKU / Nitelik
                                                        </TableHead>
                                                        <TableHead>
                                                            Barkod
                                                        </TableHead>
                                                        <TableHead>
                                                            {warehouse === null
                                                                ? 'Stok'
                                                                : `Stok (${warehouse.name})`}
                                                        </TableHead>
                                                        <TableHead className="text-right">
                                                            Satılabilir
                                                        </TableHead>
                                                        <TableHead>
                                                            Liste Fiyatı
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {initialVariants.map(
                                                        (variant) => (
                                                            <TableRow
                                                                key={variant.id}
                                                                className="text-xs"
                                                            >
                                                                <TableCell className="py-2.5 font-medium">
                                                                    <div className="flex flex-col gap-0.5">
                                                                        <span className="font-mono text-xs font-semibold tabular-nums">
                                                                            {
                                                                                variant.sku
                                                                            }
                                                                        </span>
                                                                        {variant.attributes && (
                                                                            <div className="mt-0.5 flex flex-wrap gap-1">
                                                                                {Object.entries(
                                                                                    variant.attributes,
                                                                                ).map(
                                                                                    ([
                                                                                        k,
                                                                                        v,
                                                                                    ]) => (
                                                                                        <Badge
                                                                                            key={
                                                                                                k
                                                                                            }
                                                                                            variant="secondary"
                                                                                            className="h-4 px-1 text-[10px]"
                                                                                        >
                                                                                            {
                                                                                                k
                                                                                            }
                                                                                            :{' '}
                                                                                            {
                                                                                                v
                                                                                            }
                                                                                        </Badge>
                                                                                    ),
                                                                                )}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </TableCell>
                                                                <TableCell className="py-2.5 font-mono text-xs text-muted-foreground tabular-nums">
                                                                    {variant.barcode ??
                                                                        '—'}
                                                                </TableCell>
                                                                <TableCell className="w-36 py-2.5">
                                                                    <InlineNumberCell
                                                                        value={
                                                                            variant.onHand
                                                                        }
                                                                        display={String(
                                                                            variant.onHand,
                                                                        )}
                                                                        check={
                                                                            canStock
                                                                        }
                                                                        label={`${variant.sku} stok`}
                                                                        onCommit={(
                                                                            value,
                                                                        ) =>
                                                                            patchVariant(
                                                                                stockRoute.url(
                                                                                    {
                                                                                        variant:
                                                                                            variant.id,
                                                                                    },
                                                                                ),
                                                                                {
                                                                                    on_hand:
                                                                                        value,
                                                                                },
                                                                                (
                                                                                    current,
                                                                                ) => ({
                                                                                    ...current,
                                                                                    onHand: value,
                                                                                }),
                                                                                variant.id,
                                                                            )
                                                                        }
                                                                    />
                                                                </TableCell>
                                                                <TableCell className="py-2.5 text-right text-xs tabular-nums">
                                                                    {
                                                                        variant.available
                                                                    }
                                                                </TableCell>
                                                                <TableCell className="w-40 py-2.5">
                                                                    <InlineNumberCell
                                                                        value={
                                                                            variant.price
                                                                        }
                                                                        display={priceDisplay(
                                                                            variant,
                                                                        )}
                                                                        check={
                                                                            canManage
                                                                        }
                                                                        step="0.01"
                                                                        label={`${variant.sku} fiyat`}
                                                                        onCommit={(
                                                                            value,
                                                                        ) =>
                                                                            patchVariant(
                                                                                priceRoute.url(
                                                                                    {
                                                                                        variant:
                                                                                            variant.id,
                                                                                    },
                                                                                ),
                                                                                {
                                                                                    list_price:
                                                                                        value,
                                                                                },
                                                                                (
                                                                                    current,
                                                                                ) => ({
                                                                                    ...current,
                                                                                    price: value,
                                                                                    priceFormatted:
                                                                                        null,
                                                                                }),
                                                                                variant.id,
                                                                            )
                                                                        }
                                                                    />
                                                                </TableCell>
                                                            </TableRow>
                                                        ),
                                                    )}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* 4. Sales Channels & Commission Price Calculator */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Tag className="size-4 text-primary" />
                                            Satış Kanalları & Komisyonlu Fiyat
                                            Hesaplayıcı
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Ürünün hangi pazaryerlerinde açık
                                            olduğunu yönetin ve komisyon
                                            oranlarına göre önerilen satış
                                            fiyatını hesaplayın.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="p-4">
                                        <ChannelPricingManager
                                            connections={channelConnections}
                                            selectedChannelIds={
                                                selectedChannelIds
                                            }
                                            onToggleChannel={
                                                handleToggleChannel
                                            }
                                            basePrice={
                                                initialVariants[0]?.price || 0
                                            }
                                        />
                                    </CardContent>
                                </Card>
                            </div>

                            {/* RIGHT COLUMN: Status, Brands, Categories, Save (4 cols) */}
                            <div className="space-y-5 lg:col-span-4">
                                {/* Save Card */}
                                <Card className="gap-0 overflow-hidden border-primary/20 bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="text-sm font-semibold">
                                            Değişiklikleri Kaydet
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3.5 p-4">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="status"
                                                className="text-xs font-medium"
                                            >
                                                Ürün Durumu
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="status"
                                                value={status}
                                            />
                                            <Select
                                                value={status}
                                                onValueChange={setStatus}
                                            >
                                                <SelectTrigger
                                                    id="status"
                                                    className="h-9 text-xs"
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {statuses.map((s) => (
                                                        <SelectItem
                                                            key={s.value}
                                                            value={s.value}
                                                            className="text-xs"
                                                        >
                                                            {s.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                message={errors.status}
                                            />
                                        </div>

                                        <PermissionButton
                                            check={canManage}
                                            type="submit"
                                            disabled={processing}
                                            className="h-9 w-full gap-2 text-xs font-semibold shadow-xs"
                                        >
                                            {processing ? (
                                                <Loader2 className="size-3.5 animate-spin" />
                                            ) : (
                                                <Save className="size-3.5" />
                                            )}
                                            Değişiklikleri Kaydet
                                        </PermissionButton>
                                    </CardContent>
                                </Card>

                                {/* Brand & Category Card */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <FolderTree className="size-4 text-primary" />
                                            Kategori ve Marka
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3.5 p-4">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="brand_id"
                                                className="text-xs font-medium"
                                            >
                                                Marka
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="brand_id"
                                                value={brandId ?? ''}
                                            />
                                            <SearchableSelect
                                                id="brand_id"
                                                value={
                                                    brandId === null
                                                        ? NONE
                                                        : String(brandId)
                                                }
                                                onValueChange={(val) =>
                                                    setBrandId(
                                                        val === NONE
                                                            ? null
                                                            : Number(val),
                                                    )
                                                }
                                                options={[
                                                    {
                                                        value: NONE,
                                                        label: 'Markasız',
                                                    },
                                                    ...brands.map((b) => ({
                                                        value: String(b.id),
                                                        label: b.name,
                                                    })),
                                                ]}
                                                placeholder="Marka seçin"
                                                searchPlaceholder="Marka ara..."
                                                emptyText="Marka bulunamadı."
                                            />
                                            <InputError
                                                message={errors.brand_id}
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="category_id"
                                                className="text-xs font-medium"
                                            >
                                                Kategori
                                            </Label>
                                            <input
                                                type="hidden"
                                                name="category_id"
                                                value={categoryId ?? ''}
                                            />
                                            <SearchableSelect
                                                id="category_id"
                                                value={
                                                    categoryId === null
                                                        ? NONE
                                                        : String(categoryId)
                                                }
                                                onValueChange={(val) =>
                                                    setCategoryId(
                                                        val === NONE
                                                            ? null
                                                            : Number(val),
                                                    )
                                                }
                                                options={[
                                                    {
                                                        value: NONE,
                                                        label: 'Kategorisiz',
                                                    },
                                                    ...categories.map((c) => ({
                                                        value: String(c.id),
                                                        label: c.name,
                                                    })),
                                                ]}
                                                placeholder="Kategori seçin"
                                                searchPlaceholder="Kategori ara..."
                                                emptyText="Kategori bulunamadı."
                                            />
                                            <InputError
                                                message={errors.category_id}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        </>
                    )}
                </Form>
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
