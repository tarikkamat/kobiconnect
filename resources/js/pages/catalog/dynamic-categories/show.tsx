import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Filter,
    Package,
    Pencil,
    RefreshCw,
    Wand2,
} from 'lucide-react';
import { useState } from 'react';
import DynamicCategoryController from '@/actions/App/Http/Controllers/Catalog/DynamicCategoryController';
import { DynamicCategoryDialog } from '@/components/catalog/dynamic-category-dialog';
import type {
    ConditionRow,
    DynamicCategoryRow,
} from '@/components/catalog/dynamic-category-dialog';
import { PermissionButton } from '@/components/catalog/permission-button';
import { ProductStatusBadge } from '@/components/catalog/product-status-badge';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';

type MatchedProduct = {
    id: number;
    name: string;
    status: string;
    statusLabel: string;
    brand: string | null;
    category: string | null;
    variantCount: number;
};

type Option = { value: string; label: string };
type IdName = { id: number; name: string };

export default function DynamicCategoryShow({
    category,
    products = [],
    fields = [],
    operators = [],
    matchTypes = [],
    brands = [],
    productCategories = [],
    tags = [],
}: {
    category: DynamicCategoryRow & { conditions: ConditionRow[] };
    products: MatchedProduct[];
    fields: Option[];
    operators: Option[];
    matchTypes: Option[];
    brands: IdName[];
    productCategories: IdName[];
    tags: IdName[];
}) {
    const canManage = usePermission()('catalog.manage');

    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [evaluating, setEvaluating] = useState(false);

    const handleReevaluate = () => {
        setEvaluating(true);
        router.post(
            DynamicCategoryController.evaluate.url({
                dynamicCategory: category.id,
            }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setEvaluating(false),
            },
        );
    };

    return (
        <>
            <Head title={`${category.name} - Dinamik Kategori`} />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="-ml-2 gap-1.5"
                    >
                        <Link href={DynamicCategoryController.index.url()}>
                            <ArrowLeft className="size-4" />
                            Tüm Dinamik Kategoriler
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <Wand2 className="size-5 text-primary" />
                            <Heading
                                title={category.name}
                                description={
                                    category.description ||
                                    'Bu dinamik kategoriye uyan ürünler kurallara göre otomatik listelenir.'
                                }
                            />
                        </div>
                        <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span>
                                Slug:{' '}
                                <code className="font-mono text-foreground">
                                    {category.slug}
                                </code>
                            </span>
                            <span>•</span>
                            <Badge
                                variant={
                                    category.matchType === 'all'
                                        ? 'default'
                                        : 'secondary'
                                }
                                className="text-[11px]"
                            >
                                {category.matchType === 'all'
                                    ? 'Tüm Koşullar (VE)'
                                    : 'En Az Biri (VEYA)'}
                            </Badge>
                            <span>•</span>
                            <span>{products.length} ürün eşleşti</span>
                            <span>•</span>
                            <span>Oluşturulma: {category.createdAt}</span>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 self-start sm:self-auto">
                        <PermissionButton
                            check={canManage}
                            type="button"
                            variant="outline"
                            onClick={handleReevaluate}
                            disabled={evaluating}
                            className="gap-1.5"
                        >
                            <RefreshCw
                                className={`size-4 ${evaluating ? 'animate-spin' : ''}`}
                            />
                            Yeniden Değerlendir
                        </PermissionButton>

                        <PermissionButton
                            check={canManage}
                            type="button"
                            onClick={() => setEditDialogOpen(true)}
                            className="gap-1.5"
                        >
                            <Pencil className="size-4" />
                            Düzenle
                        </PermissionButton>
                    </div>
                </div>

                {/* Tanımlı Koşullar Kartı */}
                <div className="space-y-3 rounded-lg border border-border bg-card p-4 shadow-xs">
                    <h3 className="flex items-center gap-2 text-sm font-semibold">
                        <Filter className="size-4 text-primary" />
                        Tanımlı Koşullar ({category.conditions.length})
                    </h3>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {category.conditions.map((cond, idx) => (
                            <div
                                key={idx}
                                className="flex items-center gap-2 rounded-md border border-border bg-muted/30 p-2.5 text-xs"
                            >
                                <span className="font-medium text-foreground">
                                    {cond.fieldLabel || cond.field}
                                </span>
                                <span className="font-mono text-muted-foreground">
                                    {cond.operatorLabel || cond.operator}
                                </span>
                                <Badge
                                    variant="outline"
                                    className="max-w-[150px] truncate font-mono text-xs"
                                >
                                    {String(cond.value ?? '')}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Eşleşen Ürünler Tablosu */}
                <div className="space-y-3">
                    <h3 className="flex items-center gap-2 text-sm font-semibold">
                        <Package className="size-4 text-primary" />
                        Eşleşen Ürünler ({products.length})
                    </h3>

                    {products.length === 0 ? (
                        <EmptyState
                            icon={Package}
                            title="Koşullarla eşleşen ürün bulunamadı"
                            description="Belirlediğiniz koşullara uyan hiçbir ürün bulunmuyor. Koşulları düzenleyebilir veya 'Yeniden Değerlendir' butonunu kullanabilirsiniz."
                            action={
                                <PermissionButton
                                    check={canManage}
                                    type="button"
                                    onClick={() => setEditDialogOpen(true)}
                                    className="gap-1.5"
                                >
                                    <Pencil className="size-4" />
                                    Koşulları Düzenle
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
                                        <TableHead className="w-24 pr-4 text-right">
                                            Varyant Sayısı
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
                                            <TableCell className="pr-4 text-right font-mono text-sm tabular-nums">
                                                {p.variantCount}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </div>

            {/* Düzenleme Modalı */}
            <DynamicCategoryDialog
                open={editDialogOpen}
                onOpenChange={setEditDialogOpen}
                categoryToEdit={category}
                fields={fields}
                operators={operators}
                matchTypes={matchTypes}
                brands={brands}
                productCategories={productCategories}
                tags={tags}
            />
        </>
    );
}
