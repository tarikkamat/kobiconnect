import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpDown,
    BadgePercent,
    Coins,
    DollarSign,
    ExternalLink,
    Plus,
    Search,
    Sliders,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import PriceListController from '@/actions/App/Http/Controllers/Catalog/PriceListController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { PriceListDeleteDialog } from '@/components/catalog/price-list-delete-dialog';
import type { PriceListRow } from '@/components/catalog/price-list-delete-dialog';
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

type SortOption = 'name-asc' | 'name-desc' | 'items-desc' | 'items-asc';

export default function PriceListIndex({
    priceLists = [],
}: {
    priceLists: PriceListRow[];
}) {
    const canManage = usePermission()('catalog.manage');

    const [searchTerm, setSearchTerm] = useState('');
    const [sortOption, setSortOption] = useState<SortOption>('name-asc');
    const [listToDelete, setListToDelete] = useState<PriceListRow | null>(null);

    // Arama ve sıralama
    const filteredAndSortedLists = useMemo(() => {
        const normalizedSearch = searchTerm.trim().toLocaleLowerCase('tr');

        let result = priceLists;

        if (normalizedSearch) {
            result = priceLists.filter(
                (pl) =>
                    pl.name
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    pl.typeLabel
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    pl.targetCurrency
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    (pl.description &&
                        pl.description
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
                case 'items-desc':
                    return b.itemCount - a.itemCount;
                case 'items-asc':
                    return a.itemCount - b.itemCount;
                default:
                    return 0;
            }
        });
    }, [priceLists, searchTerm, sortOption]);

    const getTypeIcon = (type: string) => {
        switch (type) {
            case 'currency':
                return <Coins className="size-3.5 text-amber-500" />;
            case 'dynamic':
                return <BadgePercent className="size-3.5 text-blue-500" />;
            default:
                return <Sliders className="size-3.5 text-emerald-500" />;
        }
    };

    return (
        <>
            <Head title="Fiyat Listeleri" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Üst Başlık ve İstatistik Bilgisi */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title="Fiyat Listeleri"
                            description="Pazaryeri, fiziksel mağaza veya B2B kanallarınıza özel manuel, kura göre veya dinamik fiyat listeleri oluşturun."
                        />
                        {priceLists.length > 0 && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1 font-medium">
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {priceLists.length}
                                    </span>{' '}
                                    fiyat listesi
                                </span>
                            </div>
                        )}
                    </div>

                    <PermissionButton
                        check={canManage}
                        asChild
                        className="gap-1.5 self-start shadow-sm sm:self-auto"
                    >
                        <Link href={PriceListController.create.url()}>
                            <Plus className="size-4" />
                            Yeni Fiyat Listesi
                        </Link>
                    </PermissionButton>
                </div>

                {priceLists.length === 0 ? (
                    <EmptyState
                        icon={DollarSign}
                        title="Henüz fiyat listesi oluşturulmamış"
                        description="Satış kanallarınıza farklı fiyatlar vermek, farklı para birimlerinde satış yapmak veya dinamik kurallara göre fiyatlandırmak için ilk listenizi oluşturun."
                        action={
                            <PermissionButton
                                check={canManage}
                                asChild
                                className="gap-1.5"
                            >
                                <Link href={PriceListController.create.url()}>
                                    <Plus className="size-4" />
                                    İlk Fiyat Listesini Oluştur
                                </Link>
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
                                    placeholder="Fiyat listesi adı, tipi veya para birimi ara..."
                                    className="h-9 pr-8 pl-8.5"
                                    aria-label="Fiyat listesi ara"
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
                                            value="items-desc"
                                            className="text-xs"
                                        >
                                            Kalem Sayısı (Çoktan Aza)
                                        </SelectItem>
                                        <SelectItem
                                            value="items-asc"
                                            className="text-xs"
                                        >
                                            Kalem Sayısı (Azdan Çoğa)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Veri Tablosu */}
                        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-1/3">
                                            Liste Adı
                                        </TableHead>
                                        <TableHead className="w-1/4">
                                            Liste Tipi
                                        </TableHead>
                                        <TableHead className="w-24 text-center">
                                            Para Birimi
                                        </TableHead>
                                        <TableHead className="w-28 text-right">
                                            Kalemler
                                        </TableHead>
                                        <TableHead className="w-24 text-center">
                                            Durum
                                        </TableHead>
                                        <TableHead className="w-24 pr-4 text-right">
                                            İşlemler
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredAndSortedLists.map((pl) => (
                                        <TableRow key={pl.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2.5">
                                                    <div className="flex size-7 items-center justify-center rounded-md bg-muted/60 text-muted-foreground">
                                                        {getTypeIcon(pl.type)}
                                                    </div>
                                                    <div>
                                                        <Link
                                                            href={PriceListController.show.url(
                                                                {
                                                                    priceList:
                                                                        pl.id,
                                                                },
                                                            )}
                                                            className="font-medium text-foreground hover:underline"
                                                        >
                                                            {pl.name}
                                                        </Link>
                                                        {pl.description && (
                                                            <div className="line-clamp-1 text-xs text-muted-foreground">
                                                                {pl.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {pl.typeLabel}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-center font-mono text-xs font-semibold">
                                                {pl.type === 'currency'
                                                    ? `${pl.sourceCurrency} → ${pl.targetCurrency}`
                                                    : pl.targetCurrency}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm tabular-nums">
                                                <Badge
                                                    variant="secondary"
                                                    className="font-mono text-xs tabular-nums"
                                                >
                                                    {pl.itemCount}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge
                                                    variant={
                                                        pl.isActive
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                    className="text-[11px]"
                                                >
                                                    {pl.isActive
                                                        ? 'Aktif'
                                                        : 'Pasif'}
                                                </Badge>
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
                                                                        href={PriceListController.show.url(
                                                                            {
                                                                                priceList:
                                                                                    pl.id,
                                                                            },
                                                                        )}
                                                                        aria-label={`${pl.name} fiyatlarını görüntüle`}
                                                                    >
                                                                        <ExternalLink className="size-3.5" />
                                                                    </Link>
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent side="top">
                                                                Fiyatlar & Detay
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
                                                                    aria-label={`${pl.name} sil`}
                                                                    onClick={() =>
                                                                        setListToDelete(
                                                                            pl,
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

            {/* Silme Onay Modalı */}
            <PriceListDeleteDialog
                priceList={listToDelete}
                onClose={() => setListToDelete(null)}
            />
        </>
    );
}
