import { router } from '@inertiajs/react';
import { Filter, Pencil, Plus, Trash2, Wand2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import DynamicCategoryController from '@/actions/App/Http/Controllers/Catalog/DynamicCategoryController';
import { toastError } from '@/components/catalog/toast-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { Textarea } from '@/components/ui/textarea';

export type ConditionRow = {
    id?: number;
    field: string;
    fieldLabel?: string;
    operator: string;
    operatorLabel?: string;
    value: any;
};

export type DynamicCategoryRow = {
    id: number;
    name: string;
    slug: string;
    matchType: 'all' | 'any';
    matchTypeLabel?: string;
    description: string | null;
    conditionCount: number;
    productCount: number;
    createdAt: string;
    conditions?: ConditionRow[];
};

type Option = { value: string; label: string };
type IdName = { id: number; name: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categoryToEdit?: DynamicCategoryRow | null;
    fields: Option[];
    operators: Option[];
    matchTypes?: Option[];
    brands?: IdName[];
    productCategories?: IdName[];
    tags?: IdName[];
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

export function DynamicCategoryDialog({
    open,
    onOpenChange,
    categoryToEdit,
    fields,
    operators,
}: Props) {
    const isEditing = Boolean(categoryToEdit);
    const formKey = categoryToEdit
        ? `edit-${categoryToEdit.id}`
        : 'create-dynamic-cat';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[640px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isEditing ? (
                            <>
                                <Pencil className="size-5 text-primary" />
                                Dinamik Kategoriyi Düzenle
                            </>
                        ) : (
                            <>
                                <Wand2 className="size-5 text-primary" />
                                Yeni Dinamik Kategori
                            </>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        Belirleyeceğiniz kurallara uyan mevcut ve ileride
                        eklenecek ürünler bu kategoriye otomatik olarak eklenir.
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <DynamicCategoryFormContent
                        key={formKey}
                        categoryToEdit={categoryToEdit}
                        isEditing={isEditing}
                        fields={fields}
                        operators={operators}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function DynamicCategoryFormContent({
    categoryToEdit,
    isEditing,
    fields,
    operators,
    onClose,
}: {
    categoryToEdit?: DynamicCategoryRow | null;
    isEditing: boolean;
    fields: Option[];
    operators: Option[];
    onClose: () => void;
}) {
    const [name, setName] = useState(categoryToEdit?.name ?? '');
    const [description, setDescription] = useState(
        categoryToEdit?.description ?? '',
    );
    const [matchType, setMatchType] = useState<'all' | 'any'>(
        categoryToEdit?.matchType ?? 'all',
    );
    const [conditions, setConditions] = useState<ConditionRow[]>(
        categoryToEdit?.conditions && categoryToEdit.conditions.length > 0
            ? categoryToEdit.conditions
            : [{ field: 'brand', operator: 'contains', value: '' }],
    );
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{
        name?: string;
        slug?: string;
        description?: string;
        match_type?: string;
    }>({});

    const previewSlug = useMemo(() => slugify(name), [name]);

    const addCondition = () => {
        setConditions((prev) => [
            ...prev,
            { field: 'name', operator: 'contains', value: '' },
        ]);
    };

    const removeCondition = (index: number) => {
        setConditions((prev) => prev.filter((_, i) => i !== index));
    };

    const updateCondition = (
        index: number,
        key: keyof ConditionRow,
        val: any,
    ) => {
        setConditions((prev) =>
            prev.map((item, i) =>
                i === index ? { ...item, [key]: val } : item,
            ),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const payload = {
            name,
            description: description || null,
            match_type: matchType,
            conditions: conditions.map((c) => ({
                field: c.field,
                operator: c.operator,
                value: c.value,
            })),
        };

        if (isEditing && categoryToEdit) {
            router.patch(
                DynamicCategoryController.update.url({
                    dynamicCategory: categoryToEdit.id,
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
            router.post(DynamicCategoryController.store.url(), payload, {
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
            <div className="grid gap-2">
                <Label htmlFor="dyn-name">Kategori Adı</Label>
                <Input
                    id="dyn-name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Örn: İndirimli Spor Ayakkabılar, Yaz Fırsatları..."
                    autoFocus
                    required
                />
                <InputError message={errors.name ?? errors.slug} />
            </div>

            {previewSlug && (
                <div className="rounded-lg border border-border bg-muted/40 p-2 text-xs text-muted-foreground">
                    <span className="font-medium text-foreground">Slug: </span>
                    <code className="font-mono text-foreground">
                        {previewSlug}
                    </code>
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="dyn-desc">Açıklama (İsteğe Bağlı)</Label>
                <Textarea
                    id="dyn-desc"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="Kategori amacı veya kampanya detayı..."
                    rows={2}
                />
                <InputError message={errors.description} />
            </div>

            {/* Koşul Eşleşme Tipi */}
            <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-3">
                <Label className="text-xs font-semibold text-foreground">
                    Koşul Eşleşme Kuralı
                </Label>
                <div className="flex flex-col gap-2 sm:flex-row sm:gap-6">
                    <label className="flex cursor-pointer items-center space-x-2 text-xs font-normal text-foreground">
                        <input
                            type="radio"
                            name="match_type"
                            value="all"
                            checked={matchType === 'all'}
                            onChange={() => setMatchType('all')}
                            className="size-4 text-primary accent-primary"
                        />
                        <span>Tüm koşulları sağlamalı (VE)</span>
                    </label>
                    <label className="flex cursor-pointer items-center space-x-2 text-xs font-normal text-foreground">
                        <input
                            type="radio"
                            name="match_type"
                            value="any"
                            checked={matchType === 'any'}
                            onChange={() => setMatchType('any')}
                            className="size-4 text-primary accent-primary"
                        />
                        <span>En az bir koşulu sağlamalı (VEYA)</span>
                    </label>
                </div>
            </div>

            {/* Koşullar Listesi */}
            <div className="space-y-3">
                <div className="flex items-center justify-between">
                    <Label className="flex items-center gap-1.5 text-xs font-semibold">
                        <Filter className="size-3.5 text-primary" />
                        Koşullar ({conditions.length})
                    </Label>
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

                {conditions.map((cond, idx) => (
                    <div
                        key={idx}
                        className="flex flex-col gap-2 rounded-lg border border-border bg-card p-3 shadow-2xs sm:flex-row sm:items-center"
                    >
                        {/* Alan Seçimi */}
                        <div className="w-full sm:w-1/3">
                            <Select
                                value={cond.field}
                                onValueChange={(val) =>
                                    updateCondition(idx, 'field', val)
                                }
                            >
                                <SelectTrigger className="h-8 text-xs">
                                    <SelectValue placeholder="Alan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {fields.map((f) => (
                                        <SelectItem
                                            key={f.value}
                                            value={f.value}
                                            className="text-xs"
                                        >
                                            {f.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Operatör Seçimi */}
                        <div className="w-full sm:w-1/3">
                            <Select
                                value={cond.operator}
                                onValueChange={(val) =>
                                    updateCondition(idx, 'operator', val)
                                }
                            >
                                <SelectTrigger className="h-8 text-xs">
                                    <SelectValue placeholder="Operatör" />
                                </SelectTrigger>
                                <SelectContent>
                                    {operators.map((o) => (
                                        <SelectItem
                                            key={o.value}
                                            value={o.value}
                                            className="text-xs"
                                        >
                                            {o.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Değer Girişi */}
                        <div className="w-full sm:flex-1">
                            <Input
                                value={cond.value ?? ''}
                                onChange={(e) =>
                                    updateCondition(
                                        idx,
                                        'value',
                                        e.target.value,
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
                                onClick={() => removeCondition(idx)}
                                className="size-8 shrink-0 self-end text-destructive hover:bg-destructive/10 sm:self-center"
                                aria-label="Koşulu kaldır"
                            >
                                <Trash2 className="size-3.5" />
                            </Button>
                        )}
                    </div>
                ))}
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
                          ? 'Güncelle & Çalıştır'
                          : 'Oluştur & Çalıştır'}
                </Button>
            </DialogFooter>
        </form>
    );
}
