import { Form, Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import BrandController from '@/actions/App/Http/Controllers/Catalog/BrandController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import Heading from '@/components/heading';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import { destroy, index, update } from '@/routes/brands';

type BrandRow = {
    id: number;
    name: string;
    slug: string;
    productCount: number;
};

export default function BrandIndex({ brands }: { brands: BrandRow[] }) {
    const canManage = usePermission()('catalog.manage');
    const [pendingDelete, setPendingDelete] = useState<BrandRow | null>(null);

    const rename = (brand: BrandRow, name: string): void => {
        if (name.trim() === '' || name === brand.name) {
            return;
        }

        router.patch(
            update.url({ brand: brand.id }),
            { name },
            { preserveScroll: true, onError: toastError },
        );
    };

    return (
        <>
            <Head title="Markalar" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Markalar"
                    description="Pazaryeri marka eşlemesi bu listeye dayanır."
                />

                <Form
                    {...BrandController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex max-w-xl items-end gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="name">Yeni marka</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="Marka adı"
                                />
                                <InputError
                                    message={errors.name ?? errors.slug}
                                />
                            </div>
                            <PermissionButton
                                check={canManage}
                                type="submit"
                                disabled={processing}
                            >
                                Ekle
                            </PermissionButton>
                        </>
                    )}
                </Form>

                {brands.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border p-8 text-center font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                        Henüz marka eklenmemiş.
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Marka</TableHead>
                                    <TableHead>Slug</TableHead>
                                    <TableHead className="text-right">
                                        Ürün
                                    </TableHead>
                                    <TableHead className="w-16" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {brands.map((brand) => (
                                    <TableRow key={brand.id}>
                                        <TableCell>
                                            <Input
                                                aria-label={`${brand.name} adı`}
                                                defaultValue={brand.name}
                                                disabled={!canManage.allowed}
                                                className="h-8 max-w-xs"
                                                onBlur={(event) =>
                                                    rename(
                                                        brand,
                                                        event.currentTarget
                                                            .value,
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="font-mono text-muted-foreground tabular-nums">
                                            {brand.slug}
                                        </TableCell>
                                        <TableCell className="text-right font-mono tabular-nums">
                                            {brand.productCount}
                                        </TableCell>
                                        <TableCell>
                                            <PermissionButton
                                                check={canManage}
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`${brand.name} sil`}
                                                onClick={() =>
                                                    setPendingDelete(brand)
                                                }
                                            >
                                                <Trash2 />
                                            </PermissionButton>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(open) => !open && setPendingDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Marka silinsin mi?</DialogTitle>
                        <DialogDescription>
                            {pendingDelete?.name} silinecek. Bu markaya bağlı
                            ürünler silinmez, markasız kalır.
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
                                    destroy.url({ brand: pendingDelete.id }),
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

BrandIndex.layout = {
    breadcrumbs: [
        {
            title: 'Markalar',
            href: index(),
        },
    ],
};
