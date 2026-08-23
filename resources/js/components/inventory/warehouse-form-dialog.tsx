import { Form } from '@inertiajs/react';
import { Building2, MapPin } from 'lucide-react';
import WarehouseController from '@/actions/App/Http/Controllers/Inventory/WarehouseController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

export type WarehouseFormData = {
    id: number;
    name: string;
    code: string;
    isDefault: boolean;
    address: {
        line: string | null;
        city: string | null;
        district: string | null;
        postalCode: string | null;
    };
    itemCount: number;
    onHandTotal: number;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    warehouse?: WarehouseFormData | null;
};

export function WarehouseFormDialog({
    open,
    onOpenChange,
    warehouse = null,
}: Props) {
    const isEditing = warehouse !== null;

    const formProps = isEditing
        ? WarehouseController.update.form({ warehouse: warehouse.id })
        : WarehouseController.store.form();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-lg">
                <Form
                    {...formProps}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={!isEditing}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2 text-lg">
                                    <Building2 className="size-5 text-muted-foreground" />
                                    {isEditing
                                        ? 'Depoyu Düzenle'
                                        : 'Yeni Depo Tanımla'}
                                </DialogTitle>
                                <DialogDescription>
                                    {isEditing
                                        ? 'Depo bilgilerini ve konum detaylarını güncelleyin.'
                                        : 'Stok takibi ve sipariş operasyonları için yeni bir depo tanımlayın.'}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4">
                                {/* Temel Bilgiler */}
                                <div className="space-y-3">
                                    <div className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Temel Bilgiler
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="warehouse-name">
                                                Depo Adı{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="warehouse-name"
                                                name="name"
                                                required
                                                autoFocus
                                                defaultValue={
                                                    warehouse?.name ?? ''
                                                }
                                                placeholder="Örn: Ana Depo, Kadıköy Şube"
                                            />
                                            <InputError
                                                message={errors.name}
                                            />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="warehouse-code">
                                                Depo Kodu{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="warehouse-code"
                                                name="code"
                                                required
                                                defaultValue={
                                                    warehouse?.code ?? ''
                                                }
                                                placeholder="Örn: WH-01, DEP-MRKZ"
                                                className="font-mono tabular-nums uppercase"
                                            />
                                            <InputError
                                                message={errors.code}
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Adres Bilgileri */}
                                <div className="space-y-3 pt-2">
                                    <div className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        <MapPin className="size-3.5" />
                                        Adres & Konum
                                    </div>

                                    <div className="grid gap-3">
                                        <div className="grid gap-1.5">
                                            <Label htmlFor="warehouse-address-line">
                                                Açık Adres
                                            </Label>
                                            <Input
                                                id="warehouse-address-line"
                                                name="address[line]"
                                                defaultValue={
                                                    warehouse?.address?.line ??
                                                    ''
                                                }
                                                placeholder="Cadde, sokak, bina ve kapı no..."
                                            />
                                            <InputError
                                                message={errors['address.line']}
                                            />
                                        </div>

                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="warehouse-address-city">
                                                    İl
                                                </Label>
                                                <Input
                                                    id="warehouse-address-city"
                                                    name="address[city]"
                                                    defaultValue={
                                                        warehouse?.address
                                                            ?.city ?? ''
                                                    }
                                                    placeholder="Örn: İstanbul"
                                                />
                                                <InputError
                                                    message={
                                                        errors['address.city']
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-1.5">
                                                <Label htmlFor="warehouse-address-district">
                                                    İlçe
                                                </Label>
                                                <Input
                                                    id="warehouse-address-district"
                                                    name="address[district]"
                                                    defaultValue={
                                                        warehouse?.address
                                                            ?.district ?? ''
                                                    }
                                                    placeholder="Örn: Kadıköy"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            'address.district'
                                                        ]
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-1.5">
                                                <Label htmlFor="warehouse-address-postal-code">
                                                    Posta Kodu
                                                </Label>
                                                <Input
                                                    id="warehouse-address-postal-code"
                                                    name="address[postal_code]"
                                                    defaultValue={
                                                        warehouse?.address
                                                            ?.postalCode ?? ''
                                                    }
                                                    placeholder="34710"
                                                    className="font-mono tabular-nums"
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            'address.postal_code'
                                                        ]
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Varsayılan Durumu */}
                                <div className="rounded-lg border border-border bg-muted/30 p-3 pt-2.5">
                                    {isEditing && warehouse.isDefault ? (
                                        <div className="space-y-1">
                                            <input
                                                type="hidden"
                                                name="is_default"
                                                value="1"
                                            />
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    Varsayılan Depo
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                Bu depo şu anda varsayılan
                                                depodur. Varsayılanlık, başka bir
                                                depo varsayılan seçilerek
                                                aktarılabilir.
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-1.5">
                                            <label className="flex items-start gap-2.5 cursor-pointer text-sm font-medium">
                                                <Checkbox
                                                    name="is_default"
                                                    value="1"
                                                    className="mt-0.5"
                                                    defaultChecked={
                                                        warehouse?.isDefault ??
                                                        false
                                                    }
                                                />
                                                <div>
                                                    <div>
                                                        Varsayılan depo olarak
                                                        ayarla
                                                    </div>
                                                    <p className="text-xs font-normal text-muted-foreground">
                                                        Katalog ve sipariş
                                                        işlemlerinde öncelikli
                                                        olarak bu depo
                                                        kullanılır.
                                                    </p>
                                                </div>
                                            </label>
                                            <InputError
                                                message={errors.is_default}
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>

                            <DialogFooter className="gap-2 sm:gap-0">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Vazgeç
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Kaydediliyor…'
                                        : isEditing
                                          ? 'Değişiklikleri Kaydet'
                                          : 'Depoyu Ekle'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
