import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import TagController from '@/actions/App/Http/Controllers/Catalog/TagController';
import type { TagRow } from '@/components/catalog/tag-dialog';
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
    tag: TagRow | null;
    onClose: () => void;
};

export function TagDeleteDialog({ tag, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    if (!tag) {
        return null;
    }

    const handleDelete = () => {
        setProcessing(true);
        router.delete(TagController.destroy.url({ tag: tag.id }), {
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
        <Dialog open={tag !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <Trash2 className="size-5" />
                        Etiket Silinsin mi?
                    </DialogTitle>
                    <DialogDescription>
                        <strong className="font-medium text-foreground">
                            "{tag.name}"
                        </strong>{' '}
                        etiketini silmek üzeresiniz.
                    </DialogDescription>
                </DialogHeader>

                <div className="py-2">
                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                        {tag.productCount > 0 ? (
                            <p>
                                Bu etikete bağlı{' '}
                                <strong className="font-mono text-foreground tabular-nums">
                                    {tag.productCount} adet ürün
                                </strong>{' '}
                                silinmez; yalnızca bu etiket ürünlerden
                                kaldırılır.
                            </p>
                        ) : (
                            <p>
                                Bu etikete bağlı herhangi bir ürün bulunmuyor.
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
                        {processing ? 'Siliniyor...' : 'Evet, Etiketi Sil'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
