import { Form, Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Catalog/CategoryController';
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
import { usePermission } from '@/hooks/use-permission';
import { destroy, index, update } from '@/routes/categories';

type CategoryRow = {
    id: number;
    name: string;
    parentId: number | null;
    depth: number;
};

/** Radix Select bos deger kabul etmez; kok kategori bu sabitle temsil edilir. */
const ROOT = 'root';

export default function CategoryIndex({
    categories,
}: {
    categories: CategoryRow[];
}) {
    const canManage = usePermission()('catalog.manage');
    const [parentId, setParentId] = useState<number | null>(null);
    const [pendingDelete, setPendingDelete] = useState<CategoryRow | null>(
        null,
    );

    const rename = (category: CategoryRow, name: string): void => {
        if (name.trim() === '' || name === category.name) {
            return;
        }

        router.patch(
            update.url({ category: category.id }),
            { name },
            { preserveScroll: true, onError: toastError },
        );
    };

    return (
        <>
            <Head title="Kategoriler" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Kategoriler"
                    description="Kendi kategori ağacınız; pazaryeri kategorilerine buradan eşlenir."
                />

                <Form
                    {...CategoryController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="flex max-w-2xl items-end gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="name">Yeni kategori</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="Kategori adı"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Üst kategori</Label>
                                <input
                                    type="hidden"
                                    name="parent_id"
                                    value={parentId ?? ''}
                                />
                                <Select
                                    value={
                                        parentId === null
                                            ? ROOT
                                            : String(parentId)
                                    }
                                    onValueChange={(value) =>
                                        setParentId(
                                            value === ROOT
                                                ? null
                                                : Number(value),
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="w-56"
                                        aria-label="Üst kategori"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={ROOT}>
                                            Kök kategori
                                        </SelectItem>
                                        {categories.map((category) => (
                                            <SelectItem
                                                key={category.id}
                                                value={String(category.id)}
                                            >
                                                {'— '.repeat(category.depth) +
                                                    category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.parent_id} />
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

                {categories.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border p-8 text-center font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                        Henüz kategori eklenmemiş.
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead className="w-16" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categories.map((category) => (
                                    <TableRow key={category.id}>
                                        <TableCell>
                                            <div
                                                style={{
                                                    paddingLeft: `${category.depth * 1.5}rem`,
                                                }}
                                            >
                                                <Input
                                                    aria-label={`${category.name} adı`}
                                                    defaultValue={category.name}
                                                    disabled={
                                                        !canManage.allowed
                                                    }
                                                    className="h-8 max-w-xs"
                                                    onBlur={(event) =>
                                                        rename(
                                                            category,
                                                            event.currentTarget
                                                                .value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <PermissionButton
                                                check={canManage}
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`${category.name} sil`}
                                                onClick={() =>
                                                    setPendingDelete(category)
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
                        <DialogTitle>Kategori silinsin mi?</DialogTitle>
                        <DialogDescription>
                            {pendingDelete?.name} ve altındaki tüm alt
                            kategoriler silinecek. Ürünler silinmez, kategorisiz
                            kalır.
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
                                        category: pendingDelete.id,
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

CategoryIndex.layout = {
    breadcrumbs: [
        {
            title: 'Kategoriler',
            href: index(),
        },
    ],
};
