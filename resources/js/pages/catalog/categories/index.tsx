import { Head } from '@inertiajs/react';
import {
    ChevronsDownUp,
    ChevronsUpDown,
    Folder,
    FolderPlus,
    FolderTree,
    List,
    Package,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { CategoryDeleteDialog } from '@/components/catalog/category-delete-dialog';
import { CategoryDialog } from '@/components/catalog/category-dialog';
import type { CategoryRow } from '@/components/catalog/category-dialog';
import { CategoryTreeNodeItem } from '@/components/catalog/category-tree-node';
import type { CategoryTreeNode } from '@/components/catalog/category-tree-node';
import { PermissionButton } from '@/components/catalog/permission-button';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermission } from '@/hooks/use-permission';
import { index } from '@/routes/categories';

export default function CategoryIndex({
    categories,
}: {
    categories: CategoryRow[];
}) {
    const canManage = usePermission()('catalog.manage');

    const [searchTerm, setSearchTerm] = useState('');
    const [viewMode, setViewMode] = useState<'tree' | 'table'>('tree');
    const [expandedIds, setExpandedIds] = useState<Set<number>>(() => {
        // Varsayılan olarak üst seviye (kök) kategorileri açık getir
        return new Set(
            categories.filter((c) => c.depth === 0).map((c) => c.id),
        );
    });

    // Dialog state'leri
    const [dialogOpen, setDialogOpen] = useState(false);
    const [categoryToEdit, setCategoryToEdit] = useState<CategoryRow | null>(
        null,
    );
    const [defaultParentId, setDefaultParentId] = useState<number | null>(null);
    const [categoryToDelete, setCategoryToDelete] =
        useState<CategoryRow | null>(null);

    // Kategori ID'si ile Kategori nesnesi haritası
    const categoryMap = useMemo(() => {
        const map = new Map<number, CategoryRow>();

        for (const cat of categories) {
            map.set(cat.id, cat);
        }

        return map;
    }, [categories]);

    // Hiyerarşik tam ad yolu haritası (Örn: "Elektronik > Telefon > Kılıf")
    const fullPathMap = useMemo(() => {
        const map = new Map<number, string>();

        for (const cat of categories) {
            const parts: string[] = [];
            const pathIds = cat.path.split('/').map(Number);

            for (const id of pathIds) {
                const item = categoryMap.get(id);

                if (item) {
                    parts.push(item.name);
                }
            }

            map.set(cat.id, parts.join(' › '));
        }

        return map;
    }, [categories, categoryMap]);

    // Ağaç veri yapısını inşa etme
    const { treeData, totalProductsCount, rootCategoriesCount } =
        useMemo(() => {
            const roots: CategoryTreeNode[] = [];
            const treeNodeMap = new Map<number, CategoryTreeNode>();

            let totalProducts = 0;
            let rootCount = 0;

            // Düğümleri hazırla
            for (const cat of categories) {
                totalProducts += cat.productCount;

                if (cat.depth === 0) {
                    rootCount++;
                }

                treeNodeMap.set(cat.id, {
                    ...cat,
                    children: [],
                    totalProductCount: cat.productCount,
                });
            }

            // Hiyerarşiyi bağla
            for (const cat of categories) {
                const node = treeNodeMap.get(cat.id)!;

                if (cat.parentId === null || !treeNodeMap.has(cat.parentId)) {
                    roots.push(node);
                } else {
                    const parent = treeNodeMap.get(cat.parentId)!;
                    parent.children.push(node);
                }
            }

            return {
                treeData: roots,
                totalProductsCount: totalProducts,
                rootCategoriesCount: rootCount,
            };
        }, [categories]);

    // Arama filtrelemesi
    const normalizedSearch = searchTerm.trim().toLocaleLowerCase('tr');

    // Arama yapıldığında eşleşen ve ebeveyn düğümleri tespit etme
    const { matchingCategoryIds, matchingAncestorIds } = useMemo(() => {
        if (!normalizedSearch) {
            return {
                matchingCategoryIds: new Set<number>(),
                matchingAncestorIds: new Set<number>(),
            };
        }

        const matches = new Set<number>();
        const ancestors = new Set<number>();

        for (const cat of categories) {
            const pathName = fullPathMap.get(cat.id) ?? cat.name;

            if (pathName.toLocaleLowerCase('tr').includes(normalizedSearch)) {
                matches.add(cat.id);
                // Ata ID'lerini ekle
                const pathIds = cat.path.split('/').map(Number);

                for (const pid of pathIds) {
                    if (pid !== cat.id) {
                        ancestors.add(pid);
                    }
                }
            }
        }

        return {
            matchingCategoryIds: matches,
            matchingAncestorIds: ancestors,
        };
    }, [categories, fullPathMap, normalizedSearch]);

    // Arama varken aktif expanded ID'ler
    const effectiveExpandedIds = useMemo(() => {
        if (!normalizedSearch) {
            return expandedIds;
        }

        return new Set([...expandedIds, ...matchingAncestorIds]);
    }, [expandedIds, matchingAncestorIds, normalizedSearch]);

    // Filtrelenmiş Tablo satırları
    const filteredTableCategories = useMemo(() => {
        if (!normalizedSearch) {
            return categories;
        }

        return categories.filter((cat) => {
            const pathName = fullPathMap.get(cat.id) ?? cat.name;

            return pathName.toLocaleLowerCase('tr').includes(normalizedSearch);
        });
    }, [categories, fullPathMap, normalizedSearch]);

    const toggleExpand = (id: number) => {
        setExpandedIds((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const expandAll = () => {
        setExpandedIds(new Set(categories.map((c) => c.id)));
    };

    const collapseAll = () => {
        setExpandedIds(new Set());
    };

    const openCreateRoot = () => {
        setCategoryToEdit(null);
        setDefaultParentId(null);
        setDialogOpen(true);
    };

    const openAddChild = (parent: CategoryRow) => {
        setCategoryToEdit(null);
        setDefaultParentId(parent.id);
        setDialogOpen(true);
        // Otomatik genişlet
        setExpandedIds((prev) => new Set([...prev, parent.id]));
    };

    const openEdit = (category: CategoryRow) => {
        setCategoryToEdit(category);
        setDefaultParentId(null);
        setDialogOpen(true);
    };

    const openDelete = (category: CategoryRow) => {
        setCategoryToDelete(category);
    };

    return (
        <>
            <Head title="Kategoriler" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Üst Başlık ve İstatistik Bilgisi */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title="Kategoriler"
                            description="Kendi kategori ağacınızı yönetin; ürünlerinizi sınıflandırın ve pazaryerleriyle eşleştirin."
                        />
                        {categories.length > 0 && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1 font-medium">
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {categories.length}
                                    </span>{' '}
                                    toplam kategori
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {rootCategoriesCount}
                                    </span>{' '}
                                    ana dal
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                    <Package className="size-3.5" />
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {totalProductsCount}
                                    </span>{' '}
                                    bağlı ürün
                                </span>
                            </div>
                        )}
                    </div>

                    <PermissionButton
                        check={canManage}
                        type="button"
                        onClick={openCreateRoot}
                        className="gap-1.5 self-start shadow-sm sm:self-auto"
                    >
                        <Plus className="size-4" />
                        Yeni Kategori
                    </PermissionButton>
                </div>

                {categories.length === 0 ? (
                    <EmptyState
                        icon={FolderTree}
                        title="Henüz kategori eklenmemiş"
                        description="Ürünlerinizi sınıflandırmak ve pazaryeri kategorileriyle eşleştirmek için ilk ana kategorinizi oluşturun."
                        action={
                            <PermissionButton
                                check={canManage}
                                type="button"
                                onClick={openCreateRoot}
                                className="gap-1.5"
                            >
                                <Plus className="size-4" />
                                İlk Kategoriyi Oluştur
                            </PermissionButton>
                        }
                    />
                ) : (
                    <div className="flex flex-col gap-4">
                        {/* Arama ve Görünüm Kontrol Çubuğu */}
                        <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                            {/* Arama Çubuğu */}
                            <div className="relative max-w-md flex-1">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    placeholder="Kategori veya hiyerarşi ara..."
                                    className="h-9 pr-8 pl-8.5"
                                    aria-label="Kategori ara"
                                />
                                {searchTerm && (
                                    <button
                                        type="button"
                                        onClick={() => setSearchTerm('')}
                                        className="absolute top-2.5 right-2.5 text-muted-foreground hover:text-foreground"
                                        aria-label="Aramayı temizle"
                                    >
                                        <X className="size-4" />
                                    </button>
                                )}
                            </div>

                            {/* Görünüm ve Genişletme Düğmeleri */}
                            <div className="flex items-center gap-2 self-end sm:self-auto">
                                {viewMode === 'tree' && (
                                    <div className="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={expandAll}
                                            className="h-8 gap-1 text-xs"
                                        >
                                            <ChevronsUpDown className="size-3.5" />
                                            <span className="hidden sm:inline">
                                                Tümünü Aç
                                            </span>
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={collapseAll}
                                            className="h-8 gap-1 text-xs"
                                        >
                                            <ChevronsDownUp className="size-3.5" />
                                            <span className="hidden sm:inline">
                                                Tümünü Kapat
                                            </span>
                                        </Button>
                                    </div>
                                )}

                                <ToggleGroup
                                    type="single"
                                    value={viewMode}
                                    onValueChange={(val) =>
                                        val &&
                                        setViewMode(val as 'tree' | 'table')
                                    }
                                    className="rounded-lg border border-border p-0.5"
                                >
                                    <ToggleGroupItem
                                        value="tree"
                                        aria-label="Ağaç Görünümü"
                                        className="h-7 gap-1.5 px-2.5 text-xs data-[state=on]:bg-muted data-[state=on]:text-foreground"
                                    >
                                        <FolderTree className="size-3.5" />
                                        <span className="hidden sm:inline">
                                            Ağaç
                                        </span>
                                    </ToggleGroupItem>
                                    <ToggleGroupItem
                                        value="table"
                                        aria-label="Tablo Görünümü"
                                        className="h-7 gap-1.5 px-2.5 text-xs data-[state=on]:bg-muted data-[state=on]:text-foreground"
                                    >
                                        <List className="size-3.5" />
                                        <span className="hidden sm:inline">
                                            Tablo
                                        </span>
                                    </ToggleGroupItem>
                                </ToggleGroup>
                            </div>
                        </div>

                        {/* Arama Sonuç Durumu */}
                        {normalizedSearch && (
                            <div className="flex items-center justify-between px-1 text-xs text-muted-foreground">
                                <span>
                                    "
                                    <strong className="text-foreground">
                                        {searchTerm}
                                    </strong>
                                    " araması için{' '}
                                    <strong className="font-mono text-foreground tabular-nums">
                                        {matchingCategoryIds.size}
                                    </strong>{' '}
                                    kategori bulundu.
                                </span>
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    onClick={() => setSearchTerm('')}
                                    className="h-auto p-0 text-xs"
                                >
                                    Filtreyi Temizle
                                </Button>
                            </div>
                        )}

                        {/* Arama Sıfır Eşleşme Durumu */}
                        {normalizedSearch && matchingCategoryIds.size === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card p-8 text-center">
                                <FolderTree className="mb-2 size-8 text-muted-foreground/60" />
                                <h3 className="text-sm font-medium">
                                    Eşleşen kategori bulunamadı
                                </h3>
                                <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                    "{searchTerm}" aramasına uygun kategori
                                    bulunmuyor. Yazımı kontrol edebilir veya
                                    yeni bir kategori oluşturabilirsiniz.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setSearchTerm('')}
                                    className="mt-3"
                                >
                                    Aramayı Temizle
                                </Button>
                            </div>
                        ) : viewMode === 'tree' ? (
                            /* Hiyerarşik Ağaç Görünümü */
                            <div className="rounded-lg border border-border bg-card p-3 shadow-xs">
                                <div className="space-y-1">
                                    {treeData.map((node) => (
                                        <CategoryTreeNodeItem
                                            key={node.id}
                                            node={node}
                                            expandedIds={effectiveExpandedIds}
                                            toggleExpand={toggleExpand}
                                            onAddChild={openAddChild}
                                            onEdit={openEdit}
                                            onDelete={openDelete}
                                            searchTerm={searchTerm}
                                            canManage={canManage}
                                            depth={0}
                                        />
                                    ))}
                                </div>
                            </div>
                        ) : (
                            /* Tablo Görünümü (Tüm Liste + Hiyerarşik Yol) */
                            <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-1/3">
                                                Kategori Adı
                                            </TableHead>
                                            <TableHead className="w-1/3">
                                                Hiyerarşi Yolu
                                            </TableHead>
                                            <TableHead className="w-28 text-right">
                                                Alt Kategori
                                            </TableHead>
                                            <TableHead className="w-24 text-right">
                                                Ürün
                                            </TableHead>
                                            <TableHead className="w-28 pr-4 text-right">
                                                İşlemler
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredTableCategories.map((cat) => {
                                            const subCount = categories.filter(
                                                (c) => c.parentId === cat.id,
                                            ).length;

                                            return (
                                                <TableRow key={cat.id}>
                                                    <TableCell>
                                                        <div
                                                            className="flex items-center gap-2"
                                                            style={{
                                                                paddingLeft: `${cat.depth * 1.25}rem`,
                                                            }}
                                                        >
                                                            <Folder className="size-4 shrink-0 text-amber-500/80" />
                                                            <span className="font-medium">
                                                                {cat.name}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-xs font-normal text-muted-foreground">
                                                        {fullPathMap.get(
                                                            cat.id,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-xs text-muted-foreground tabular-nums">
                                                        {subCount > 0 ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="font-mono text-xs tabular-nums"
                                                            >
                                                                {subCount}
                                                            </Badge>
                                                        ) : (
                                                            '—'
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-sm tabular-nums">
                                                        {cat.productCount >
                                                        0 ? (
                                                            <Badge
                                                                variant="secondary"
                                                                className="font-mono text-xs tabular-nums"
                                                            >
                                                                {
                                                                    cat.productCount
                                                                }
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">
                                                                0
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="pr-4 text-right">
                                                        <TooltipProvider
                                                            delayDuration={200}
                                                        >
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <PermissionButton
                                                                            check={
                                                                                canManage
                                                                            }
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="size-7 text-muted-foreground hover:text-foreground"
                                                                            aria-label={`${cat.name} için alt kategori ekle`}
                                                                            onClick={() =>
                                                                                openAddChild(
                                                                                    cat,
                                                                                )
                                                                            }
                                                                        >
                                                                            <FolderPlus className="size-3.5" />
                                                                        </PermissionButton>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent side="top">
                                                                        Alt
                                                                        Kategori
                                                                        Ekle
                                                                    </TooltipContent>
                                                                </Tooltip>

                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <PermissionButton
                                                                            check={
                                                                                canManage
                                                                            }
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="size-7 text-muted-foreground hover:text-foreground"
                                                                            aria-label={`${cat.name} düzenle`}
                                                                            onClick={() =>
                                                                                openEdit(
                                                                                    cat,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Pencil className="size-3.5" />
                                                                        </PermissionButton>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent side="top">
                                                                        Düzenle
                                                                    </TooltipContent>
                                                                </Tooltip>

                                                                <Tooltip>
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <PermissionButton
                                                                            check={
                                                                                canManage
                                                                            }
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="size-7 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                            aria-label={`${cat.name} sil`}
                                                                            onClick={() =>
                                                                                openDelete(
                                                                                    cat,
                                                                                )
                                                                            }
                                                                        >
                                                                            <Trash2 className="size-3.5" />
                                                                        </PermissionButton>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent side="top">
                                                                        Sil
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            </div>
                                                        </TooltipProvider>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Kategori Ekleme / Düzenleme Modalı */}
            <CategoryDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                categories={categories}
                categoryToEdit={categoryToEdit}
                defaultParentId={defaultParentId}
            />

            {/* Kategori Silme Onay Modalı */}
            <CategoryDeleteDialog
                category={categoryToDelete}
                categories={categories}
                onClose={() => setCategoryToDelete(null)}
            />
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
