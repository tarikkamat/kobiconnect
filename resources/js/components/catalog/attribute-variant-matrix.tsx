import {
    Image as ImageIcon,
    Layers,
    Package,
    Plus,
    Sparkles,
    Trash2,
    X,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type { ProductImageItem } from './media-gallery-ai-studio';

export type VariantItem = {
    id: number;
    sku: string;
    barcode: string;
    price: string;
    stock: string;
    attributes?: Record<string, string>;
    imageUrl?: string;
};

export type AttributeDefinition = {
    id: string;
    name: string;
    values: string[];
    currentInput: string;
};

type Props = {
    mode: 'simple' | 'variants';
    setMode: (mode: 'simple' | 'variants') => void;
    // Simple Mode
    simpleSku: string;
    setSimpleSku: (sku: string) => void;
    simpleBarcode: string;
    setSimpleBarcode: (barcode: string) => void;
    simplePrice: string;
    setSimplePrice: (price: string) => void;
    simpleStock: string;
    setSimpleStock: (stock: string) => void;
    // Multi-Variant Mode
    variants: VariantItem[];
    setVariants: React.Dispatch<React.SetStateAction<VariantItem[]>>;
    // Gallery images available for variant image assignment
    galleryImages: ProductImageItem[];
    productName: string;
    errors?: Record<string, string>;
};

export function AttributeVariantMatrix({
    mode,
    setMode,
    simpleSku,
    setSimpleSku,
    simpleBarcode,
    setSimpleBarcode,
    simplePrice,
    setSimplePrice,
    simpleStock,
    setSimpleStock,
    variants,
    setVariants,
    galleryImages,
    productName,
    errors = {},
}: Props) {
    // Attributes State (for WooCommerce-style generator)
    const [attributes, setAttributes] = useState<AttributeDefinition[]>([
        { id: '1', name: 'Beden', values: ['S', 'M', 'L'], currentInput: '' },
        { id: '2', name: 'Renk', values: ['Siyah', 'Beyaz'], currentInput: '' },
    ]);

    // Add Attribute Block
    const handleAddAttribute = () => {
        setAttributes((prev) => [
            ...prev,
            {
                id: String(Date.now() + Math.random()),
                name: '',
                values: [],
                currentInput: '',
            },
        ]);
    };

    // Remove Attribute Block
    const handleRemoveAttribute = (id: string) => {
        setAttributes((prev) => prev.filter((attr) => attr.id !== id));
    };

    // Add Value to Attribute
    const handleAddValue = (attrId: string, val: string) => {
        const trimmed = val.trim();

        if (!trimmed) {
            return;
        }

        setAttributes((prev) =>
            prev.map((attr) => {
                if (attr.id === attrId) {
                    if (attr.values.includes(trimmed)) {
                        return attr;
                    }

                    return {
                        ...attr,
                        values: [...attr.values, trimmed],
                        currentInput: '',
                    };
                }

                return attr;
            }),
        );
    };

    // Remove Value from Attribute
    const handleRemoveValue = (attrId: string, valToRemove: string) => {
        setAttributes((prev) =>
            prev.map((attr) =>
                attr.id === attrId
                    ? {
                          ...attr,
                          values: attr.values.filter((v) => v !== valToRemove),
                      }
                    : attr,
            ),
        );
    };

    // Cartesian Product Variant Generator (WooCommerce-Style)
    const handleGenerateVariantsFromAttributes = () => {
        const validAttrs = attributes.filter(
            (a) => a.name.trim() !== '' && a.values.length > 0,
        );

        if (validAttrs.length === 0) {
            toast.error(
                'Lütfen en az bir nitelik adı ve değeri girin (örn: Beden -> S, M).',
            );

            return;
        }

        // Compute Cartesian Product
        const cartesian = (arrays: string[][]): string[][] => {
            return arrays.reduce<string[][]>(
                (acc, curr) => acc.flatMap((c) => curr.map((n) => [...c, n])),
                [[]],
            );
        };

        const attrNames = validAttrs.map((a) => a.name.trim());
        const attrValuesArrays = validAttrs.map((a) => a.values);
        const combinations = cartesian(attrValuesArrays);

        if (combinations.length === 0) {
            toast.error('Kombinasyon üretilemedi.');

            return;
        }

        const skuPrefix = productName
            ? productName
                  .slice(0, 4)
                  .toUpperCase()
                  .replace(/[^A-Z0-9]/g, 'PRD')
            : 'SKU';

        const newVariants: VariantItem[] = combinations.map((combo, idx) => {
            const attrObj: Record<string, string> = {};
            const skuParts: string[] = [skuPrefix];

            combo.forEach((val, i) => {
                const name = attrNames[i];
                attrObj[name] = val;
                skuParts.push(val.toUpperCase().replace(/\s+/g, ''));
            });

            return {
                id: Date.now() + idx + Math.random(),
                sku: skuParts.join('-'),
                barcode: '',
                price: simplePrice || '',
                stock: simpleStock || '',
                attributes: attrObj,
                imageUrl: galleryImages[0]?.url,
            };
        });

        setVariants(newVariants);
        toast.success(
            `${newVariants.length} adet varyant başarıyla oluşturuldu!`,
        );
    };

    // Add Single Manual Variant
    const handleAddManualVariant = () => {
        setVariants((prev) => [
            ...prev,
            {
                id: Date.now() + Math.random(),
                sku: '',
                barcode: '',
                price: simplePrice || '',
                stock: simpleStock || '',
                imageUrl: galleryImages[0]?.url,
            },
        ]);
    };

    return (
        <div className="space-y-4">
            {/* Mode Switcher */}
            <div className="flex flex-col gap-2 pb-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="text-sm font-semibold text-foreground">
                        Ürün Türü ve Varyant Yapısı
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        Tekil ürün girişi veya beden/renk gibi niteliklerden
                        çoklu varyant oluşturma.
                    </p>
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={mode}
                    onValueChange={(val) =>
                        val && setMode(val as 'simple' | 'variants')
                    }
                    className="self-start sm:self-auto"
                >
                    <ToggleGroupItem
                        value="simple"
                        className="h-8 gap-1.5 px-3 text-xs"
                    >
                        <Package className="size-3.5" />
                        Basit Ürün (Tek)
                    </ToggleGroupItem>
                    <ToggleGroupItem
                        value="variants"
                        className="h-8 gap-1.5 px-3 text-xs"
                    >
                        <Layers className="size-3.5" />
                        Nitelikli Varyantlar
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            <InputError message={errors.variants} />

            {/* 1. SIMPLE PRODUCT MODE */}
            {mode === 'simple' && (
                <div className="animate-in space-y-4 duration-150 fade-in">
                    <div className="grid grid-cols-1 gap-4 rounded-xl border border-border/80 bg-secondary/15 p-4 sm:grid-cols-2 md:grid-cols-4">
                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="simple-sku"
                                className="text-xs font-medium"
                            >
                                SKU (Stok Kodu){' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="simple-sku"
                                name="variants[0][sku]"
                                required
                                value={simpleSku}
                                onChange={(e) => setSimpleSku(e.target.value)}
                                placeholder="Örn: TSH-BLK-M"
                                className="font-mono text-xs uppercase tabular-nums"
                            />
                            <InputError message={errors['variants.0.sku']} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="simple-barcode"
                                className="text-xs font-medium"
                            >
                                Barkod
                            </Label>
                            <Input
                                id="simple-barcode"
                                name="variants[0][barcode]"
                                value={simpleBarcode}
                                onChange={(e) =>
                                    setSimpleBarcode(e.target.value)
                                }
                                placeholder="8690000000000"
                                className="font-mono text-xs tabular-nums"
                            />
                            <InputError
                                message={errors['variants.0.barcode']}
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="simple-price"
                                className="text-xs font-medium"
                            >
                                Liste Fiyatı (TRY)
                            </Label>
                            <Input
                                id="simple-price"
                                type="number"
                                step="0.01"
                                min="0"
                                name="variants[0][list_price]"
                                value={simplePrice}
                                onChange={(e) => setSimplePrice(e.target.value)}
                                placeholder="0,00"
                                className="font-mono text-xs tabular-nums"
                            />
                            <InputError
                                message={errors['variants.0.list_price']}
                            />
                        </div>

                        <div className="grid gap-1.5">
                            <Label
                                htmlFor="simple-stock"
                                className="text-xs font-medium"
                            >
                                Başlangıç Stoğu
                            </Label>
                            <Input
                                id="simple-stock"
                                type="number"
                                min="0"
                                name="variants[0][on_hand]"
                                value={simpleStock}
                                onChange={(e) => setSimpleStock(e.target.value)}
                                placeholder="0"
                                className="font-mono text-xs tabular-nums"
                            />
                            <InputError
                                message={errors['variants.0.on_hand']}
                            />
                        </div>
                    </div>
                </div>
            )}

            {/* 2. MULTI-VARIANT MODE (WooCommerce Style) */}
            {mode === 'variants' && (
                <div className="animate-in space-y-6 duration-150 fade-in">
                    {/* Attribute Generator Box */}
                    <div className="space-y-4 rounded-xl border border-primary/20 bg-linear-to-b from-primary/5 via-transparent to-transparent p-4">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2">
                                <Sparkles className="size-4 text-primary" />
                                <h4 className="text-xs font-semibold text-foreground">
                                    Nitelik Tanımlama (WooCommerce Stili Varyant
                                    Üretici)
                                </h4>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleAddAttribute}
                                className="h-7 gap-1 text-xs"
                            >
                                <Plus className="size-3" />
                                Nitelik Ekle
                            </Button>
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Beden, Renk vb. nitelikler ve değerler ekleyin;
                            sistem tüm varyant kombinasyonlarını otomatik olarak
                            oluştursun.
                        </p>

                        <div className="space-y-3">
                            {attributes.map((attr) => (
                                <div
                                    key={attr.id}
                                    className="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 sm:flex-row sm:items-center"
                                >
                                    <div className="w-full shrink-0 sm:w-44">
                                        <Label className="mb-1 block text-[11px] text-muted-foreground">
                                            Nitelik Adı
                                        </Label>
                                        <Input
                                            value={attr.name}
                                            onChange={(e) => {
                                                const val = e.target.value;
                                                setAttributes((prev) =>
                                                    prev.map((a) =>
                                                        a.id === attr.id
                                                            ? {
                                                                  ...a,
                                                                  name: val,
                                                              }
                                                            : a,
                                                    ),
                                                );
                                            }}
                                            placeholder="Örn: Beden, Renk"
                                            className="h-8 text-xs font-medium"
                                        />
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <Label className="mb-1 block text-[11px] text-muted-foreground">
                                            Değerler / Seçenekler (Virgül veya
                                            Enter ile ekleyin)
                                        </Label>
                                        <div className="flex min-h-8 flex-wrap items-center gap-1.5 rounded-md border border-input bg-background p-1.5">
                                            {attr.values.map((v) => (
                                                <Badge
                                                    key={v}
                                                    variant="secondary"
                                                    className="h-5 gap-1 pr-1 pl-2 text-xs font-normal"
                                                >
                                                    {v}
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleRemoveValue(
                                                                attr.id,
                                                                v,
                                                            )
                                                        }
                                                        className="hover:text-destructive"
                                                    >
                                                        <X className="size-3" />
                                                    </button>
                                                </Badge>
                                            ))}
                                            <input
                                                type="text"
                                                value={attr.currentInput}
                                                onChange={(e) => {
                                                    const val = e.target.value;

                                                    if (val.includes(',')) {
                                                        const parts =
                                                            val.split(',');
                                                        parts.forEach((p) =>
                                                            handleAddValue(
                                                                attr.id,
                                                                p,
                                                            ),
                                                        );
                                                    } else {
                                                        setAttributes((prev) =>
                                                            prev.map((a) =>
                                                                a.id === attr.id
                                                                    ? {
                                                                          ...a,
                                                                          currentInput:
                                                                              val,
                                                                      }
                                                                    : a,
                                                            ),
                                                        );
                                                    }
                                                }}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        handleAddValue(
                                                            attr.id,
                                                            attr.currentInput,
                                                        );
                                                    }
                                                }}
                                                placeholder={
                                                    attr.values.length === 0
                                                        ? 'Örn: S, M, L, XL yazıp Enter basın'
                                                        : '+ Ekle'
                                                }
                                                className="h-5 min-w-28 flex-1 border-0 bg-transparent px-1 text-xs outline-hidden"
                                            />
                                        </div>
                                    </div>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            handleRemoveAttribute(attr.id)
                                        }
                                        className="size-8 shrink-0 self-end text-muted-foreground hover:text-destructive sm:self-center"
                                        title="Niteliği Sil"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>
                            ))}
                        </div>

                        <div className="flex justify-end pt-1">
                            <Button
                                type="button"
                                onClick={handleGenerateVariantsFromAttributes}
                                size="sm"
                                className="gap-1.5 text-xs"
                            >
                                <Sparkles className="size-3.5" />
                                Niteliklerden Varyantları Otomatik Oluştur
                            </Button>
                        </div>
                    </div>

                    {/* Generated Variants List / Cards */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <h4 className="text-xs font-semibold text-foreground">
                                    Varyant Listesi ({variants.length} adet)
                                </h4>
                                <span className="text-[11px] text-muted-foreground">
                                    (Her biri ayrı fiyat, stok ve görsele sahip
                                    bağımsız ürün birimidir)
                                </span>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleAddManualVariant}
                                className="h-7 gap-1 text-xs"
                            >
                                <Plus className="size-3" />
                                Manuel Varyant Ekle
                            </Button>
                        </div>

                        {variants.length === 0 ? (
                            <div className="space-y-2 rounded-xl border border-dashed border-border p-8 text-center text-muted-foreground">
                                <Layers className="mx-auto size-8 opacity-40" />
                                <p className="text-xs font-medium">
                                    Henüz varyant oluşturulmadı.
                                </p>
                                <p className="text-[11px]">
                                    Yukarıdaki nitelikleri tanımlayıp "Otomatik
                                    Oluştur" butonuna basabilir veya manuel
                                    ekleyebilirsiniz.
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {variants.map((v, position) => (
                                    <div
                                        key={v.id}
                                        className="space-y-3 rounded-xl border border-border bg-card p-3.5 shadow-xs transition-all"
                                    >
                                        {/* Variant Header info */}
                                        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 pb-2">
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                <Badge
                                                    variant="outline"
                                                    className="font-mono text-xs font-semibold"
                                                >
                                                    #{position + 1}
                                                </Badge>
                                                {v.attributes &&
                                                    Object.entries(
                                                        v.attributes,
                                                    ).map(
                                                        ([
                                                            attrName,
                                                            attrVal,
                                                        ]) => (
                                                            <Badge
                                                                key={attrName}
                                                                variant="secondary"
                                                                className="text-xs font-normal"
                                                            >
                                                                {attrName}:{' '}
                                                                <strong className="ml-1">
                                                                    {attrVal}
                                                                </strong>
                                                            </Badge>
                                                        ),
                                                    )}
                                            </div>

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setVariants((prev) =>
                                                        prev.filter(
                                                            (item) =>
                                                                item.id !==
                                                                v.id,
                                                        ),
                                                    )
                                                }
                                                className="h-6 gap-1 px-2 text-xs text-muted-foreground hover:text-destructive"
                                            >
                                                <Trash2 className="size-3" />
                                                Kaldır
                                            </Button>
                                        </div>

                                        {/* Hidden attributes inputs */}
                                        {v.attributes &&
                                            Object.entries(v.attributes).map(
                                                ([k, val]) => (
                                                    <input
                                                        key={k}
                                                        type="hidden"
                                                        name={`variants[${position}][attributes][${k}]`}
                                                        value={val}
                                                    />
                                                ),
                                            )}
                                        <input
                                            type="hidden"
                                            name={`variants[${position}][image_url]`}
                                            value={v.imageUrl ?? ''}
                                        />

                                        {/* Variant Main Fields */}
                                        <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-12">
                                            {/* Image Assignment */}
                                            <div className="space-y-1 sm:col-span-3">
                                                <Label className="text-[11px] text-muted-foreground">
                                                    Varyant Görseli
                                                </Label>
                                                <div className="flex items-center gap-2">
                                                    <div className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-secondary/30">
                                                        {v.imageUrl ? (
                                                            <img
                                                                src={v.imageUrl}
                                                                alt="Variant"
                                                                className="size-full object-cover"
                                                            />
                                                        ) : (
                                                            <ImageIcon className="size-4 text-muted-foreground/60" />
                                                        )}
                                                    </div>
                                                    <Select
                                                        value={
                                                            v.imageUrl || 'none'
                                                        }
                                                        onValueChange={(
                                                            val,
                                                        ) => {
                                                            const newUrl =
                                                                val === 'none'
                                                                    ? undefined
                                                                    : val;
                                                            setVariants(
                                                                (prev) =>
                                                                    prev.map(
                                                                        (
                                                                            item,
                                                                        ) =>
                                                                            item.id ===
                                                                            v.id
                                                                                ? {
                                                                                      ...item,
                                                                                      imageUrl:
                                                                                          newUrl,
                                                                                  }
                                                                                : item,
                                                                    ),
                                                            );
                                                        }}
                                                    >
                                                        <SelectTrigger className="h-8 flex-1 text-xs">
                                                            <SelectValue placeholder="Görsel Seç" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem
                                                                value="none"
                                                                className="text-xs"
                                                            >
                                                                Görselsiz /
                                                                Genel Kapak
                                                            </SelectItem>
                                                            {galleryImages.map(
                                                                (img, i) => (
                                                                    <SelectItem
                                                                        key={
                                                                            img.id
                                                                        }
                                                                        value={
                                                                            img.url
                                                                        }
                                                                        className="text-xs"
                                                                    >
                                                                        Görsel #
                                                                        {i + 1}{' '}
                                                                        {i === 0
                                                                            ? '(Kapak)'
                                                                            : ''}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            {/* SKU */}
                                            <div className="space-y-1 sm:col-span-3">
                                                <Label className="text-[11px] text-muted-foreground">
                                                    SKU{' '}
                                                    <span className="text-destructive">
                                                        *
                                                    </span>
                                                </Label>
                                                <Input
                                                    name={`variants[${position}][sku]`}
                                                    required
                                                    value={v.sku}
                                                    onChange={(e) => {
                                                        const val =
                                                            e.target.value;
                                                        setVariants((prev) =>
                                                            prev.map((item) =>
                                                                item.id === v.id
                                                                    ? {
                                                                          ...item,
                                                                          sku: val,
                                                                      }
                                                                    : item,
                                                            ),
                                                        );
                                                    }}
                                                    placeholder="SKU-001"
                                                    className="h-8 font-mono text-xs uppercase"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `variants.${position}.sku`
                                                        ]
                                                    }
                                                />
                                            </div>

                                            {/* Barcode */}
                                            <div className="space-y-1 sm:col-span-2">
                                                <Label className="text-[11px] text-muted-foreground">
                                                    Barkod
                                                </Label>
                                                <Input
                                                    name={`variants[${position}][barcode]`}
                                                    value={v.barcode}
                                                    onChange={(e) => {
                                                        const val =
                                                            e.target.value;
                                                        setVariants((prev) =>
                                                            prev.map((item) =>
                                                                item.id === v.id
                                                                    ? {
                                                                          ...item,
                                                                          barcode:
                                                                              val,
                                                                      }
                                                                    : item,
                                                            ),
                                                        );
                                                    }}
                                                    placeholder="Barkod"
                                                    className="h-8 font-mono text-xs"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `variants.${position}.barcode`
                                                        ]
                                                    }
                                                />
                                            </div>

                                            {/* Price */}
                                            <div className="space-y-1 sm:col-span-2">
                                                <Label className="text-[11px] text-muted-foreground">
                                                    Fiyat (TRY)
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    name={`variants[${position}][list_price]`}
                                                    value={v.price}
                                                    onChange={(e) => {
                                                        const val =
                                                            e.target.value;
                                                        setVariants((prev) =>
                                                            prev.map((item) =>
                                                                item.id === v.id
                                                                    ? {
                                                                          ...item,
                                                                          price: val,
                                                                      }
                                                                    : item,
                                                            ),
                                                        );
                                                    }}
                                                    placeholder="0,00"
                                                    className="h-8 font-mono text-xs"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `variants.${position}.list_price`
                                                        ]
                                                    }
                                                />
                                            </div>

                                            {/* Stock */}
                                            <div className="space-y-1 sm:col-span-2">
                                                <Label className="text-[11px] text-muted-foreground">
                                                    Stok
                                                </Label>
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    name={`variants[${position}][on_hand]`}
                                                    value={v.stock}
                                                    onChange={(e) => {
                                                        const val =
                                                            e.target.value;
                                                        setVariants((prev) =>
                                                            prev.map((item) =>
                                                                item.id === v.id
                                                                    ? {
                                                                          ...item,
                                                                          stock: val,
                                                                      }
                                                                    : item,
                                                            ),
                                                        );
                                                    }}
                                                    placeholder="0"
                                                    className="h-8 font-mono text-xs"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `variants.${position}.on_hand`
                                                        ]
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
