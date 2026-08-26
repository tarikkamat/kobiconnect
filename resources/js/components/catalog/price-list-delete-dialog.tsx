import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import PriceListController from '@/actions/App/Http/Controllers/Catalog/PriceListController';
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

export type PriceListRow = {
    id: number;
    name: string;
    type: 'manual' | 'currency' | 'dynamic';
    typeLabel: string;
    sourceCurrency: string;
    targetCurrency: string;
    exchangeRate: number | null;
    roundingMethod: string;
    roundingMethodLabel: string;
    isActive: boolean;
    itemCount: number;
    description: string | null;
    createdAt: string;
};

type Props = {
    priceList: PriceListRow | null;
    onClose: () => void;
};

export function PriceListDeleteDialog({ priceList, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    if (!priceList) {
        return null;
    }

    const handleDelete = () => {
        setProcessing(true);
        router.delete(
            PriceListController.destroy.url({ priceList: priceList.id }),
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
            open={priceList !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-[460px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        <Trash2 className="size-5" />
                        Fiyat Listesi Silinsin mi?
                    </DialogTitle>
                    <DialogDescription>
                        <strong className="font-medium text-foreground">
                            "{priceList.name}"
                        </strong>{' '}
                        fiyat listesini ve bu listeye özel tüm fiyatları silmek
                        üzeresiniz.
                    </DialogDescription>
                </DialogHeader>

                <div className="py-2">
                    <div className="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground">
                        <p>
                            Bu işlem ana ürün fiyatlarını etkilemez; yalnızca bu
                            satış kanalına / para birimine ait özel fiyat
                            listesi silinir.
                        </p>
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
                        {processing
                            ? 'Siliniyor...'
                            : 'Evet, Fiyat Listesini Sil'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
