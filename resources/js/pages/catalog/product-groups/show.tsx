import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Layers, Package, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ProductGroupController from '@/actions/App/Http/Controllers/Catalog/ProductGroupController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { ProductStatusBadge } from '@/components/catalog/product-status-badge';
import { toastError } from '@/components/catalog/toast-error';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

type GroupData = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    createdAt: string;
};

type GroupProduct = {
    id: number;
    name: string;
    status: string;
    statusLabel: string;
    brand: string | null;
    category: string | null;
    variantCount: number;
    position: number;
};

type AvailableProduct = {
    id: number;
    name: string;
    status: string;
};

export default function ProductGroupShow({
    group,
    products = [],
    allProducts = [],
}: {
    group: GroupData;
    products: GroupProduct[];
    allProducts: AvailableProduct[];
}) {
    const canManage = usePermission()('catalog.manage');

    const [addDialogOpen, setAddDialogOpen] = useState(false);
    const [selectedProductId, setSelectedProductId] = useState<string>('');
    const [processing, setProcessing] = useState(false);

    // Gruba dahil olmayan ürünler
    const assignedIds = new Set(products.map((p) => p.id));
    const unassignedProducts = allProducts.filter(
        (p) => !assignedIds.has(p.id),
    );

    const handleAddProduct = () => {
        if (!selectedProductId) {
            return;
        }

        setProcessing(true);
        const newIds = [
            ...products.map((p) => p.id),
            parseInt(selectedProductId, 10),
        ];

        router.patch(
            ProductGroupController.update.url({ productGroup: group.id }),
            {
                name: group.name,
                description: group.description,
                product_ids: newIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    setAddDialogOpen(false);
                    setSelectedProductId('');
                },
                onError: (errs) => {
                    setProcessing(false);
                    toastError(errs);
                },
            },
        );
    };

    const handleRemoveProduct = (productId: number) => {
        setProcessing(true);
        const newIds = products
            .filter((p) => p.id !== productId)
            .map((p) => p.id);

        router.patch(
            ProductGroupController.update.url({ productGroup: group.id }),
            {
                name: group.name,
                description: group.description,
                product_ids: newIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                },
                onError: (errs) => {
                    setProcessing(false);
                    toastError(errs);
                },
            },
        );
    };

    return (
        <>
            <Head title={`${group.name} - Ürün Grubu`} />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="-ml-2 gap-1.5"
                    >
                        <Link href={ProductGroupController.index.url()}>
                            <ArrowLeft className="size-4" />
                            Tüm Gruplar
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Layers className="size-5 text-primary" />
                            <Heading
                                title={group.name}
                                description={
                                    group.description ||
                                    'Bu gruptaki ürünler detay sayfasında ve listelemelerde birlikte sunulur.'
                                }
                            />
                        </div>
                        <div className="mt-1.5 flex items-center gap-2 text-xs text-muted-foreground">
                            <span>
                                Slug:{' '}
                                <code className="font-mono text-foreground">
                                    {group.slug}
                                </code>
                            </span>
                            <span>•</span>
                            <span>{products.length} ürün atanmış</span>
                            <span>•</span>
                            <span>Oluşturulma: {group.createdAt}</span>
                        </div>
                    </div>

                    <PermissionButton
                        check={canManage}
                        type="button"
                        onClick={() => setAddDialogOpen(true)}
                        className="gap-1.5 self-start sm:self-auto"
                    >
                        <Plus className="size-4" />
                        Gruba Ürün Ekle
                    </PermissionButton>
                </div>

                {products.length === 0 ? (
                    <EmptyState
                        icon={Package}
                        title="Bu grupta henüz ürün yok"
                        description="Gruba ürün ekleyerek bu grup altında birlikte gösterilmesini sağlayabilirsiniz."
                        action={
                            <PermissionButton
                                check={canManage}
                                type="button"
                                onClick={() => setAddDialogOpen(true)}
                                className="gap-1.5"
                            >
                                <Plus className="size-4" />
                                İlk Ürünü Ekle
                            </PermissionButton>
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        #
                                    </TableHead>
                                    <TableHead>Ürün Adı</TableHead>
                                    <TableHead>Marka</TableHead>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead>Durum</TableHead>
                                    <TableHead className="w-24 text-right">
                                        Varyantlar
                                    </TableHead>
                                    <TableHead className="w-20 pr-4 text-right">
                                        İşlem
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {products.map((p, idx) => (
                                    <TableRow key={p.id}>
                                        <TableCell className="text-center font-mono text-xs text-muted-foreground">
                                            {idx + 1}
                                        </TableCell>
                                        <TableCell>
                                            <span className="font-medium text-foreground">
                                                {p.name}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {p.brand || '-'}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {p.category || '-'}
                                        </TableCell>
                                        <TableCell>
                                            <ProductStatusBadge
                                                status={p.status}
                                                label={p.statusLabel}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm tabular-nums">
                                            {p.variantCount}
                                        </TableCell>
                                        <TableCell className="pr-4 text-right">
                                            <PermissionButton
                                                check={canManage}
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() =>
                                                    handleRemoveProduct(p.id)
                                                }
                                                disabled={processing}
                                                aria-label="Gruptan Çıkar"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </PermissionButton>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            {/* Ürün Ekleme Dialog */}
            <Dialog open={addDialogOpen} onOpenChange={setAddDialogOpen}>
                <DialogContent className="sm:max-w-[480px]">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Plus className="size-5 text-primary" />
                            Gruba Ürün Ekle
                        </DialogTitle>
                        <DialogDescription>
                            "{group.name}" grubuna eklemek istediğiniz ürünü
                            seçin.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-4">
                        {unassignedProducts.length === 0 ? (
                            <p className="text-xs text-muted-foreground">
                                Eklenebilecek başka ürün bulunmuyor. Tüm ürünler
                                zaten bu grupta.
                            </p>
                        ) : (
                            <Select
                                value={selectedProductId}
                                onValueChange={setSelectedProductId}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="Bir ürün seçin..." />
                                </SelectTrigger>
                                <SelectContent className="max-h-60">
                                    {unassignedProducts.map((p) => (
                                        <SelectItem
                                            key={p.id}
                                            value={String(p.id)}
                                        >
                                            {p.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setAddDialogOpen(false)}
                            disabled={processing}
                        >
                            Vazgeç
                        </Button>
                        <Button
                            type="button"
                            onClick={handleAddProduct}
                            disabled={processing || !selectedProductId}
                        >
                            {processing ? 'Ekleniyor...' : 'Gruba Ekle'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
