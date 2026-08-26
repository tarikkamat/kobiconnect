import { Head } from '@inertiajs/react';
import {
    ArrowUpDown,
    Package,
    Pencil,
    Plus,
    Scale,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { PermissionButton } from '@/components/catalog/permission-button';
import { UnitDeleteDialog } from '@/components/catalog/unit-delete-dialog';
import { UnitDialog } from '@/components/catalog/unit-dialog';
import type { UnitRow } from '@/components/catalog/unit-dialog';
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

export default function UnitIndex({ units = [] }: { units: UnitRow[] }) {
    const canManage = usePermission()('catalog.manage');

    const [searchTerm, setSearchTerm] = useState('');
    const [sortOption, setSortOption] = useState<SortOption>('name-asc');

    // Dialog state'leri
    const [dialogOpen, setDialogOpen] = useState(false);
    const [unitToEdit, setUnitToEdit] = useState<UnitRow | null>(null);
    const [unitToDelete, setUnitToDelete] = useState<UnitRow | null>(null);

    // Toplam istatistikler
    const totalProductsCount = useMemo(
        () => units.reduce((sum, u) => sum + u.productCount, 0),
        [units],
    );

    const unitsWithProductsCount = useMemo(
        () => units.filter((u) => u.productCount > 0).length,
        [units],
    );

    // Arama ve sıralama
    const filteredAndSortedUnits = useMemo(() => {
        const normalizedSearch = searchTerm.trim().toLocaleLowerCase('tr');

        let result = units;

        if (normalizedSearch) {
            result = units.filter(
                (unit) =>
                    unit.name
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    unit.shortName
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
    }, [units, searchTerm, sortOption]);

    const openCreate = () => {
        setUnitToEdit(null);
        setDialogOpen(true);
    };

    const openEdit = (unit: UnitRow) => {
        setUnitToEdit(unit);
        setDialogOpen(true);
    };

    const openDelete = (unit: UnitRow) => {
        setUnitToDelete(unit);
    };

    return (
        <>
            <Head title="Ürün Birimleri" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Üst Başlık ve İstatistik Bilgisi */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title="Ürün Birimleri"
                            description="Servis, adet, kg gibi özel birimler tanımlayarak ürün birim fiyatlarını detay ve satın alma adımlarında gösterin."
                        />
                        {units.length > 0 && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1 font-medium">
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {units.length}
                                    </span>{' '}
                                    toplam birim
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                    <Package className="size-3.5" />
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {totalProductsCount}
                                    </span>{' '}
                                    bağlı ürün
                                </span>
                                {unitsWithProductsCount < units.length && (
                                    <>
                                        <span>•</span>
                                        <span className="text-muted-foreground/80">
                                            <span className="font-mono tabular-nums">
                                                {units.length -
                                                    unitsWithProductsCount}
                                            </span>{' '}
                                            birimde henüz ürün yok
                                        </span>
                                    </>
                                )}
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
                        Yeni Birim
                    </PermissionButton>
                </div>

                {units.length === 0 ? (
                    <EmptyState
                        icon={Scale}
                        title="Henüz ürün birimi eklenmemiş"
                        description="Ürünlerinizi adet, servis, kilogram gibi birimlerle tanımlamak için ilk biriminizi ekleyin."
                        action={
                            <PermissionButton
                                check={canManage}
                                type="button"
                                onClick={openCreate}
                                className="gap-1.5"
                            >
                                <Plus className="size-4" />
                                İlk Birimi Ekle
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
                                    placeholder="Birim adı veya kısa kod ara..."
                                    className="h-9 pr-8 pl-8.5"
                                    aria-label="Birim ara"
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
                                        {filteredAndSortedUnits.length}
                                    </strong>{' '}
                                    birim bulundu.
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
                        filteredAndSortedUnits.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card p-8 text-center">
                                <Scale className="mb-2 size-8 text-muted-foreground/60" />
                                <h3 className="text-sm font-medium">
                                    Eşleşen birim bulunamadı
                                </h3>
                                <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                    "{searchTerm}" aramasına uygun birim
                                    bulunmuyor.
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
                                                Birim Adı
                                            </TableHead>
                                            <TableHead className="w-1/3">
                                                Kısa Ad / Kod
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
                                        {filteredAndSortedUnits.map((unit) => (
                                            <TableRow key={unit.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2.5">
                                                        <div className="flex size-7 items-center justify-center rounded-md bg-muted/60 text-muted-foreground">
                                                            <Scale className="size-3.5" />
                                                        </div>
                                                        <span className="font-medium text-foreground">
                                                            {unit.name}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        className="font-mono text-xs"
                                                    >
                                                        {unit.shortName}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm tabular-nums">
                                                    {unit.productCount > 0 ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="font-mono text-xs tabular-nums"
                                                        >
                                                            {unit.productCount}
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
                                                                    <PermissionButton
                                                                        check={
                                                                            canManage
                                                                        }
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-7 text-muted-foreground hover:text-foreground"
                                                                        aria-label={`${unit.name} düzenle`}
                                                                        onClick={() =>
                                                                            openEdit(
                                                                                unit,
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
                                                                        aria-label={`${unit.name} sil`}
                                                                        onClick={() =>
                                                                            openDelete(
                                                                                unit,
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
                        )}
                    </div>
                )}
            </div>

            {/* Birim Ekleme / Düzenleme Modalı */}
            <UnitDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                unitToEdit={unitToEdit}
            />

            {/* Birim Silme Onay Modalı */}
            <UnitDeleteDialog
                unit={unitToDelete}
                onClose={() => setUnitToDelete(null)}
            />
        </>
    );
}
