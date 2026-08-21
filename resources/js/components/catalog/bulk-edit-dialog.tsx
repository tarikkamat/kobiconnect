import { router, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Catalog/ProductController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PermissionCheck } from '@/hooks/use-permission';

type Form = {
    product_ids: number[];
    field: 'price' | 'stock';
    mode: 'set' | 'percent' | 'amount';
    value: string;
};

type Preview = {
    affected: number;
    samples: { sku: string; current: string; next: string }[];
};

const MODES: { value: Form['mode']; label: string }[] = [
    { value: 'set', label: 'Şu değere ayarla' },
    { value: 'percent', label: 'Yüzde değiştir (%)' },
    { value: 'amount', label: 'Tutar/adet ekle' },
];

/**
 * Toplu fiyat/stok — FRONTEND-PLAN §4.5.
 *
 * Uygulamadan ONCE kac satirin degisecegi ve ornek sonuc gosterilir; onizleme
 * ile yazma AYNI hesaptan cikar (BulkEditVariants::plan). Onizleme sayfa
 * gezinmesi olmadan `useHttp` ile alinir.
 *
 * ponytail: CSV/XLSX ice aktarma YOK. Dosya yukleme + sutun eslemesi + hata
 * raporu, buradaki dort alanin cozdugu ihtiyaci cozmuyor.
 */
export function BulkEditDialog({
    productIds,
    check,
    onApplied,
}: {
    productIds: number[];
    check: PermissionCheck;
    onApplied: () => void;
}) {
    const [open, setOpen] = useState(false);
    const [preview, setPreview] = useState<Preview | null>(null);

    const { data, setData, post, processing, errors } = useHttp<Form, Preview>({
        product_ids: productIds,
        field: 'price',
        mode: 'set',
        value: '',
    });

    const runPreview = (): void => {
        setData('product_ids', productIds);

        post(ProductController.bulkPreview.url(), {
            onSuccess: (response: Preview) => setPreview(response),
        });
    };

    const apply = (): void => {
        router.post(
            ProductController.bulkUpdate.url(),
            { ...data, product_ids: productIds },
            {
                preserveScroll: true,
                onError: toastError,
                onSuccess: () => {
                    setOpen(false);
                    setPreview(null);
                    onApplied();
                },
            },
        );
    };

    const unit = data.field === 'price' ? 'fiyat' : 'stok';

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setPreview(null);
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Toplu fiyat/stok
                </Button>
            </DialogTrigger>

            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Toplu {unit} değişikliği</DialogTitle>
                    <DialogDescription>
                        Seçili {productIds.length} ürünün bütün varyantlarına
                        uygulanır. Uygulamadan önce önizleyin.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div className="grid gap-2">
                        <Label>Alan</Label>
                        <Select
                            value={data.field}
                            onValueChange={(value) => {
                                // Her alan degisikligi onizlemeyi gecersiz kilar;
                                // eski sayiyla uygulanmasin.
                                setData('field', value as Form['field']);
                                setPreview(null);
                            }}
                        >
                            <SelectTrigger aria-label="Alan">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="price">
                                    Liste fiyatı (TRY)
                                </SelectItem>
                                <SelectItem value="stock">
                                    Stok (varsayılan depo)
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.field} />
                    </div>

                    <div className="grid gap-2">
                        <Label>Değişim türü</Label>
                        <Select
                            value={data.mode}
                            onValueChange={(value) => {
                                setData('mode', value as Form['mode']);
                                setPreview(null);
                            }}
                        >
                            <SelectTrigger aria-label="Değişim türü">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {MODES.map((mode) => (
                                    <SelectItem
                                        key={mode.value}
                                        value={mode.value}
                                    >
                                        {mode.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.mode} />
                        {data.mode !== 'set' && (
                            <p className="text-xs text-muted-foreground">
                                Negatif değer indirim/düşüm anlamına gelir.
                                Mevcut {unit} kaydı olmayan varyantlar atlanır.
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="bulk-value">Değer</Label>
                        <Input
                            id="bulk-value"
                            type="number"
                            step={data.field === 'price' ? '0.01' : '1'}
                            value={data.value}
                            onChange={(event) => {
                                setData('value', event.target.value);
                                setPreview(null);
                            }}
                        />
                        <InputError message={errors.value} />
                    </div>

                    <Button
                        type="button"
                        variant="secondary"
                        disabled={processing || data.value === ''}
                        onClick={runPreview}
                    >
                        Önizle
                    </Button>

                    {preview !== null && (
                        <div className="rounded-lg border p-3">
                            <p className="text-sm font-medium">
                                {preview.affected} varyant etkilenecek.
                            </p>

                            {preview.affected === 0 ? (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Bu ayarlarla değişecek satır yok.
                                </p>
                            ) : (
                                <Table className="mt-2">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>SKU</TableHead>
                                            <TableHead>Şu an</TableHead>
                                            <TableHead>Sonra</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {preview.samples.map((sample) => (
                                            <TableRow key={sample.sku}>
                                                <TableCell>
                                                    {sample.sku}
                                                </TableCell>
                                                <TableCell className="tabular-nums">
                                                    {sample.current}
                                                </TableCell>
                                                <TableCell className="font-medium tabular-nums">
                                                    {sample.next}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    )}
                </div>

                <DialogFooter>
                    {/* Onizlemeden once uygulanamaz: kac satirin degisecegini
                        gormeden yazmak toplu islemin tam da riskli kismi. */}
                    <PermissionButton
                        check={check}
                        type="button"
                        disabled={
                            processing ||
                            preview === null ||
                            preview.affected === 0
                        }
                        onClick={apply}
                    >
                        Uygula
                    </PermissionButton>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
