import { router } from '@inertiajs/react';
import { AlertTriangle, ShieldAlert } from 'lucide-react';
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
import { destroy } from '@/routes/warehouses';
import type { WarehouseFormData } from './warehouse-form-dialog';

type Props = {
    warehouse: WarehouseFormData | null;
    totalWarehousesCount: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function WarehouseDeleteDialog({
    warehouse,
    totalWarehousesCount,
    open,
    onOpenChange,
}: Props) {
    if (!warehouse) {
        return null;
    }

    const isDefault = warehouse.isDefault;
    const hasStock = warehouse.onHandTotal > 0;
    const isLastWarehouse = totalWarehousesCount <= 1;
    const canDelete = !isDefault && !hasStock && !isLastWarehouse;

    const handleDelete = () => {
        router.delete(destroy.url({ warehouse: warehouse.id }), {
            preserveScroll: true,
            onError: toastError,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-destructive">
                        {canDelete ? (
                            <AlertTriangle className="size-5" />
                        ) : (
                            <ShieldAlert className="size-5 text-amber-500" />
                        )}
                        {canDelete ? 'Depo Silme Onayı' : 'Depo Silinemez'}
                    </DialogTitle>
                    <DialogDescription className="pt-2 text-foreground/90">
                        {isDefault ? (
                            <span>
                                <strong className="font-semibold text-foreground">
                                    {warehouse.name}
                                </strong>{' '}
                                şu anda <strong>varsayılan depo</strong> olarak
                                ayarlanmış. Varsayılan depo silinemez. Silmek
                                için öncelikle başka bir depoyu varsayılan
                                yapmalısınız.
                            </span>
                        ) : isLastWarehouse ? (
                            <span>
                                Sistemde stok ve sipariş takibinin
                                sürdürülebilmesi için en az bir depo bulunmak
                                zorundadır. Son depo silinemez.
                            </span>
                        ) : hasStock ? (
                            <span>
                                <strong className="font-semibold text-foreground">
                                    {warehouse.name}
                                </strong>{' '}
                                deposunda{' '}
                                <strong className="font-mono text-destructive tabular-nums">
                                    {warehouse.onHandTotal}
                                </strong>{' '}
                                adet fiziksel stok (
                                <span className="font-mono tabular-nums">
                                    {warehouse.itemCount}
                                </span>{' '}
                                varyant) bulunmaktadır. Veri kaybını önlemek
                                amacıyla stoğu olan depolar silinemez. Lütfen
                                önce stokları sıfırlayın veya başka depoya
                                aktarın.
                            </span>
                        ) : (
                            <span>
                                <strong className="font-semibold text-foreground">
                                    {warehouse.name}
                                </strong>{' '}
                                (
                                <span className="font-mono text-xs tabular-nums">
                                    {warehouse.code}
                                </span>
                                ) deposunu silmek istediğinize emin misiniz? Bu
                                işlem geri alınamaz.
                            </span>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2 pt-3 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {canDelete ? 'Vazgeç' : 'Kapat'}
                    </Button>
                    {canDelete && (
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleDelete}
                        >
                            Depoyu Sil
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
