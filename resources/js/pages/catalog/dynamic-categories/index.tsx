import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpDown,
    ExternalLink,
    Filter,
    Package,
    Pencil,
    Plus,
    Search,
    Trash2,
    Wand2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DynamicCategoryController from '@/actions/App/Http/Controllers/Catalog/DynamicCategoryController';
import { DynamicCategoryDeleteDialog } from '@/components/catalog/dynamic-category-delete-dialog';
import { DynamicCategoryDialog } from '@/components/catalog/dynamic-category-dialog';
import type { DynamicCategoryRow } from '@/components/catalog/dynamic-category-dialog';
import { PermissionButton } from '@/components/catalog/permission-button';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermission } from '@/hooks/use-permission';

type SortOption = 'name-asc' | 'name-desc' | 'products-desc' | 'products-asc';

export default function DynamicCategoryIndex({
    categories = [],
    fields = [],
    operators = [],
    matchTypes = [],
    brands = [],
    productCategories = [],
    tags = [],
}: {
    categories: DynamicCategoryRow[];
    fields: { value: string; label: string }[];
    operators: { value: string; label: string }[];
    matchTypes: { value: string; label: string }[];
    brands: { id: number; name: string }[];
    productCategories: { id: number; name: string }[];
    tags: { id: number; name: string }[];
}) {
    const canManage = usePermission()('catalog.manage');

    const [searchTerm, setSearchTerm] = useState('');
    const [sortOption, setSortOption] = useState<SortOption>('name-asc');

    // Dialog state'leri
    const [dialogOpen, setDialogOpen] = useState(false);
    const [catToEdit, setCatToEdit] = useState<DynamicCategoryRow | null>(null);
    const [catToDelete, setCatToDelete] = useState<DynamicCategoryRow | null>(
        null,
    );

    // Toplam istatistikler
    const totalProductsCount = useMemo(
        () => categories.reduce((sum, c) => sum + c.productCount, 0),
        [categories],
    );

    // Arama ve sıralama
    const filteredAndSortedCategories = useMemo(() => {
        const normalizedSearch = searchTerm.trim().toLocaleLowerCase('tr');

        let result = categories;

        if (normalizedSearch) {
            result = categories.filter(
                (cat) =>
                    cat.name
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    cat.slug
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    (cat.description &&
                        cat.description
                            .toLocaleLowerCase('tr')
                            .includes(normalizedSearch)),
            );
        }

        return [...result].sort((a, b) => {
            switch (sortOption) {
                case 'name-asc':
                    return a.name.localeCompare(b.name, 'tr');
                case 'name-desc':
                    return b.name.localeCompare(a.name, 'tr');
                case 'products-desc':
                    return b.productCount - a.productCount;
                case 'products-asc':
                    return a.productCount - b.productCount;
                default:
                    return 0;
            }
        });
    }, [categories, searchTerm, sortOption]);

    const openCreate = () => {
        setCatToEdit(null);
        setDialogOpen(true);
    };

    const openEdit = (cat: DynamicCategoryRow) => {
        setCatToEdit(cat);
        setDialogOpen(true);
    };

    const openDelete = (cat: DynamicCategoryRow) => {
        setCatToDelete(cat);
    };

    return (
        <>
            <Head title="Dinamik Kategoriler" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Üst Başlık ve İstatistik Bilgisi */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title="Dinamik Kategoriler"
                            description="Belirleyeceğiniz koşullara uyan ürünlerin otomatik olarak toplandığı akıllı kategoriler."
                        />
                        {categories.length > 0 && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1 font-medium">
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {categories.length}
                                    </span>{' '}
                                    dinamik kategori
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                    <Package className="size-3.5" />
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {totalProductsCount}
                                    </span>{' '}
                                    eşleşen ürün
                                </span>
                            </div>
                        )}
                    </div>

                    <PermissionButton
                        check={canManage}
                        type="button"
                        onClick={openCreate}
                        className="gap-1.5 self-start shadow-sm sm:self-auto"
                    >
                        <Plus className="size-4" />
                        Yeni Dinamik Kategori
                    </PermissionButton>
                </div>

                {categories.length === 0 ? (
                    <EmptyState
                        icon={Wand2}
                        title="Henüz dinamik kategori oluşturulmamış"
                        description="Marka, fiyat, etiket, indirim ve varyant kurallarına göre ürünleri otomatik toplayan ilk dinamik kategorinizi oluşturun."
                        action={
                            <PermissionButton
                                check={canManage}
                                type="button"
                                onClick={openCreate}
                                className="gap-1.5"
                            >
                                <Plus className="size-4" />
                                İlk Dinamik Kategoriyi Oluştur
                            </PermissionButton>
                        }
                    />
                ) : (
                    <div className="flex flex-col gap-4">
                        {/* Arama ve Sıralama Kontrol Çubuğu */}
                        <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                            {/* Arama Çubuğu */}
                            <div className="relative max-w-md flex-1">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    placeholder="Kategori adı, slug veya açıklama ara..."
                                    className="h-9 pr-8 pl-8.5"
                                    aria-label="Dinamik kategori ara"
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

                            {/* Sıralama Seçici */}
                            <div className="flex items-center gap-2 self-end sm:self-auto">
                                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <ArrowUpDown className="size-3.5" />
                                    <span className="hidden sm:inline">
                                        Sırala:
                                    </span>
                                </div>
                                <Select
                                    value={sortOption}
                                    onValueChange={(val) =>
                                        setSortOption(val as SortOption)
                                    }
                                >
                                    <SelectTrigger
                                        className="h-9 w-[180px] text-xs"
                                        aria-label="Sıralama ölçütü"
                                    >
                                        <SelectValue placeholder="Sıralama" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            value="name-asc"
                                            className="text-xs"
                                        >
                                            İsim (A → Z)
                                        </SelectItem>
                                        <SelectItem
                                            value="name-desc"
                                            className="text-xs"
                                        >
                                            İsim (Z → A)
                                        </SelectItem>
                                        <SelectItem
                                            value="products-desc"
                                            className="text-xs"
                                        >
                                            Eşleşen Ürün (Çoktan Aza)
                                        </SelectItem>
                                        <SelectItem
                                            value="products-asc"
                                            className="text-xs"
                                        >
                                            Eşleşen Ürün (Azdan Çoğa)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
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
                                        {filteredAndSortedCategories.length}
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

                        {/* Veri Tablosu */}
                        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-1/3">
                                            Kategori Adı
                                        </TableHead>
                                        <TableHead className="w-1/4">
                                            Kural Tipi
                                        </TableHead>
                                        <TableHead className="w-24 text-center">
                                            Koşullar
                                        </TableHead>
                                        <TableHead className="w-28 text-right">
                                            Eşleşen Ürün
                                        </TableHead>
                                        <TableHead className="w-28 pr-4 text-right">
                                            İşlemler
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredAndSortedCategories.map((cat) => (
                                        <TableRow key={cat.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2.5">
                                                    <div className="flex size-7 items-center justify-center rounded-md bg-muted/60 text-muted-foreground">
                                                        <Wand2 className="size-3.5" />
                                                    </div>
                                                    <div>
                                                        <Link
                                                            href={DynamicCategoryController.show.url(
                                                                {
                                                                    dynamicCategory:
                                                                        cat.id,
                                                                },
                                                            )}
                                                            className="font-medium text-foreground hover:underline"
                                                        >
                                                            {cat.name}
                                                        </Link>
                                                        <div className="font-mono text-xs text-muted-foreground">
                                                            {cat.slug}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        cat.matchType === 'all'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                    className="text-xs"
                                                >
                                                    {cat.matchType === 'all'
                                                        ? 'Tüm Koşullar (VE)'
                                                        : 'En Az Bir (VEYA)'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-center font-mono text-xs">
                                                <span className="flex items-center justify-center gap-1">
                                                    <Filter className="size-3 text-muted-foreground" />
                                                    {cat.conditionCount}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm tabular-nums">
                                                {cat.productCount > 0 ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className="font-mono text-xs tabular-nums"
                                                    >
                                                        {cat.productCount}
                                                    </Badge>
                                                ) : (
                                                    <span className="font-mono text-xs text-muted-foreground">
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
                                                                <Button
                                                                    asChild
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-7 text-muted-foreground hover:text-foreground"
                                                                >
                                                                    <Link
                                                                        href={DynamicCategoryController.show.url(
                                                                            {
                                                                                dynamicCategory:
                                                                                    cat.id,
                                                                            },
                                                                        )}
                                                                        aria-label={`${cat.name} detaylarını görüntüle`}
                                                                    >
                                                                        <ExternalLink className="size-3.5" />
                                                                    </Link>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent side="top">
                                                                Detaylar &
                                                                Ürünler
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
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                )}
            </div>

            {/* Kategori Ekleme / Düzenleme Modalı */}
            <DynamicCategoryDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                categoryToEdit={catToEdit}
                fields={fields}
                operators={operators}
                matchTypes={matchTypes}
                brands={brands}
                productCategories={productCategories}
                tags={tags}
            />

            {/* Kategori Silme Onay Modalı */}
            <DynamicCategoryDeleteDialog
                category={catToDelete}
                onClose={() => setCatToDelete(null)}
            />
        </>
    );
}
