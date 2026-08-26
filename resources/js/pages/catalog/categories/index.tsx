import { Head, Link, router } from '@inertiajs/react';
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
import { create, edit, index } from '@/routes/categories';
import { index as definitions } from '@/routes/definitions';

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

    // Ağaç (Tree) veri yapısını oluşturma
    const treeData = useMemo(() => {
        const nodeMap = new Map<number, CategoryTreeNode>();
        const roots: CategoryTreeNode[] = [];

        // 1. Düğüm nesnelerini ilkle
        for (const cat of categories) {
            nodeMap.set(cat.id, {
                ...cat,
                children: [],
                totalProductCount: cat.productCount,
            });
        }

        // 2. Ebeveyn-çocuk ilişkilerini kur
        for (const cat of categories) {
            const node = nodeMap.get(cat.id)!;

            if (cat.parentId && nodeMap.has(cat.parentId)) {
                nodeMap.get(cat.parentId)!.children.push(node);
            } else {
                roots.push(node);
            }
        }

        // 3. Alt dalların ürün sayılarını yukarıya topla
        const calculateTotalProducts = (node: CategoryTreeNode): number => {
            let total = node.productCount;

            for (const child of node.children) {
                total += calculateTotalProducts(child);
            }

            node.totalProductCount = total;

            return total;
        };

        for (const root of roots) {
            calculateTotalProducts(root);
        }

        return roots;
    }, [categories]);

    // Toplam istatistikler
    const totalProductsCount = useMemo(
        () => categories.reduce((sum, c) => sum + c.productCount, 0),
        [categories],
    );

    const rootCategoriesCount = useMemo(
        () => categories.filter((c) => c.depth === 0).length,
        [categories],
    );

    // Tablo görünümü için filtrelenmiş liste
    const normalizedSearch = searchTerm.trim().toLocaleLowerCase('tr');

    const filteredCategories = useMemo(() => {
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

    const openAddChild = () => {
        router.visit(create());
    };

    const openEdit = (category: CategoryRow) => {
        router.visit(edit({ category: category.id }));
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
                            description="Ürünlerin hiyerarşik kategori ağacı ve pazaryeri eşlemeleri."
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
                                <span className="text-muted-foreground/80">
                                    <span className="font-mono tabular-nums">
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
                                    ürün bağlantısı
                                </span>
                            </div>
                        )}
                    </div>

                    {canManage && (
                        <Button
                            asChild
                            className="gap-1.5 self-start shadow-sm sm:self-auto"
                        >
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Yeni Kategori
                            </Link>
                        </Button>
                    )}
                </div>

                {categories.length === 0 ? (
                    <EmptyState
                        icon={FolderTree}
                        title="Henüz kategori eklenmemiş"
                        description="Ürünlerinizi düzenlemek ve pazaryeri kategorileriyle eşleştirmek için hiyerarşik kategorilerinizi oluşturun."
                        action={
                            canManage ? (
                                <Button asChild className="gap-1.5">
                                    <Link href={create()}>
                                        <Plus className="size-4" />
                                        İlk Kategoriyi Ekle
                                    </Link>
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="flex flex-col gap-4">
                        {/* Arama, Ağaç Genişletme ve Görünüm Kontrol Çubuğu */}
                        <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                            {/* Arama Çubuğu */}
                            <div className="relative max-w-md flex-1">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    placeholder="Kategori adı veya hiyerarşi ara..."
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

                            {/* Görünüm ve Ağaç Düğmeleri */}
                            <div className="flex items-center gap-2 self-end sm:self-auto">
                                {viewMode === 'tree' && (
                                    <div className="flex items-center gap-1 border-r border-border pr-2">
                                        <TooltipProvider delayDuration={200}>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={expandAll}
                                                        className="h-8 px-2 text-xs"
                                                        aria-label="Tümünü genişlet"
                                                    >
                                                        <ChevronsUpDown className="mr-1 size-3.5" />
                                                        <span className="hidden md:inline">
                                                            Tümünü Aç
                                                        </span>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    Tüm alt dalları genişlet
                                                </TooltipContent>
                                            </Tooltip>

                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={collapseAll}
                                                        className="h-8 px-2 text-xs"
                                                        aria-label="Tümünü daralt"
                                                    >
                                                        <ChevronsDownUp className="mr-1 size-3.5" />
                                                        <span className="hidden md:inline">
                                                            Daralt
                                                        </span>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent side="top">
                                                    Tüm alt dalları kapat
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    </div>
                                )}

                                {/* Ağaç / Tablo Görünüm Seçici */}
                                <ToggleGroup
                                    type="single"
                                    value={viewMode}
                                    onValueChange={(val) => {
                                        if (val) {
                                            setViewMode(
                                                val as 'tree' | 'table',
                                            );
                                        }
                                    }}
                                    className="border border-border"
                                    aria-label="Görünüm modu seçimi"
                                >
                                    <ToggleGroupItem
                                        value="tree"
                                        className="h-8 px-2.5 text-xs"
                                        aria-label="Ağaç görünümü"
                                    >
                                        <FolderTree className="mr-1.5 size-3.5" />
                                        Ağaç
                                    </ToggleGroupItem>
                                    <ToggleGroupItem
                                        value="table"
                                        className="h-8 px-2.5 text-xs"
                                        aria-label="Tablo görünümü"
                                    >
                                        <List className="mr-1.5 size-3.5" />
                                        Tablo
                                    </ToggleGroupItem>
                                </ToggleGroup>
                            </div>
                        </div>

                        {/* Arama Sonuç Durumu */}
                        {searchTerm.trim() && (
                            <div className="flex items-center justify-between px-1 text-xs text-muted-foreground">
                                <span>
                                    "
                                    <strong className="text-foreground">
                                        {searchTerm}
                                    </strong>
                                    " araması için{' '}
                                    <strong className="font-mono text-foreground tabular-nums">
                                        {filteredCategories.length}
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
                        {searchTerm.trim() &&
                        filteredCategories.length === 0 ? (
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
                            /* 1. Hiyerarşik Ağaç Görünümü (Tree View) */
                            <div className="space-y-1 rounded-lg border border-border bg-card p-3 shadow-xs">
                                {treeData.map((rootNode) => (
                                    <CategoryTreeNodeItem
                                        key={rootNode.id}
                                        node={rootNode}
                                        expandedIds={expandedIds}
                                        toggleExpand={toggleExpand}
                                        onAddChild={openAddChild}
                                        onEdit={openEdit}
                                        onDelete={openDelete}
                                        searchTerm={searchTerm}
                                        canManage={canManage}
                                    />
                                ))}
                            </div>
                        ) : (
                            /* 2. Tablo Görünümü (Table View) */
                            <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-2/5">
                                                Kategori Adı
                                            </TableHead>
                                            <TableHead className="w-2/5">
                                                Tam Yol (Hiyerarşi)
                                            </TableHead>
                                            <TableHead className="w-28 text-right">
                                                Bağlı Ürün
                                            </TableHead>
                                            <TableHead className="w-28 pr-4 text-right">
                                                İşlemler
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredCategories.map((cat) => {
                                            const fullPath =
                                                fullPathMap.get(cat.id) ??
                                                cat.name;

                                            return (
                                                <TableRow key={cat.id}>
                                                    <TableCell>
                                                        <div
                                                            className="flex items-center gap-2"
                                                            style={{
                                                                paddingLeft: `${cat.depth * 16}px`,
                                                            }}
                                                        >
                                                            {cat.depth > 0 && (
                                                                <span className="font-mono text-muted-foreground/60">
                                                                    └─
                                                                </span>
                                                            )}
                                                            <div className="flex size-6 items-center justify-center rounded-md bg-muted/60 text-muted-foreground">
                                                                <Folder className="size-3" />
                                                            </div>
                                                            <span className="font-medium text-foreground">
                                                                {cat.name}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-xs text-muted-foreground">
                                                        {fullPath}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-xs tabular-nums">
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
                                                                {canManage && (
                                                                    <Tooltip>
                                                                        <TooltipTrigger
                                                                            asChild
                                                                        >
                                                                            <Button
                                                                                asChild
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="size-7 text-muted-foreground hover:text-foreground"
                                                                                aria-label={`${cat.name} için alt kategori ekle`}
                                                                            >
                                                                                <Link
                                                                                    href={create()}
                                                                                >
                                                                                    <FolderPlus className="size-3.5" />
                                                                                </Link>
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent side="top">
                                                                            Alt
                                                                            Kategori
                                                                            Ekle
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                )}

                                                                {canManage && (
                                                                    <Tooltip>
                                                                        <TooltipTrigger
                                                                            asChild
                                                                        >
                                                                            <Button
                                                                                asChild
                                                                                variant="ghost"
                                                                                size="icon"
                                                                                className="size-7 text-muted-foreground hover:text-foreground"
                                                                                aria-label={`${cat.name} düzenle`}
                                                                            >
                                                                                <Link
                                                                                    href={edit(
                                                                                        {
                                                                                            category:
                                                                                                cat.id,
                                                                                        },
                                                                                    )}
                                                                                >
                                                                                    <Pencil className="size-3.5" />
                                                                                </Link>
                                                                            </Button>
                                                                        </TooltipTrigger>
                                                                        <TooltipContent side="top">
                                                                            Düzenle
                                                                        </TooltipContent>
                                                                    </Tooltip>
                                                                )}

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
            title: 'Tanımlamalar',
            href: definitions(),
        },
        {
            title: 'Kategoriler',
            href: index(),
        },
    ],
};
