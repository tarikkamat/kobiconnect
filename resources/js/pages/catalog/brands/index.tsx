import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpDown,
    Package,
    Pencil,
    Plus,
    Search,
    Tag,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { BrandDeleteDialog } from '@/components/catalog/brand-delete-dialog';
import type { BrandRow } from '@/components/catalog/brand-dialog';
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
import { create, edit, index } from '@/routes/brands';
import { index as definitions } from '@/routes/definitions';

type SortOption = 'name-asc' | 'name-desc' | 'products-desc' | 'products-asc';

export default function BrandIndex({ brands }: { brands: BrandRow[] }) {
    const canManage = usePermission()('catalog.manage');

    const [searchTerm, setSearchTerm] = useState('');
    const [sortOption, setSortOption] = useState<SortOption>('name-asc');
    const [brandToDelete, setBrandToDelete] = useState<BrandRow | null>(null);

    // Toplam istatistikler
    const totalProductsCount = useMemo(
        () => brands.reduce((sum, b) => sum + b.productCount, 0),
        [brands],
    );

    const brandsWithProductsCount = useMemo(
        () => brands.filter((b) => b.productCount > 0).length,
        [brands],
    );

    // Arama ve sıralama
    const filteredAndSortedBrands = useMemo(() => {
        const normalizedSearch = searchTerm.trim().toLocaleLowerCase('tr');

        let result = brands;

        if (normalizedSearch) {
            result = brands.filter(
                (brand) =>
                    brand.name
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    brand.slug
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch),
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
    }, [brands, searchTerm, sortOption]);

    const openDelete = (brand: BrandRow) => {
        setBrandToDelete(brand);
    };

    return (
        <>
            <Head title="Markalar" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Üst Başlık ve İstatistik Bilgisi */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title="Markalar"
                            description="Pazaryeri marka eşlemesi ve ürün sınıflandırması bu listeye dayanır."
                        />
                        {brands.length > 0 && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1 font-medium">
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {brands.length}
                                    </span>{' '}
                                    toplam marka
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                    <Package className="size-3.5" />
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {totalProductsCount}
                                    </span>{' '}
                                    bağlı ürün
                                </span>
                                {brandsWithProductsCount < brands.length && (
                                    <>
                                        <span>•</span>
                                        <span className="text-muted-foreground/80">
                                            <span className="font-mono tabular-nums">
                                                {brands.length -
                                                    brandsWithProductsCount}
                                            </span>{' '}
                                            markada henüz ürün yok
                                        </span>
                                    </>
                                )}
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
                                Yeni Marka
                            </Link>
                        </Button>
                    )}
                </div>

                {brands.length === 0 ? (
                    <EmptyState
                        icon={Tag}
                        title="Henüz marka eklenmemiş"
                        description="Ürünlerinizi markalarına göre gruplamak ve pazaryeri eşlemelerini kolaylaştırmak için ilk markanızı ekleyin."
                        action={
                            canManage ? (
                                <Button asChild className="gap-1.5">
                                    <Link href={create()}>
                                        <Plus className="size-4" />
                                        İlk Markayı Ekle
                                    </Link>
                                </Button>
                            ) : undefined
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
                                    placeholder="Marka adı veya slug ara..."
                                    className="h-9 pr-8 pl-8.5"
                                    aria-label="Marka ara"
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
                                            Ürün Sayısı (Çoktan Aza)
                                        </SelectItem>
                                        <SelectItem
                                            value="products-asc"
                                            className="text-xs"
                                        >
                                            Ürün Sayısı (Azdan Çoğa)
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
                                        {filteredAndSortedBrands.length}
                                    </strong>{' '}
                                    marka bulundu.
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
                        filteredAndSortedBrands.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card p-8 text-center">
                                <Tag className="mb-2 size-8 text-muted-foreground/60" />
                                <h3 className="text-sm font-medium">
                                    Eşleşen marka bulunamadı
                                </h3>
                                <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                    "{searchTerm}" aramasına uygun marka
                                    bulunmuyor. Yazımı kontrol edebilir veya
                                    yeni bir marka oluşturabilirsiniz.
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
                        ) : (
                            /* Veri Tablosu */
                            <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-1/2">
                                                Marka Adı
                                            </TableHead>
                                            <TableHead className="w-1/3">
                                                Slug
                                            </TableHead>
                                            <TableHead className="w-28 text-right">
                                                Bağlı Ürün
                                            </TableHead>
                                            <TableHead className="w-24 pr-4 text-right">
                                                İşlemler
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredAndSortedBrands.map(
                                            (brand) => (
                                                <TableRow key={brand.id}>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2.5">
                                                            <div className="flex size-7 items-center justify-center rounded-md bg-muted/60 text-muted-foreground">
                                                                <Tag className="size-3.5" />
                                                            </div>
                                                            <span className="font-medium text-foreground">
                                                                {brand.name}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="font-mono text-xs text-muted-foreground tabular-nums">
                                                        {brand.slug}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-sm tabular-nums">
                                                        {brand.productCount >
                                                        0 ? (
                                                            <Badge
                                                                variant="secondary"
                                                                className="font-mono text-xs tabular-nums"
                                                            >
                                                                {
                                                                    brand.productCount
                                                                }
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
                                                                                aria-label={`${brand.name} düzenle`}
                                                                            >
                                                                                <Link
                                                                                    href={edit(
                                                                                        {
                                                                                            brand: brand.id,
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
                                                                            aria-label={`${brand.name} sil`}
                                                                            onClick={() =>
                                                                                openDelete(
                                                                                    brand,
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
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Marka Silme Onay Modalı */}
            <BrandDeleteDialog
                brand={brandToDelete}
                onClose={() => setBrandToDelete(null)}
            />
        </>
    );
}

BrandIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tanımlamalar',
            href: definitions(),
        },
        {
            title: 'Markalar',
            href: index(),
        },
    ],
};
