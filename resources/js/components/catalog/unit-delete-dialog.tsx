import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import UnitController from '@/actions/App/Http/Controllers/Catalog/UnitController';
import { toastError } from '@/components/catalog/toast-error';
import type { UnitRow } from '@/components/catalog/unit-dialog';
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
    unit: UnitRow | null;
    onClose: () => void;
};

export function UnitDeleteDialog({ unit, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    if (!unit) {
        return null;
    }

    const handleDelete = () => {
        setProcessing(true);
        router.delete(UnitController.destroy.url({ unit: unit.id }), {
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
            open={unit !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <Trash2 className="size-5" />
                        Ürün Birimi Silinsin mi?
                    </DialogTitle>
                    <DialogDescription>
                        <strong className="font-medium text-foreground">
                            "{unit.name}" ({unit.shortName})
                        </strong>{' '}
                        birimini silmek üzeresiniz.
                    </DialogDescription>
                </DialogHeader>

                <div className="py-2">
                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                        {unit.productCount > 0 ? (
                            <p>
                                Bu birime bağlı{' '}
                                <strong className="font-mono text-foreground tabular-nums">
                                    {unit.productCount} adet ürün
                                </strong>{' '}
                                silinmez; ürünlerin birim tanımı boşa çıkarılır.
                            </p>
                        ) : (
                            <p>Bu birime bağlı herhangi bir ürün bulunmuyor.</p>
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
                        {processing ? 'Siliniyor...' : 'Evet, Birimi Sil'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
