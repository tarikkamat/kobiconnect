import { router } from '@inertiajs/react';
import { Pencil, Tag, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import AttributeController from '@/actions/App/Http/Controllers/Catalog/AttributeController';
import { toastError } from '@/components/catalog/toast-error';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
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

export type AttributeValueItem = {
    id?: number;
    value: string;
    position?: number;
};

export type AttributeRow = {
    id: number;
    name: string;
    code: string;
    type: string;
    isVariantDefining: boolean;
    valuesCount: number;
    values: AttributeValueItem[];
};

export type AttributeTypeOption = {
    value: string;
    label: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    attributeToEdit?: AttributeRow | null;
    types?: AttributeTypeOption[];
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

export function AttributeDialog({
    open,
    onOpenChange,
    attributeToEdit,
    types = [
        { value: 'select', label: 'Seçim Kutusu (Tekli)' },
        { value: 'multi_select', label: 'Çoklu Seçim' },
        { value: 'text', label: 'Metin (Serbest Yazı)' },
        { value: 'number', label: 'Sayısal Değer' },
        { value: 'boolean', label: 'Mantıksal (Evet / Hayır)' },
    ],
}: Props) {
    const isEditing = Boolean(attributeToEdit);
    const formKey = attributeToEdit
        ? `edit-${attributeToEdit.id}`
        : 'create-attribute';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[540px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isEditing ? (
                            <>
                                <Pencil className="size-5 text-primary" />
                                Niteliği Düzenle
                            </>
                        ) : (
                            <>
                                <Tag className="size-5 text-primary" />
                                Yeni Nitelik Tanımla
                            </>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Nitelik bilgilerini ve değer seçeneklerini güncelleyin.'
                            : 'Ürün varyantlarında veya ürün özelliklerinde kullanmak üzere nitelik ve hazır değerlerini tanımlayın.'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <AttributeFormContent
                        key={formKey}
                        attributeToEdit={attributeToEdit}
                        types={types}
                        isEditing={isEditing}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function AttributeFormContent({
    attributeToEdit,
    types,
    isEditing,
    onClose,
}: {
    attributeToEdit?: AttributeRow | null;
    types: AttributeTypeOption[];
    isEditing: boolean;
    onClose: () => void;
}) {
    const [name, setName] = useState(attributeToEdit?.name ?? '');
    const [code, setCode] = useState(attributeToEdit?.code ?? '');
    const [type, setType] = useState<string>(attributeToEdit?.type ?? 'select');
    const [isVariantDefining, setIsVariantDefining] = useState<boolean>(
        attributeToEdit?.isVariantDefining ?? true,
    );
    const [values, setValues] = useState<string[]>(
        attributeToEdit?.values?.map((v) => v.value) ?? [],
    );
    const [currentValueInput, setCurrentValueInput] = useState('');

    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{
        name?: string;
        code?: string;
        type?: string;
        values?: string;
    }>({});

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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        // Eğer input içinde yazılmış ama Enter basılmamış değer varsa otomatik ekle
        const finalValues = [...values];

        if (currentValueInput.trim()) {
            const extra = currentValueInput.trim();

            if (!finalValues.includes(extra)) {
                finalValues.push(extra);
            }
        }

        const payload = {
            name: name.trim(),
            code: code.trim() ? slugify(code) : slugify(name),
            type,
            is_variant_defining: isVariantDefining,
            values: finalValues,
        };

        if (isEditing && attributeToEdit) {
            router.patch(
                AttributeController.update.url({
                    attribute: attributeToEdit.id,
                }),
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setProcessing(false);
                        onClose();
                    },
                    onError: (errs) => {
                        setProcessing(false);
                        setErrors(errs);
                        toastError(errs);
                    },
                },
            );
        } else {
            router.post(AttributeController.store.url(), payload, {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    onClose();
                },
                onError: (errs) => {
                    setProcessing(false);
                    setErrors(errs);
                    toastError(errs);
                },
            });
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4 py-2">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5 sm:col-span-2">
                    <Label htmlFor="attr-name">
                        Nitelik Adı <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="attr-name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="Örn: Beden, Renk, Materyal, Hafıza..."
                        autoFocus
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="attr-code">Kod / Slug (Opsiyonel)</Label>
                    <Input
                        id="attr-code"
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        placeholder={previewCode || 'beden'}
                        className="font-mono text-xs"
                    />
                    <InputError message={errors.code} />
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="attr-type">Nitelik Türü</Label>
                    <Select value={type} onValueChange={setType}>
                        <SelectTrigger id="attr-type" className="text-xs">
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
                    <InputError message={errors.type} />
                </div>
            </div>

            {/* Varyant Belirleyici Switch/Checkbox */}
            <div className="flex items-start gap-2.5 rounded-lg border border-border/80 bg-muted/30 p-3">
                <Checkbox
                    id="is-variant-defining"
                    checked={isVariantDefining}
                    onCheckedChange={(checked) =>
                        setIsVariantDefining(Boolean(checked))
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
                        İşaretlendiğinde bu nitelik (Beden, Renk vb.) ürünlerde
                        farklı varyant kombinasyonları oluşturmak için
                        kullanılır.
                    </p>
                </div>
            </div>

            {/* Değerler Listesi (Tags Input) */}
            <div className="grid gap-2">
                <div className="flex items-center justify-between">
                    <Label className="text-xs font-medium">
                        Tanımlı Değerler / Seçenekler ({values.length})
                    </Label>
                    <span className="text-[11px] text-muted-foreground">
                        Virgül veya Enter ile ekleyin
                    </span>
                </div>

                <div className="flex min-h-16 flex-wrap items-center gap-1.5 rounded-md border border-input bg-background p-2">
                    {values.map((val) => (
                        <Badge
                            key={val}
                            variant="secondary"
                            className="h-6 gap-1 pr-1 pl-2.5 text-xs font-normal"
                        >
                            <span>{val}</span>
                            <button
                                type="button"
                                onClick={() => handleRemoveValue(val)}
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
                            const inputVal = e.target.value;

                            if (inputVal.includes(',')) {
                                const parts = inputVal.split(',');
                                parts.forEach((p) => handleAddValue(p));
                            } else {
                                setCurrentValueInput(inputVal);
                            }
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                handleAddValue(currentValueInput);
                            }
                        }}
                        placeholder={
                            values.length === 0
                                ? 'Örn: S, M, L, XL veya Siyah, Beyaz...'
                                : '+ Değer ekle...'
                        }
                        className="h-6 min-w-32 flex-1 border-0 bg-transparent px-1 text-xs outline-hidden placeholder:text-muted-foreground/60"
                    />
                </div>
                <InputError message={errors.values} />
            </div>

            <DialogFooter className="pt-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onClose}
                    disabled={processing}
                >
                    Vazgeç
                </Button>
                <Button
                    type="submit"
                    disabled={processing || name.trim() === ''}
                >
                    {processing
                        ? 'Kaydediliyor...'
                        : isEditing
                          ? 'Güncelle'
                          : 'Oluştur'}
                </Button>
            </DialogFooter>
        </form>
    );
}
