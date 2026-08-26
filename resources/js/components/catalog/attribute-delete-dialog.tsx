import { router } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import AttributeController from '@/actions/App/Http/Controllers/Catalog/AttributeController';
import type { AttributeRow } from '@/components/catalog/attribute-dialog';
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
    attribute: AttributeRow | null;
    onClose: () => void;
};

export function AttributeDeleteDialog({ attribute, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    const handleDelete = () => {
        if (!attribute) {
            return;
        }

        setProcessing(true);
        router.delete(
            AttributeController.destroy.url({ attribute: attribute.id }),
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
            open={attribute !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <TriangleAlert className="size-5" />
                        Niteliği Sil
                    </DialogTitle>
                    <DialogDescription>
                        <strong className="text-foreground">
                            "{attribute?.name}"
                        </strong>{' '}
                        niteliğini ve buna bağlı{' '}
                        <strong className="font-mono text-foreground">
                            {attribute?.valuesCount ?? 0}
                        </strong>{' '}
                        adet değeri silmek üzeresiniz. Bu işlem geri alınamaz.
                    </DialogDescription>
                </DialogHeader>

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
                        {processing ? 'Siliniyor...' : 'Evet, Niteliği Sil'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
