import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ExternalLink,
    Filter,
    Loader2,
    Pencil,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DynamicCategoryController from '@/actions/App/Http/Controllers/Catalog/DynamicCategoryController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { index as definitions } from '@/routes/definitions';
import { index, show } from '@/routes/dynamic-categories';

type Option = { value: string; label: string };
type IdName = { id: number; name: string };

type ConditionItem = {
    id?: number;
    field: string;
    operator: string;
    value: string;
};

type CategoryData = {
    id: number;
    name: string;
    slug: string;
    matchType: 'all' | 'any';
    matchTypeLabel?: string;
    description: string | null;
    conditions: ConditionItem[];
};

function slugify(text: string): string {
    const trMap: Record<string, string> = {
        ç: 'c',
        Ç: 'c',
        ğ: 'g',
        Ğ: 'g',
        ı: 'i',
        I: 'i',
        İ: 'i',
        ö: 'o',
        Ö: 'o',
        ş: 's',
        Ş: 's',
        ü: 'u',
        Ü: 'u',
    };

    return text
        .split('')
        .map((char) => trMap[char] ?? char)
        .join('')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

export default function DynamicCategoryEdit({
    category,
    fields = [],
    operators = [],
}: {
    category: CategoryData;
    fields: Option[];
    operators: Option[];
    matchTypes?: Option[];
    brands?: IdName[];
    productCategories?: IdName[];
    tags?: IdName[];
}) {
    const [name, setName] = useState(category.name);
    const [slug, setSlug] = useState(category.slug);
    const [description, setDescription] = useState(category.description ?? '');
    const [matchType, setMatchType] = useState<'all' | 'any'>(
        category.matchType,
    );
    const [conditions, setConditions] = useState<ConditionItem[]>(
        category.conditions && category.conditions.length > 0
            ? category.conditions
            : [
                  {
                      field: fields[0]?.value ?? 'brand',
                      operator: operators[0]?.value ?? 'contains',
                      value: '',
                  },
              ],
    );

    const previewSlug = useMemo(() => {
        if (slug.trim()) {
            return slugify(slug);
        }

        return slugify(name);
    }, [slug, name]);

    const addCondition = () => {
        setConditions((prev) => [
            ...prev,
            {
                field: fields[0]?.value ?? 'name',
                operator: operators[0]?.value ?? 'contains',
                value: '',
            },
        ]);
    };

    const removeCondition = (indexToRemove: number) => {
        setConditions((prev) => prev.filter((_, i) => i !== indexToRemove));
    };

    const updateCondition = (
        indexToUpdate: number,
        key: keyof ConditionItem,
        val: string,
    ) => {
        setConditions((prev) =>
            prev.map((item, i) =>
                i === indexToUpdate ? { ...item, [key]: val } : item,
            ),
        );
    };

    return (
        <>
            <Head title={`Dinamik Kategoriyi Düzenle: ${category.name}`} />

            <div className="mx-auto flex max-w-7xl flex-col gap-5 p-4 font-sans sm:p-6 lg:p-8">
                {/* Top Navigation */}
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
                                <span>Dinamik Kategoriler</span>
                            </Link>
                        </Button>
                        <span className="text-muted-foreground/40">/</span>
                        <span className="font-sans text-sm font-semibold text-foreground">
                            {category.name} Düzenle
                        </span>
                    </div>

                    <Button
                        asChild
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1.5 text-xs"
                    >
                        <Link href={show({ dynamicCategory: category.id })}>
                            <ExternalLink className="size-3.5" />
                            Eşleşen Ürünleri Görüntüle
                        </Link>
                    </Button>
                </div>

                {/* Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                    <div className="space-y-1">
                        <h1 className="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Dinamik Kategoriyi Düzenle
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Koşulları ve kategori ayarlarını güncelleyin.
                        </p>
                    </div>
                </div>

                {/* Form */}
                <Form
                    {...DynamicCategoryController.update.form({
                        dynamicCategory: category.id,
                    })}
                    className="grid grid-cols-1 gap-5 lg:grid-cols-12"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Hidden Match Type & Conditions Inputs */}
                            <input
                                type="hidden"
                                name="match_type"
                                value={matchType}
                            />
                            {conditions.map((cond, i) => (
                                <span key={i}>
                                    <input
                                        type="hidden"
                                        name={`conditions[${i}][field]`}
                                        value={cond.field}
                                    />
                                    <input
                                        type="hidden"
                                        name={`conditions[${i}][operator]`}
                                        value={cond.operator}
                                    />
                                    <input
                                        type="hidden"
                                        name={`conditions[${i}][value]`}
                                        value={cond.value}
                                    />
                                </span>
                            ))}

                            {/* Left Column */}
                            <div className="space-y-5 lg:col-span-8">
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Pencil className="size-4 text-primary" />
                                            Temel Bilgiler
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Kategori adı, benzersiz kodu ve
                                            açıklamasını düzenleyin.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="name"
                                                className="text-xs font-medium"
                                            >
                                                Kategori Adı{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                autoFocus
                                                value={name}
                                                onChange={(e) =>
                                                    setName(e.target.value)
                                                }
                                                placeholder="Örn: İndirimli Spor Ayakkabılar, Yaz Fırsatları..."
                                                className="h-9 text-xs font-medium"
                                            />
                                            <InputError
                                                message={
                                                    errors.name ?? errors.slug
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="slug"
                                                className="text-xs font-medium"
                                            >
                                                Slug (Opsiyonel)
                                            </Label>
                                            <Input
                                                id="slug"
                                                name="slug"
                                                value={slug}
                                                onChange={(e) =>
                                                    setSlug(e.target.value)
                                                }
                                                placeholder={
                                                    previewSlug ||
                                                    'yaz-firsatlari'
                                                }
                                                className="h-9 font-mono text-xs"
                                            />
                                            <InputError message={errors.slug} />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="description"
                                                className="text-xs font-medium"
                                            >
                                                Açıklama (Opsiyonel)
                                            </Label>
                                            <Textarea
                                                id="description"
                                                name="description"
                                                rows={2}
                                                value={description}
                                                onChange={(e) =>
                                                    setDescription(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Kategori amacı veya kampanya detayı..."
                                                className="text-xs"
                                            />
                                            <InputError
                                                message={errors.description}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Koşullar Kartı */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                                <Filter className="size-4 text-primary" />
                                                Eşleşme Kuralları & Koşullar (
                                                {conditions.length})
                                            </CardTitle>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={addCondition}
                                                className="h-7 gap-1 text-xs"
                                            >
                                                <Plus className="size-3" />
                                                Koşul Ekle
                                            </Button>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        {/* Koşul Eşleşme Tipi */}
                                        <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-3">
                                            <Label className="text-xs font-semibold text-foreground">
                                                Koşul Eşleşme Mantığı
                                            </Label>
                                            <div className="flex flex-col gap-2 sm:flex-row sm:gap-6">
                                                <label className="flex cursor-pointer items-center space-x-2 text-xs font-normal text-foreground">
                                                    <input
                                                        type="radio"
                                                        name="match_type_radio"
                                                        value="all"
                                                        checked={
                                                            matchType === 'all'
                                                        }
                                                        onChange={() =>
                                                            setMatchType('all')
                                                        }
                                                        className="size-4 text-primary accent-primary"
                                                    />
                                                    <span>
                                                        Tüm koşulları sağlamalı
                                                        (VE)
                                                    </span>
                                                </label>
                                                <label className="flex cursor-pointer items-center space-x-2 text-xs font-normal text-foreground">
                                                    <input
                                                        type="radio"
                                                        name="match_type_radio"
                                                        value="any"
                                                        checked={
                                                            matchType === 'any'
                                                        }
                                                        onChange={() =>
                                                            setMatchType('any')
                                                        }
                                                        className="size-4 text-primary accent-primary"
                                                    />
                                                    <span>
                                                        En az bir koşulu
                                                        sağlamalı (VEYA)
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        {/* Koşul Satırları */}
                                        <div className="space-y-3">
                                            {conditions.map((cond, idx) => (
                                                <div
                                                    key={idx}
                                                    className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3 shadow-2xs sm:flex-row sm:items-center"
                                                >
                                                    {/* Alan Seçimi */}
                                                    <div className="w-full sm:w-1/3">
                                                        <Select
                                                            value={cond.field}
                                                            onValueChange={(
                                                                val,
                                                            ) =>
                                                                updateCondition(
                                                                    idx,
                                                                    'field',
                                                                    val,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="h-8 text-xs">
                                                                <SelectValue placeholder="Alan" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {fields.map(
                                                                    (f) => (
                                                                        <SelectItem
                                                                            key={
                                                                                f.value
                                                                            }
                                                                            value={
                                                                                f.value
                                                                            }
                                                                            className="text-xs"
                                                                        >
                                                                            {
                                                                                f.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    {/* Operatör Seçimi */}
                                                    <div className="w-full sm:w-1/3">
                                                        <Select
                                                            value={
                                                                cond.operator
                                                            }
                                                            onValueChange={(
                                                                val,
                                                            ) =>
                                                                updateCondition(
                                                                    idx,
                                                                    'operator',
                                                                    val,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger className="h-8 text-xs">
                                                                <SelectValue placeholder="Operatör" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {operators.map(
                                                                    (o) => (
                                                                        <SelectItem
                                                                            key={
                                                                                o.value
                                                                            }
                                                                            value={
                                                                                o.value
                                                                            }
                                                                            className="text-xs"
                                                                        >
                                                                            {
                                                                                o.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>

                                                    {/* Değer Girişi */}
                                                    <div className="w-full sm:flex-1">
                                                        <Input
                                                            value={
                                                                cond.value ?? ''
                                                            }
                                                            onChange={(e) =>
                                                                updateCondition(
                                                                    idx,
                                                                    'value',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Değer yazın..."
                                                            className="h-8 text-xs"
                                                        />
                                                    </div>

                                                    {/* Sil Butonu */}
                                                    {conditions.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                removeCondition(
                                                                    idx,
                                                                )
                                                            }
                                                            className="size-8 shrink-0 self-end text-destructive hover:bg-destructive/10 sm:self-center"
                                                            aria-label="Koşulu kaldır"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Right Column */}
                            <div className="space-y-5 lg:col-span-4">
                                <Card className="gap-0 overflow-hidden border-primary/20 bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="text-sm font-semibold">
                                            Değişiklikleri Kaydet
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3.5 p-4">
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing || !name.trim()
                                            }
                                            className="h-9 w-full gap-2 text-xs font-semibold shadow-xs"
                                        >
                                            {processing ? (
                                                <Loader2 className="size-3.5 animate-spin" />
                                            ) : (
                                                <Save className="size-3.5" />
                                            )}
                                            Güncellemeyi Kaydet
                                        </Button>

                                        <Button
                                            asChild
                                            type="button"
                                            variant="outline"
                                            className="h-9 w-full text-xs"
                                        >
                                            <Link href={index()}>Vazgeç</Link>
                                        </Button>
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

DynamicCategoryEdit.layout = {
    breadcrumbs: [
        {
            title: 'Tanımlamalar',
            href: definitions(),
        },
        {
            title: 'Dinamik Kategoriler',
            href: index(),
        },
        {
            title: 'Dinamik Kategoriyi Düzenle',
        },
    ],
};
