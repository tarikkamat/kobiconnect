import { router } from '@inertiajs/react';
import { FolderPlus, Pencil } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Catalog/CategoryController';
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

export type CategoryRow = {
    id: number;
    name: string;
    parentId: number | null;
    depth: number;
    path: string;
    productCount: number;
};

const ROOT = 'root';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categories: CategoryRow[];
    categoryToEdit?: CategoryRow | null;
    defaultParentId?: number | null;
};

export function CategoryDialog({
    open,
    onOpenChange,
    categories,
    categoryToEdit,
    defaultParentId = null,
}: Props) {
    const isEditing = Boolean(categoryToEdit);
    const parentCategoryName = defaultParentId
        ? categories.find((c) => c.id === defaultParentId)?.name
        : null;

    const formKey = categoryToEdit
        ? `edit-${categoryToEdit.id}`
        : `create-${defaultParentId ?? 'root'}`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[480px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isEditing ? (
                            <>
                                <Pencil className="size-5 text-primary" />
                                Kategoriyi Düzenle
                            </>
                        ) : (
                            <>
                                <FolderPlus className="size-5 text-primary" />
                                {parentCategoryName
                                    ? `"${parentCategoryName}" İçin Alt Kategori`
                                    : 'Yeni Kategori Ekle'}
                            </>
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? 'Kategori adını güncelleyin.'
                            : parentCategoryName
                              ? `"${parentCategoryName}" kategorisinin altına yeni bir alt dal ekleyin.`
                              : 'Ürünlerinizi gruplamak ve pazaryerleriyle eşleştirmek için kategori oluşturun.'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <CategoryFormContent
                        key={formKey}
                        categories={categories}
                        categoryToEdit={categoryToEdit}
                        defaultParentId={defaultParentId}
                        isEditing={isEditing}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function CategoryFormContent({
    categories,
    categoryToEdit,
    defaultParentId,
    isEditing,
    onClose,
}: {
    categories: CategoryRow[];
    categoryToEdit?: CategoryRow | null;
    defaultParentId: number | null;
    isEditing: boolean;
    onClose: () => void;
}) {
    const [name, setName] = useState(categoryToEdit?.name ?? '');
    const [parentId, setParentId] = useState<number | null>(
        categoryToEdit ? categoryToEdit.parentId : defaultParentId,
    );
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<{ name?: string; parent_id?: string }>(
        {},
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        if (isEditing && categoryToEdit) {
            router.patch(
                CategoryController.update.url({ category: categoryToEdit.id }),
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
                CategoryController.store.url(),
                {
                    name,
                    parent_id: parentId ?? undefined,
                },
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
                    <Label htmlFor="category-name">Kategori Adı</Label>
                    <Input
                        id="category-name"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="Örn: Akıllı Telefonlar, Tişört..."
                        autoFocus
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                {!isEditing && (
                    <div className="grid gap-2">
                        <Label htmlFor="parent-category">Üst Kategori</Label>
                        <Select
                            value={parentId === null ? ROOT : String(parentId)}
                            onValueChange={(val) =>
                                setParentId(val === ROOT ? null : Number(val))
                            }
                        >
                            <SelectTrigger
                                id="parent-category"
                                aria-label="Üst kategori seçimi"
                            >
                                <SelectValue placeholder="Kök kategori (Ana Dal)" />
                            </SelectTrigger>
                            <SelectContent className="max-h-64">
                                <SelectItem value={ROOT}>
                                    📁 Kök Kategori (Ana Dal)
                                </SelectItem>
                                {categories.map((cat) => (
                                    <SelectItem
                                        key={cat.id}
                                        value={String(cat.id)}
                                    >
                                        {'\u00A0'.repeat(cat.depth * 4) +
                                            (cat.depth > 0 ? '└─ ' : '') +
                                            cat.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            Kök kategori seçilirse en üst seviyede ana kategori
                            oluşturulur.
                        </p>
                        <InputError message={errors.parent_id} />
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
