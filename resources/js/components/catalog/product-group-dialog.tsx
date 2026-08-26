import { router } from '@inertiajs/react';
import { Layers, Pencil } from 'lucide-react';
import { useMemo, useState } from 'react';
import ProductGroupController from '@/actions/App/Http/Controllers/Catalog/ProductGroupController';
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
import { Textarea } from '@/components/ui/textarea';

export type ProductGroupRow = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    productCount: number;
    createdAt: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    groupToEdit?: ProductGroupRow | null;
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

export function ProductGroupDialog({ open, onOpenChange, groupToEdit }: Props) {
    const isEditing = Boolean(groupToEdit);
    const formKey = groupToEdit ? `edit-${groupToEdit.id}` : 'create-group';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isEditing ? (
                            <>
                                <Pencil className="size-5 text-primary" />
                                Ürün Grubunu Düzenle
                            </>
                        ) : (
                            <>
                                <Layers className="size-5 text-primary" />
                                Yeni Ürün Grubu Ekle
                            </>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Ürün grubu bilgilerini güncelleyin.'
                            : 'Ürünlerinizi belirli kriterlere göre gruplayarak detay sayfasında ve listelemelerde nasıl görüneceklerini ayarlayın.'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <ProductGroupFormContent
                        key={formKey}
                        groupToEdit={groupToEdit}
                        isEditing={isEditing}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ProductGroupFormContent({
    groupToEdit,
    isEditing,
    onClose,
}: {
    groupToEdit?: ProductGroupRow | null;
    isEditing: boolean;
    onClose: () => void;
}) {
    const [name, setName] = useState(groupToEdit?.name ?? '');
    const [description, setDescription] = useState(
        groupToEdit?.description ?? '',
    );
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{
        name?: string;
        slug?: string;
        description?: string;
    }>({});

    const previewSlug = useMemo(() => slugify(name), [name]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        if (isEditing && groupToEdit) {
            router.patch(
                ProductGroupController.update.url({
                    productGroup: groupToEdit.id,
                }),
                { name, description: description || null },
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
            router.post(
                ProductGroupController.store.url(),
                { name, description: description || null },
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
        }
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="grid gap-4 py-4">
                <div className="grid gap-2">
                    <Label htmlFor="group-name">Grup Adı</Label>
                    <Input
                        id="group-name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="Örn: Yaz Koleksiyonu, Tamamlayıcı Ürünler..."
                        autoFocus
                        required
                    />
                    <InputError message={errors.name ?? errors.slug} />
                </div>

                {previewSlug && (
                    <div className="rounded-lg border border-border bg-muted/40 p-2.5 text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">
                            Slug Önizleme:{' '}
                        </span>
                        <code className="font-mono text-foreground">
                            {previewSlug}
                        </code>
                    </div>
                )}

                <div className="grid gap-2">
                    <Label htmlFor="group-description">
                        Açıklama (İsteğe Bağlı)
                    </Label>
                    <Textarea
                        id="group-description"
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        placeholder="Grup hakkında kısa açıklama veya tema detayı..."
                        rows={3}
                    />
                    <InputError message={errors.description} />
                </div>
            </div>

            <DialogFooter>
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
