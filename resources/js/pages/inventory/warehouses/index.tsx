import { Form, Head, router } from '@inertiajs/react';
import { Pencil, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import WarehouseController from '@/actions/App/Http/Controllers/Inventory/WarehouseController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import Heading from '@/components/heading';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import { destroy, index } from '@/routes/warehouses';

type WarehouseRow = {
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

export default function WarehouseIndex({
    warehouses,
}: {
    warehouses: WarehouseRow[];
}) {
    const canManage = usePermission()('stock.manage');
    const [editing, setEditing] = useState<WarehouseRow | null>(null);
    const [pendingDelete, setPendingDelete] = useState<WarehouseRow | null>(
        null,
    );

    return (
        <>
            <Head title="Depolar" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Depolar"
                    description="Stok her zaman bir depoya yazılır. En az bir depo bulunmak zorundadır; varsayılan depo silinemez."
                />

                <Form
                    {...WarehouseController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid max-w-3xl gap-3 rounded-lg border border-border p-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <p className="text-sm font-medium">Yeni depo</p>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Depo adı</Label>
                                    <Input id="name" name="name" required />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="code">Kod</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        required
                                        placeholder="WH-01"
                                        className="font-mono tabular-nums"
                                    />
                                    <InputError message={errors.code} />
                                </div>
                            </div>

                            <AddressFields errors={errors} />

                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox name="is_default" value="1" />
                                Varsayılan depo yap
                            </label>

                            <PermissionButton
                                check={canManage}
                                type="submit"
                                disabled={processing}
                                className="justify-self-start"
                            >
                                Depo ekle
                            </PermissionButton>
                        </>
                    )}
                </Form>

                {warehouses.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border p-8 text-center font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                        Henüz depo tanımlanmamış. Stok girebilmek için en az bir
                        depo gerekir.
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Depo</TableHead>
                                    <TableHead>Kod</TableHead>
                                    <TableHead>Adres</TableHead>
                                    <TableHead className="text-right">
                                        Varyant
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Eldeki
                                    </TableHead>
                                    <TableHead className="w-24" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {warehouses.map((warehouse) => (
                                    <TableRow key={warehouse.id}>
                                        <TableCell>
                                            <span className="flex items-center gap-2">
                                                {warehouse.name}
                                                {warehouse.isDefault && (
                                                    <Badge variant="secondary">
                                                        <Star />
                                                        Varsayılan
                                                    </Badge>
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground tabular-nums">
                                            {warehouse.code}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {[
                                                warehouse.address.line,
                                                warehouse.address.district,
                                                warehouse.address.city,
                                            ]
                                                .filter(Boolean)
                                                .join(', ') || '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {warehouse.itemCount}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {warehouse.onHandTotal}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                <PermissionButton
                                                    check={canManage}
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`${warehouse.name} düzenle`}
                                                    onClick={() =>
                                                        setEditing(warehouse)
                                                    }
                                                >
                                                    <Pencil />
                                                </PermissionButton>
                                                <PermissionButton
                                                    check={canManage}
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`${warehouse.name} sil`}
                                                    onClick={() =>
                                                        setPendingDelete(
                                                            warehouse,
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </PermissionButton>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    {editing !== null && (
                        <Form
                            {...WarehouseController.update.form({
                                warehouse: editing.id,
                            })}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Depoyu düzenle
                                        </DialogTitle>
                                        <DialogDescription>
                                            {editing.isDefault
                                                ? 'Bu varsayılan depo. Varsayılanlığı ancak başka bir depoyu varsayılan yaparak taşıyabilirsiniz.'
                                                : 'Depo bilgilerini güncelleyin.'}
                                        </DialogDescription>
                                    </DialogHeader>

                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-name">
                                            Depo adı
                                        </Label>
                                        <Input
                                            id="edit-name"
                                            name="name"
                                            required
                                            defaultValue={editing.name}
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-code">Kod</Label>
                                        <Input
                                            id="edit-code"
                                            name="code"
                                            required
                                            defaultValue={editing.code}
                                            className="font-mono tabular-nums"
                                        />
                                        <InputError message={errors.code} />
                                    </div>

                                    <AddressFields
                                        errors={errors}
                                        prefix="edit-"
                                        values={editing.address}
                                    />

                                    {editing.isDefault ? (
                                        /*
                                         * Varsayilan depoda bayrak devre disi bir
                                         * kutu OLAMAZ: devre disi girdi form ile
                                         * gonderilmez, sunucu bunu "varsayilanligi
                                         * kaldir" sanip her duzenlemeyi reddederdi.
                                         * Bayrak gizli alanla aynen geri gonderilir.
                                         */
                                        <input
                                            type="hidden"
                                            name="is_default"
                                            value="1"
                                        />
                                    ) : (
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                name="is_default"
                                                value="1"
                                            />
                                            Varsayılan depo yap
                                        </label>
                                    )}
                                    <InputError message={errors.is_default} />

                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setEditing(null)}
                                        >
                                            Vazgeç
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Kaydet
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Depo silinsin mi?</DialogTitle>
                        <DialogDescription>
                            {pendingDelete?.name} silinecek. Bu depodaki{' '}
                            <span className="font-mono tabular-nums">
                                {pendingDelete?.itemCount ?? 0}
                            </span>{' '}
                            envanter satırı da silinir; stoğu olan depo
                            silinemez.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPendingDelete(null)}
                        >
                            Vazgeç
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (pendingDelete === null) {
                                    return;
                                }

                                router.delete(
                                    destroy.url({
                                        warehouse: pendingDelete.id,
                                    }),
                                    {
                                        preserveScroll: true,
                                        onError: toastError,
                                    },
                                );
                                setPendingDelete(null);
                            }}
                        >
                            Sil
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function AddressFields({
    errors,
    prefix = '',
    values,
}: {
    errors: Record<string, string>;
    prefix?: string;
    values?: WarehouseRow['address'];
}) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${prefix}address-line`}>Adres</Label>
                <Input
                    id={`${prefix}address-line`}
                    name="address[line]"
                    defaultValue={values?.line ?? ''}
                />
                <InputError message={errors['address.line']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}address-district`}>İlçe</Label>
                <Input
                    id={`${prefix}address-district`}
                    name="address[district]"
                    defaultValue={values?.district ?? ''}
                />
                <InputError message={errors['address.district']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}address-city`}>İl</Label>
                <Input
                    id={`${prefix}address-city`}
                    name="address[city]"
                    defaultValue={values?.city ?? ''}
                />
                <InputError message={errors['address.city']} />
            </div>
        </div>
    );
}

WarehouseIndex.layout = {
    breadcrumbs: [
        {
            title: 'Depolar',
            href: index(),
        },
    ],
};
