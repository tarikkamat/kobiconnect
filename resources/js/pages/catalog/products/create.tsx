import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    FolderTree,
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
import type {
    DefinedAttributeItem,
    VariantItem,
} from '@/components/catalog/attribute-variant-matrix';
import { AttributeVariantMatrix } from '@/components/catalog/attribute-variant-matrix';
import type { ChannelConnectionItem } from '@/components/catalog/channel-pricing-manager';
import { ChannelPricingManager } from '@/components/catalog/channel-pricing-manager';
import type { ProductImageItem } from '@/components/catalog/media-gallery-ai-studio';
import { MediaGalleryAiStudio } from '@/components/catalog/media-gallery-ai-studio';
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
    units?: { id: number; name: string; short_name: string }[];
    tags?: { id: number; name: string; slug: string }[];
    productGroups?: { id: number; name: string }[];
    channelConnections?: ChannelConnectionItem[];
    attributes?: DefinedAttributeItem[];
};

const NONE = 'none';

export default function ProductCreate({
    brands,
    categories,
    statuses,
    units = [],
    tags = [],
    productGroups = [],
    channelConnections = [],
    attributes = [],
}: Props) {
    // Basic Form State
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [brandId, setBrandId] = useState<number | null>(null);
    const [categoryId, setCategoryId] = useState<number | null>(null);
    const [unitId, setUnitId] = useState<number | null>(null);
    const [selectedTagIds, setSelectedTagIds] = useState<number[]>([]);
    const [selectedGroupIds, setSelectedGroupIds] = useState<number[]>([]);
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

                            {/* Hidden Unit, Tags, Groups Inputs */}
                            <input
                                type="hidden"
                                name="unit_id"
                                value={unitId ?? ''}
                            />
                            {selectedTagIds.map((id, i) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name={`tag_ids[${i}]`}
                                    value={id}
                                />
                            ))}
                            {selectedGroupIds.map((id, i) => (
                                <input
                                    key={id}
                                    type="hidden"
                                    name={`group_ids[${i}]`}
                                    value={id}
                                />
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

                                <AttributeVariantMatrix
                                    mode={variantMode}
                                    setMode={setVariantMode}
                                    simpleSku={simpleSku}
                                    setSimpleSku={setSimpleSku}
                                    simpleBarcode={simpleBarcode}
                                    setSimpleBarcode={setSimpleBarcode}
                                    simplePrice={simplePrice}
                                    setSimplePrice={setSimplePrice}
                                    simpleStock={simpleStock}
                                    setSimpleStock={setSimpleStock}
                                    variants={variants}
                                    setVariants={setVariants}
                                    galleryImages={images}
                                    productName={name}
                                    definedAttributes={attributes}
                                    errors={errors}
                                />

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

                                        {/* Ürün Birimi */}
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="unit_id"
                                                className="text-xs font-medium"
                                            >
                                                Ürün Birimi
                                            </Label>
                                            <Select
                                                value={
                                                    unitId === null
                                                        ? NONE
                                                        : String(unitId)
                                                }
                                                onValueChange={(val) =>
                                                    setUnitId(
                                                        val === NONE
                                                            ? null
                                                            : Number(val),
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="unit_id"
                                                    className="h-9 text-xs"
                                                >
                                                    <SelectValue placeholder="Birim seçin (Adet, kg...)" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        value={NONE}
                                                        className="text-xs"
                                                    >
                                                        Tanımsız
                                                    </SelectItem>
                                                    {units.map((u) => (
                                                        <SelectItem
                                                            key={u.id}
                                                            value={String(u.id)}
                                                            className="text-xs"
                                                        >
                                                            {u.name} (
                                                            {u.short_name})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {/* Etiketler */}
                                        {tags.length > 0 && (
                                            <div className="grid gap-1.5">
                                                <Label className="text-xs font-medium">
                                                    Etiketler
                                                </Label>
                                                <div className="flex max-h-28 flex-wrap gap-1.5 overflow-y-auto rounded-md border border-border bg-muted/20 p-1.5">
                                                    {tags.map((t) => {
                                                        const isSelected =
                                                            selectedTagIds.includes(
                                                                t.id,
                                                            );

                                                        return (
                                                            <button
                                                                key={t.id}
                                                                type="button"
                                                                onClick={() => {
                                                                    setSelectedTagIds(
                                                                        (
                                                                            prev,
                                                                        ) =>
                                                                            isSelected
                                                                                ? prev.filter(
                                                                                      (
                                                                                          id,
                                                                                      ) =>
                                                                                          id !==
                                                                                          t.id,
                                                                                  )
                                                                                : [
                                                                                      ...prev,
                                                                                      t.id,
                                                                                  ],
                                                                    );
                                                                }}
                                                                className={`cursor-pointer rounded-full px-2 py-0.5 text-[11px] font-medium transition-all ${
                                                                    isSelected
                                                                        ? 'bg-primary text-primary-foreground shadow-2xs'
                                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                                                }`}
                                                            >
                                                                {t.name}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}

                                        {/* Ürün Grupları */}
                                        {productGroups.length > 0 && (
                                            <div className="grid gap-1.5">
                                                <Label className="text-xs font-medium">
                                                    Ürün Grupları
                                                </Label>
                                                <div className="flex max-h-28 flex-wrap gap-1.5 overflow-y-auto rounded-md border border-border bg-muted/20 p-1.5">
                                                    {productGroups.map((g) => {
                                                        const isSelected =
                                                            selectedGroupIds.includes(
                                                                g.id,
                                                            );

                                                        return (
                                                            <button
                                                                key={g.id}
                                                                type="button"
                                                                onClick={() => {
                                                                    setSelectedGroupIds(
                                                                        (
                                                                            prev,
                                                                        ) =>
                                                                            isSelected
                                                                                ? prev.filter(
                                                                                      (
                                                                                          id,
                                                                                      ) =>
                                                                                          id !==
                                                                                          g.id,
                                                                                  )
                                                                                : [
                                                                                      ...prev,
                                                                                      g.id,
                                                                                  ],
                                                                    );
                                                                }}
                                                                className={`cursor-pointer rounded-full px-2 py-0.5 text-[11px] font-medium transition-all ${
                                                                    isSelected
                                                                        ? 'bg-primary text-primary-foreground shadow-2xs'
                                                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'
                                                                }`}
                                                            >
                                                                {g.name}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        )}
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
