import { router } from '@inertiajs/react';
import { Pencil, Tag as TagIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import TagController from '@/actions/App/Http/Controllers/Catalog/TagController';
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

export type TagRow = {
    id: number;
    name: string;
    slug: string;
    productCount: number;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tagToEdit?: TagRow | null;
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

export function TagDialog({ open, onOpenChange, tagToEdit }: Props) {
    const isEditing = Boolean(tagToEdit);
    const formKey = tagToEdit ? `edit-${tagToEdit.id}` : 'create-tag';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isEditing ? (
                            <>
                                <Pencil className="size-5 text-primary" />
                                Etiketi Düzenle
                            </>
                        ) : (
                            <>
                                <TagIcon className="size-5 text-primary" />
                                Yeni Etiket Ekle
                            </>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Etiket adını güncelleyin. Otomatik üretilen slug değeri de güncellenecektir.'
                            : 'Ürünlerinizi etiketleyerek filtrelemeyi ve dışa aktarma işlemlerini kolaylaştırın.'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <TagFormContent
                        key={formKey}
                        tagToEdit={tagToEdit}
                        isEditing={isEditing}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function TagFormContent({
    tagToEdit,
    isEditing,
    onClose,
}: {
    tagToEdit?: TagRow | null;
    isEditing: boolean;
    onClose: () => void;
}) {
    const [name, setName] = useState(tagToEdit?.name ?? '');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{ name?: string; slug?: string }>({});

    const previewSlug = useMemo(() => slugify(name), [name]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        if (isEditing && tagToEdit) {
            router.patch(
                TagController.update.url({ tag: tagToEdit.id }),
                { name },
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
                TagController.store.url(),
                { name },
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
                    <Label htmlFor="tag-name">Etiket Adı</Label>
                    <Input
                        id="tag-name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="Örn: Yeni Sezon, Çok Satan, Organik..."
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
                        <p className="mt-1 text-[11px] text-muted-foreground/80">
                            Filtreleme ve dışa aktarma tanımlamalarında bu kod
                            kullanılır.
                        </p>
                    </div>
                )}
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
