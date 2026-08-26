import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Loader2, Pencil, Save, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import AttributeController from '@/actions/App/Http/Controllers/Catalog/AttributeController';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/attributes';
import { index as definitions } from '@/routes/definitions';

type AttributeValueData = {
    id: number;
    value: string;
    position: number;
};

type AttributeData = {
    id: number;
    name: string;
    code: string;
    type: string;
    isVariantDefining: boolean;
    values: AttributeValueData[];
};

type AttributeTypeOption = {
    value: string;
    label: string;
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

export default function AttributeEdit({
    attribute,
    types = [
        { value: 'select', label: 'Seçim Kutusu (Tekli)' },
        { value: 'multi_select', label: 'Çoklu Seçim' },
        { value: 'text', label: 'Metin (Serbest Yazı)' },
        { value: 'number', label: 'Sayısal Değer' },
        { value: 'boolean', label: 'Mantıksal (Evet / Hayır)' },
    ],
}: {
    attribute: AttributeData;
    types?: AttributeTypeOption[];
}) {
    const [name, setName] = useState(attribute.name);
    const [code, setCode] = useState(attribute.code);
    const [type, setType] = useState<string>(attribute.type);
    const [isVariantDefining, setIsVariantDefining] = useState<boolean>(
        attribute.isVariantDefining,
    );
    const [values, setValues] = useState<string[]>(
        attribute.values?.map((v) => v.value) ?? [],
    );
    const [currentValueInput, setCurrentValueInput] = useState('');

    const previewCode = useMemo(() => {
        if (code.trim()) {
            return slugify(code);
        }

        return slugify(name);
    }, [code, name]);

    const handleAddValue = (val: string) => {
        const trimmed = val.trim();

        if (!trimmed) {
            return;
        }

        if (!values.includes(trimmed)) {
            setValues((prev) => [...prev, trimmed]);
        }

        setCurrentValueInput('');
    };

    const handleRemoveValue = (valToRemove: string) => {
        setValues((prev) => prev.filter((v) => v !== valToRemove));
    };

    return (
        <>
            <Head title={`Özel Alanı Düzenle: ${attribute.name}`} />

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
                                <span>Özel Alanlar</span>
                            </Link>
                        </Button>
                        <span className="text-muted-foreground/40">/</span>
                        <span className="font-sans text-sm font-semibold text-foreground">
                            {attribute.name} Düzenle
                        </span>
                    </div>
                </div>

                {/* Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                    <div className="space-y-1">
                        <h1 className="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Özel Alanı Düzenle
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Nitelik bilgilerini ve değer seçeneklerini
                            güncelleyin.
                        </p>
                    </div>
                </div>

                {/* Form */}
                <Form
                    {...AttributeController.update.form({
                        attribute: attribute.id,
                    })}
                    className="grid grid-cols-1 gap-5 lg:grid-cols-12"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Hidden Type & Variant Inputs */}
                            <input type="hidden" name="type" value={type} />
                            <input
                                type="hidden"
                                name="is_variant_defining"
                                value={isVariantDefining ? '1' : '0'}
                            />
                            {values.map((v, i) => (
                                <input
                                    key={i}
                                    type="hidden"
                                    name={`values[${i}]`}
                                    value={v}
                                />
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
                                            Nitelik adı, kodu ve veri türünü
                                            güncelleyin.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                            <div className="grid gap-1.5 sm:col-span-2">
                                                <Label
                                                    htmlFor="name"
                                                    className="text-xs font-medium"
                                                >
                                                    Nitelik Adı{' '}
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
                                                    placeholder="Örn: Beden, Renk, Sezon, Cinsiyet..."
                                                    className="h-9 text-xs font-medium"
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>

                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor="code"
                                                    className="text-xs font-medium"
                                                >
                                                    Kod / Slug (Opsiyonel)
                                                </Label>
                                                <Input
                                                    id="code"
                                                    name="code"
                                                    value={code}
                                                    onChange={(e) =>
                                                        setCode(e.target.value)
                                                    }
                                                    placeholder={
                                                        previewCode || 'beden'
                                                    }
                                                    className="h-9 font-mono text-xs"
                                                />
                                                <InputError
                                                    message={errors.code}
                                                />
                                            </div>

                                            <div className="grid gap-1.5">
                                                <Label
                                                    htmlFor="type"
                                                    className="text-xs font-medium"
                                                >
                                                    Nitelik Türü
                                                </Label>
                                                <Select
                                                    value={type}
                                                    onValueChange={setType}
                                                >
                                                    <SelectTrigger
                                                        id="type"
                                                        className="h-9 text-xs"
                                                    >
                                                        <SelectValue placeholder="Tür Seçin" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {types.map((t) => (
                                                            <SelectItem
                                                                key={t.value}
                                                                value={t.value}
                                                                className="text-xs"
                                                            >
                                                                {t.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={errors.type}
                                                />
                                            </div>
                                        </div>

                                        {/* Varyant Belirleyici Checkbox */}
                                        <div className="flex items-start gap-2.5 rounded-lg border border-border/80 bg-muted/30 p-3">
                                            <Checkbox
                                                id="is-variant-defining"
                                                checked={isVariantDefining}
                                                onCheckedChange={(checked) =>
                                                    setIsVariantDefining(
                                                        Boolean(checked),
                                                    )
                                                }
                                                className="mt-0.5"
                                            />
                                            <div className="grid gap-0.5">
                                                <Label
                                                    htmlFor="is-variant-defining"
                                                    className="cursor-pointer text-xs font-medium text-foreground"
                                                >
                                                    Varyant Tanımlayıcı Nitelik
                                                </Label>
                                                <p className="text-[11px] text-muted-foreground">
                                                    İşaretlendiğinde bu nitelik
                                                    (Beden, Renk vb.) ürünlerde
                                                    farklı varyant
                                                    kombinasyonları oluşturmak
                                                    için kullanılır.
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Değerler Kartı */}
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="text-sm font-semibold">
                                                Tanımlı Değerler / Seçenekler (
                                                {values.length})
                                            </CardTitle>
                                            <span className="text-[11px] text-muted-foreground">
                                                Virgül veya Enter ile ekleyin
                                            </span>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="flex min-h-20 flex-wrap items-center gap-1.5 rounded-md border border-input bg-background p-2.5">
                                            {values.map((val) => (
                                                <Badge
                                                    key={val}
                                                    variant="secondary"
                                                    className="h-6 gap-1 pr-1 pl-2.5 text-xs font-normal"
                                                >
                                                    <span>{val}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleRemoveValue(
                                                                val,
                                                            )
                                                        }
                                                        className="rounded-xs hover:bg-destructive/20 hover:text-destructive"
                                                        aria-label={`${val} değerini kaldır`}
                                                    >
                                                        <X className="size-3" />
                                                    </button>
                                                </Badge>
                                            ))}

                                            <input
                                                type="text"
                                                value={currentValueInput}
                                                onChange={(e) => {
                                                    const inputVal =
                                                        e.target.value;

                                                    if (
                                                        inputVal.includes(',')
                                                    ) {
                                                        const parts =
                                                            inputVal.split(',');
                                                        parts.forEach((p) =>
                                                            handleAddValue(p),
                                                        );
                                                    } else {
                                                        setCurrentValueInput(
                                                            inputVal,
                                                        );
                                                    }
                                                }}
                                                onKeyDown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        handleAddValue(
                                                            currentValueInput,
                                                        );
                                                    }
                                                }}
                                                placeholder={
                                                    values.length === 0
                                                        ? 'Örn: S, M, L, XL veya Siyah, Beyaz...'
                                                        : '+ Değer ekle...'
                                                }
                                                className="h-6 min-w-36 flex-1 border-0 bg-transparent px-1 text-xs outline-hidden placeholder:text-muted-foreground/60"
                                            />
                                        </div>
                                        <InputError message={errors.values} />
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
                                            onClick={() => {
                                                if (
                                                    currentValueInput.trim() &&
                                                    !values.includes(
                                                        currentValueInput.trim(),
                                                    )
                                                ) {
                                                    setValues((prev) => [
                                                        ...prev,
                                                        currentValueInput.trim(),
                                                    ]);
                                                }
                                            }}
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

AttributeEdit.layout = {
    breadcrumbs: [
        {
            title: 'Tanımlamalar',
            href: definitions(),
        },
        {
            title: 'Özel Alanlar',
            href: index(),
        },
        {
            title: 'Özel Alanı Düzenle',
        },
    ],
};
