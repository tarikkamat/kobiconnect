import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import ProductGroupController from '@/actions/App/Http/Controllers/Catalog/ProductGroupController';
import type { ProductGroupRow } from '@/components/catalog/product-group-dialog';
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
    group: ProductGroupRow | null;
    onClose: () => void;
};

export function ProductGroupDeleteDialog({ group, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    if (!group) {
        return null;
    }

    const handleDelete = () => {
        setProcessing(true);
        router.delete(
            ProductGroupController.destroy.url({ productGroup: group.id }),
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
            open={group !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <Trash2 className="size-5" />
                        Ürün Grubu Silinsin mi?
                    </DialogTitle>
                    <DialogDescription>
                        <strong className="font-medium text-foreground">
                            "{group.name}"
                        </strong>{' '}
                        ürün grubunu silmek üzeresiniz.
                    </DialogDescription>
                </DialogHeader>

                <div className="py-2">
                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                        {group.productCount > 0 ? (
                            <p>
                                Bu gruba bağlı{' '}
                                <strong className="font-mono text-foreground tabular-nums">
                                    {group.productCount} adet ürün
                                </strong>{' '}
                                silinmez; yalnızca bu gruptan çıkarılır.
                            </p>
                        ) : (
                            <p>Bu gruba bağlı herhangi bir ürün bulunmuyor.</p>
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
                        {processing ? 'Siliniyor...' : 'Evet, Grubu Sil'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
