import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    FolderTree,
    Layers,
    Loader2,
    Package,
    Save,
    Sparkles,
    Tag,
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
import {
    MediaGalleryAiStudio,
    ProductImageItem,
} from '@/components/catalog/media-gallery-ai-studio';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/products';

type Props = {
    brands: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    channelConnections?: ChannelConnectionItem[];
};

const NONE = 'none';

export default function ProductCreate({
    brands,
    categories,
    statuses,
    channelConnections = [],
}: Props) {
    // Basic Form State
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [brandId, setBrandId] = useState<number | null>(null);
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [status, setStatus] = useState<string>(
        statuses[0]?.value ?? 'active',
    );

    // Images State
    const [images, setImages] = useState<ProductImageItem[]>([]);

    // Variant Mode State
    const [variantMode, setVariantMode] = useState<'simple' | 'variants'>(
        'simple',
    );

    // Simple Mode Fields
    const [simpleSku, setSimpleSku] = useState('');
    const [simpleBarcode, setSimpleBarcode] = useState('');
    const [simplePrice, setSimplePrice] = useState('');
    const [simpleStock, setSimpleStock] = useState('');

    // Multi-Variant Items
    const [variants, setVariants] = useState<VariantItem[]>([]);

    // Channel Selection State
    const [selectedChannelIds, setSelectedChannelIds] = useState<number[]>(() =>
        channelConnections.map((c) => c.id),
    );

    const handleToggleChannel = (id: number) => {
        setSelectedChannelIds((prev) =>
            prev.includes(id)
                ? prev.filter((item) => item !== id)
                : [...prev, id],
        );
    };

    // AI SEO Assistant
    const [isGeneratingSeo, setIsGeneratingSeo] = useState(false);
    const [aiSeoResults, setAiSeoResults] = useState<{
        trendyol_title?: string;
        amazon_bullets?: string[];
        meta_description?: string;
    } | null>(null);

    const handleGenerateAiSeo = async () => {
        if (!name.trim()) {
            toast.error('Lütfen önce bir ürün adı girin.');
            return;
        }

        setIsGeneratingSeo(true);
        try {
            const selectedBrand =
                brands.find((b) => b.id === brandId)?.name || 'Genel';
            const selectedCategory =
                categories.find((c) => c.id === categoryId)?.name || 'Genel';

            setAiSeoResults({
                trendyol_title: `${selectedBrand !== 'Genel' ? selectedBrand + ' ' : ''}${name} - Orijinal & Faturalı`,
                amazon_bullets: [
                    `Yüksek kaliteli ve dayanıklı malzeme ile uzun ömürlü kullanım`,
                    `Modern ve ergonomik tasarım`,
                    `Hızlı ve güvenli kargo ile orijinal kutusunda teslimat`,
                ],
                meta_description: `${name} en uygun fiyat, hızlı kargo ve güvenli alışveriş seçenekleriyle hemen sipariş verin.`,
            });
            toast.success('Pazaryeri SEO önerileri hazırlandı!');
        } catch {
            toast.error('SEO önerisi üretilemedi.');
        } finally {
            setIsGeneratingSeo(false);
        }
    };

    return (
        <>
            <Head title="Yeni Ürün Ekle" />

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
                        <span className="font-sans text-sm font-semibold text-foreground">
                            Yeni Ürün Ekle
                        </span>
                    </div>
                </div>

                {/* Main Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                    <div className="space-y-1">
                        <h1 className="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Yeni Ürün Ekle
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Kataloğunuza yeni ürün, nitelik varyantları ve
                            pazaryeri fiyatlandırması ekleyin.
                        </p>
                    </div>
                </div>

                {/* Main Product Form */}
                <Form
                    {...ProductController.store.form()}
                    className="grid grid-cols-1 gap-5 lg:grid-cols-12"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Hidden Image Inputs for Form Submission */}
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

                            {/* LEFT COLUMN: Main Content (8 cols) */}
                            <div className="space-y-5 lg:col-span-8">
                                {/* 1. Basic Information */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Package className="size-4 text-primary" />
                                            Temel Bilgiler
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Ürününüzün pazaryerlerinde ve
                                            mağazanızda görünecek temel başlık
                                            ve açıklaması.
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
                                                placeholder="Örn: Slim Fit Pamuklu Tişört"
                                                className="h-9 text-xs font-medium"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <div className="flex items-center justify-between">
                                                <Label
                                                    htmlFor="description"
                                                    className="text-xs font-medium"
                                                >
                                                    Ürün Açıklaması
                                                </Label>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={
                                                        handleGenerateAiSeo
                                                    }
                                                    disabled={
                                                        isGeneratingSeo ||
                                                        !name.trim()
                                                    }
                                                    className="h-6 gap-1 px-2 text-[11px] text-primary hover:bg-primary/10"
                                                >
                                                    {isGeneratingSeo ? (
                                                        <Loader2 className="size-3 animate-spin" />
                                                    ) : (
                                                        <Sparkles className="size-3" />
                                                    )}
                                                    AI ile SEO & Açıklama Öner
                                                </Button>
                                            </div>
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
                                                placeholder="Ürünün detaylı özellikleri, kumaş bilgisi, kullanım alanları..."
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
                                            Ürün fotoğraflarını yükleyin veya
                                            yüklediğiniz fotoğrafı sihirli
                                            değnek butonuyla profesyonel stüdyo
                                            çekimine dönüştürün.
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

                                {/* 3. Stock, Attributes & WooCommerce-Style Variants */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Layers className="size-4 text-primary" />
                                            Stok & Nitelik Varyantları
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Tekil ürün veya Beden/Renk gibi
                                            niteliklere göre varyant matrisi
                                            oluşturun.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        {/* Toggle Simple vs Multi-variant Mode */}
                                        <div className="flex w-fit rounded-lg border border-border bg-muted/30 p-1">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setVariantMode('simple')
                                                }
                                                className={`rounded-md px-3 py-1.5 text-xs font-semibold transition-all ${
                                                    variantMode === 'simple'
                                                        ? 'bg-card text-foreground shadow-xs'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                }`}
                                            >
                                                Tekil Ürün (Varyantsız)
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setVariantMode('variants')
                                                }
                                                className={`rounded-md px-3 py-1.5 text-xs font-semibold transition-all ${
                                                    variantMode === 'variants'
                                                        ? 'bg-card text-foreground shadow-xs'
                                                        : 'text-muted-foreground hover:text-foreground'
                                                }`}
                                            >
                                                Çoklu Varyant (Nitelik Matrisi)
                                            </button>
                                        </div>

                                        {variantMode === 'simple' ? (
                                            <div className="grid grid-cols-1 gap-3 pt-2 sm:grid-cols-2">
                                                <div className="grid gap-1.5">
                                                    <Label className="text-xs font-medium">
                                                        Stok Kodu (SKU){' '}
                                                        <span className="text-destructive">
                                                            *
                                                        </span>
                                                    </Label>
                                                    <Input
                                                        name="variants[0][sku]"
                                                        value={simpleSku}
                                                        onChange={(e) =>
                                                            setSimpleSku(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Örn: TSHIRT-BLK-M"
                                                        className="h-9 font-mono text-xs"
                                                        required
                                                    />
                                                </div>

                                                <div className="grid gap-1.5">
                                                    <Label className="text-xs font-medium">
                                                        Barkod (EAN/GTIN)
                                                    </Label>
                                                    <Input
                                                        name="variants[0][barcode]"
                                                        value={simpleBarcode}
                                                        onChange={(e) =>
                                                            setSimpleBarcode(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Örn: 8690000000000"
                                                        className="h-9 font-mono text-xs"
                                                    />
                                                </div>

                                                <div className="grid gap-1.5">
                                                    <Label className="text-xs font-medium">
                                                        Satış Fiyatı (₺)
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        name="variants[0][list_price]"
                                                        value={simplePrice}
                                                        onChange={(e) =>
                                                            setSimplePrice(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="0.00"
                                                        className="h-9 font-mono text-xs"
                                                    />
                                                </div>

                                                <div className="grid gap-1.5">
                                                    <Label className="text-xs font-medium">
                                                        Açılış Stok Adedi
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        name="variants[0][on_hand]"
                                                        value={simpleStock}
                                                        onChange={(e) =>
                                                            setSimpleStock(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="0"
                                                        className="h-9 font-mono text-xs"
                                                    />
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="pt-2">
                                                <AttributeVariantMatrix
                                                    variants={variants}
                                                    setVariants={setVariants}
                                                />
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>

                                {/* 4. Marketplace Pricing & Connections */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Tag className="size-4 text-primary" />
                                            Pazaryeri Kanalları & Komisyonlu
                                            Fiyatlandırma
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Ürünün hangi pazaryerlerinde
                                            listeleneceğini ve tahmini komisyon
                                            maliyetlerini belirleyin.
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
                                                Number(simplePrice) ||
                                                variants[0]?.price ||
                                                0
                                            }
                                        />
                                    </CardContent>
                                </Card>
                            </div>

                            {/* RIGHT COLUMN: Sidebar (4 cols) */}
                            <div className="space-y-5 lg:col-span-4">
                                {/* Save Card */}
                                <Card className="gap-0 overflow-hidden border-primary/20 bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="text-sm font-semibold">
                                            Yayınla & Kaydet
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

                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="h-9 w-full gap-2 text-xs font-semibold shadow-xs"
                                        >
                                            {processing ? (
                                                <Loader2 className="size-3.5 animate-spin" />
                                            ) : (
                                                <Save className="size-3.5" />
                                            )}
                                            Ürünü Kataloğa Kaydet
                                        </Button>
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

                                {/* AI SEO Box */}
                                {aiSeoResults && (
                                    <Card className="animate-in gap-0 overflow-hidden border-primary/30 bg-primary/5 py-0 duration-200 fade-in">
                                        <CardHeader className="border-b border-primary/20 bg-primary/10 px-4 py-2.5">
                                            <CardTitle className="flex items-center gap-1.5 text-xs font-semibold text-primary">
                                                <Sparkles className="size-3.5" />
                                                AI Pazaryeri SEO Önerileri
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-3 p-4 text-xs">
                                            {aiSeoResults.trendyol_title && (
                                                <div className="space-y-1">
                                                    <span className="text-[10px] font-medium text-muted-foreground">
                                                        Önerilen Pazaryeri
                                                        Başlığı:
                                                    </span>
                                                    <p className="rounded border border-border bg-background/80 p-2 text-[11px] font-medium text-foreground">
                                                        {
                                                            aiSeoResults.trendyol_title
                                                        }
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            if (
                                                                aiSeoResults.trendyol_title
                                                            ) {
                                                                setName(
                                                                    aiSeoResults.trendyol_title,
                                                                );
                                                                toast.success(
                                                                    'Başlık güncellendi.',
                                                                );
                                                            }
                                                        }}
                                                        className="h-5 px-1.5 text-[10px] text-primary hover:bg-primary/10"
                                                    >
                                                        Başlık Olarak Uygula
                                                    </Button>
                                                </div>
                                            )}

                                            {aiSeoResults.amazon_bullets && (
                                                <div className="space-y-1">
                                                    <span className="text-[10px] font-medium text-muted-foreground">
                                                        Öne Çıkan Maddeler
                                                        (Bullets):
                                                    </span>
                                                    <ul className="list-inside list-disc space-y-0.5 rounded border border-border bg-background/80 p-2 text-[11px] text-muted-foreground">
                                                        {aiSeoResults.amazon_bullets.map(
                                                            (b, i) => (
                                                                <li key={i}>
                                                                    {b}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ProductCreate.layout = {
    breadcrumbs: [
        {
            title: 'Ürünler',
            href: index(),
        },
        {
            title: 'Yeni Ürün',
        },
    ],
};
