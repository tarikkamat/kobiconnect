import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import BrandController from '@/actions/App/Http/Controllers/Catalog/BrandController';
import type { BrandRow } from '@/components/catalog/brand-dialog';
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
    brand: BrandRow | null;
    onClose: () => void;
};

export function BrandDeleteDialog({ brand, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    if (!brand) {
        return null;
    }

    const handleDelete = () => {
        setProcessing(true);
        router.delete(BrandController.destroy.url({ brand: brand.id }), {
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                onClose();
            },
            onError: (errs) => {
                setProcessing(false);
                toastError(errs);
            },
        });
    };

    return (
        <Dialog
            open={brand !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <Trash2 className="size-5" />
                        Marka Silinsin mi?
                    </DialogTitle>
                    <DialogDescription>
                        <strong className="font-medium text-foreground">
                            "{brand.name}"
                        </strong>{' '}
                        markasını silmek üzeresiniz.
                    </DialogDescription>
                </DialogHeader>

                <div className="py-2">
                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                        {brand.productCount > 0 ? (
                            <p>
                                Bu markaya bağlı{' '}
                                <strong className="font-mono text-foreground tabular-nums">
                                    {brand.productCount} adet ürün
                                </strong>{' '}
                                silinmez; yalnızca markasız olarak listelenmeye
                                devam eder.
                            </p>
                        ) : (
                            <p>
                                Bu markaya bağlı herhangi bir ürün bulunmuyor.
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
                        {processing ? 'Siliniyor...' : 'Evet, Markayı Sil'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
