import { Form } from '@inertiajs/react';
import StockController from '@/actions/App/Http/Controllers/Inventory/StockController';
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
import { Textarea } from '@/components/ui/textarea';

export type StockTarget = {
    variantId: number;
    sku: string;
    warehouseId: number;
    warehouseName: string;
    onHand: number;
};

/**
 * Stok duzeltmesi sessiz bir islem degildir: `on_hand` degisimi bir depo
 * operasyonudur, sebep sorulur ve iz birakir (StockController::update). Bu
 * yuzden satir ici duzenleme degil, ayri bir onay adimi.
 */
export function StockAdjustDialog({
    target,
    onClose,
}: {
    target: StockTarget | null;
    onClose: () => void;
}) {
    return (
        <Dialog
            open={target !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {target !== null && (
                    <Form
                        {...StockController.update.form({
                            variant: target.variantId,
                            warehouse: target.warehouseId,
                        })}
                        options={{ preserveScroll: true }}
                        onSuccess={onClose}
                        className="grid gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>Stok düzeltme</DialogTitle>
                                    <DialogDescription>
                                        {target.sku} · {target.warehouseName}.
                                        Düzeltme kaydedilir: kim, ne zaman ve
                                        neden değiştirdi.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="grid gap-2">
                                    <Label htmlFor="on_hand">Eldeki stok</Label>
                                    <Input
                                        id="on_hand"
                                        name="on_hand"
                                        type="number"
                                        min="0"
                                        step="1"
                                        required
                                        autoFocus
                                        defaultValue={target.onHand}
                                        className="tabular-nums"
                                    />
                                    <InputError message={errors.on_hand} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Sebep</Label>
                                    <Textarea
                                        id="reason"
                                        name="reason"
                                        required
                                        rows={2}
                                        placeholder="Sayım farkı, hasarlı ürün, iade girişi…"
                                    />
                                    <InputError message={errors.reason} />
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={onClose}
                                    >
                                        Vazgeç
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        Kaydet
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
