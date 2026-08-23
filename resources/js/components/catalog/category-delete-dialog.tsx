import { router } from '@inertiajs/react';
import { AlertTriangle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Catalog/CategoryController';
import type { CategoryRow } from '@/components/catalog/category-dialog';
import { toastError } from '@/components/catalog/toast-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    category: CategoryRow | null;
    categories: CategoryRow[];
    onClose: () => void;
};

export function CategoryDeleteDialog({ category, categories, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    if (!category) {
        return null;
    }

    // Subcategories count whose path starts with this category path + '/'
    const subcategoryCount = categories.filter((c) =>
        c.path.startsWith(`${category.path}/`),
    ).length;

    // Total products in this category and all descendants
    const affectedProductsCount =
        category.productCount +
        categories
            .filter((c) => c.path.startsWith(`${category.path}/`))
            .reduce((sum, c) => sum + c.productCount, 0);

    const handleDelete = () => {
        setProcessing(true);
        router.delete(
            CategoryController.destroy.url({ category: category.id }),
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
                        Kategori Silinsin mi?
                    </DialogTitle>
                    <DialogDescription>
                        <strong className="font-medium text-foreground">
                            "{category.name}"
                        </strong>{' '}
                        kategorisini silmek üzeresiniz.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3 py-2">
                    {subcategoryCount > 0 && (
                        <Alert variant="destructive">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>Alt Kategoriler Silinecek</AlertTitle>
                            <AlertDescription>
                                Bu kategoriye bağlı{' '}
                                <strong className="font-mono tabular-nums">
                                    {subcategoryCount} adet alt kategori
                                </strong>{' '}
                                de hiyerarşiden tamamen silinecektir.
                            </AlertDescription>
                        </Alert>
                    )}

                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                        {affectedProductsCount > 0 ? (
                            <p>
                                Bu kategorideki{' '}
                                <strong className="font-mono text-foreground tabular-nums">
                                    {affectedProductsCount} adet ürün
                                </strong>{' '}
                                silinmez; yalnızca kategorisiz olarak
                                listelenmeye devam eder.
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
