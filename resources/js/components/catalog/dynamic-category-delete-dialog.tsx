import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import DynamicCategoryController from '@/actions/App/Http/Controllers/Catalog/DynamicCategoryController';
import type { DynamicCategoryRow } from '@/components/catalog/dynamic-category-dialog';
import { toastError } from '@/components/catalog/toast-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    category: DynamicCategoryRow | null;
    onClose: () => void;
};

export function DynamicCategoryDeleteDialog({ category, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    if (!category) {
        return null;
    }

    const handleDelete = () => {
        setProcessing(true);
        router.delete(
            DynamicCategoryController.destroy.url({
                dynamicCategory: category.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    onClose();
                },
                onError: (errs) => {
                    setProcessing(false);
                    toastError(errs);
                },
            },
        );
    };

    return (
        <Dialog
            open={category !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <Trash2 className="size-5" />
                        Dinamik Kategori Silinsin mi?
                    </DialogTitle>
                    <DialogDescription>
                        <strong className="font-medium text-foreground">
                            "{category.name}"
                        </strong>{' '}
                        dinamik kategorisini silmek üzeresiniz.
                    </DialogDescription>
                </DialogHeader>

                <div className="py-2">
                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                        {category.productCount > 0 ? (
                            <p>
                                Bu dinamik kategoriye bağlı{' '}
                                <strong className="font-mono text-foreground tabular-nums">
                                    {category.productCount} adet ürün
                                </strong>{' '}
                                silinmez; yalnızca bu dinamik kategoriden
                                çıkarılır.
                            </p>
                        ) : (
                            <p>
                                Bu kategoriye bağlı herhangi bir ürün
                                bulunmuyor.
                            </p>
                        )}
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Vazgeç
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={handleDelete}
                        disabled={processing}
                    >
                        {processing ? 'Siliniyor...' : 'Evet, Kategoriyi Sil'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
