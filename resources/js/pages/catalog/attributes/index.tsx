import { Head, Link } from '@inertiajs/react';
import {
    ArrowUpDown,
    Check,
    Layers,
    ListFilter,
    Pencil,
    Plus,
    Search,
    SlidersHorizontal,
    Tag,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { AttributeDeleteDialog } from '@/components/catalog/attribute-delete-dialog';
import type {
    AttributeRow,
    AttributeTypeOption,
} from '@/components/catalog/attribute-dialog';
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
import { create, edit, index } from '@/routes/attributes';
import { index as definitions } from '@/routes/definitions';

type SortOption = 'name-asc' | 'name-desc' | 'values-desc' | 'values-asc';

type Props = {
    attributes: AttributeRow[];
    types?: AttributeTypeOption[];
};

export default function AttributeIndex({ attributes, types = [] }: Props) {
    const canManage = usePermission()('catalog.manage');

    const [searchTerm, setSearchTerm] = useState('');
    const [typeFilter, setTypeFilter] = useState<string>('all');
    const [sortOption, setSortOption] = useState<SortOption>('name-asc');
    const [attributeToDelete, setAttributeToDelete] =
        useState<AttributeRow | null>(null);

    // Toplam istatistikler
    const totalValuesCount = useMemo(
        () =>
            attributes.reduce(
                (sum, a) => sum + (a.valuesCount || a.values.length),
                0,
            ),
        [attributes],
    );

    const variantDefiningCount = useMemo(
        () => attributes.filter((a) => a.isVariantDefining).length,
        [attributes],
    );

    // Arama ve filtreleme
    const filteredAndSortedAttributes = useMemo(() => {
        const normalizedSearch = searchTerm.trim().toLocaleLowerCase('tr');

        let result = attributes;

        if (normalizedSearch) {
            result = result.filter(
                (attr) =>
                    attr.name
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    attr.code
                        .toLocaleLowerCase('tr')
                        .includes(normalizedSearch) ||
                    attr.values.some((v) =>
                        v.value
                            .toLocaleLowerCase('tr')
                            .includes(normalizedSearch),
                    ),
            );
        }

        if (typeFilter !== 'all') {
            result = result.filter((attr) => attr.type === typeFilter);
        }

        return [...result].sort((a, b) => {
            switch (sortOption) {
                case 'name-asc':
                    return a.name.localeCompare(b.name, 'tr');
                case 'name-desc':
                    return b.name.localeCompare(a.name, 'tr');
                case 'values-desc':
                    return (
                        (b.valuesCount || b.values.length) -
                        (a.valuesCount || a.values.length)
                    );
                case 'values-asc':
                    return (
                        (a.valuesCount || a.values.length) -
                        (b.valuesCount || b.values.length)
                    );
                default:
                    return 0;
            }
        });
    }, [attributes, searchTerm, typeFilter, sortOption]);

    const openDelete = (attr: AttributeRow) => {
        setAttributeToDelete(attr);
    };

    const getTypeLabel = (typeValue: string) => {
        const found = types.find((t) => t.value === typeValue);

        return found ? found.label : typeValue;
    };

    return (
        <>
            <Head title="Özel Alanlar ve Nitelikler" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Üst Başlık ve İstatistik Bilgisi */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title="Özel Alanlar ve Nitelikler"
                            description="Ürün varyasyonlarını oluşturmak (Beden, Renk vb.) veya ek teknik özellikler tanımlamak için kullanılır."
                        />
                        {attributes.length > 0 && (
                            <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span className="flex items-center gap-1 font-medium">
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {attributes.length}
                                    </span>{' '}
                                    toplam nitelik
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                    <Layers className="size-3.5" />
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {variantDefiningCount}
                                    </span>{' '}
                                    varyant belirleyici
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                    <Tag className="size-3.5" />
                                    <span className="font-mono font-semibold text-foreground tabular-nums">
                                        {totalValuesCount}
                                    </span>{' '}
                                    tanımlı değer seçeneği
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
                                Yeni Özel Alan
                            </Link>
                        </Button>
                    )}
                </div>

                {attributes.length === 0 ? (
                    <EmptyState
                        icon={SlidersHorizontal}
                        title="Henüz nitelik tanımlanmamış"
                        description="Ürünlerinize Beden, Renk, Materyal, Sezon gibi varyant ve filtreleme özellikleri eklemek için ilk niteliğinizi tanımlayın."
                        action={
                            canManage ? (
                                <Button asChild className="gap-1.5">
                                    <Link href={create()}>
                                        <Plus className="size-4" />
                                        İlk Niteliği Tanımla
                                    </Link>
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="flex flex-col gap-4">
                        {/* Arama ve Filtreleme Kontrol Çubuğu */}
                        <div className="flex flex-col gap-3 rounded-lg border border-border bg-card p-3 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                            {/* Arama Çubuğu */}
                            <div className="relative max-w-md flex-1">
                                <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    placeholder="Nitelik adı, kodu veya değer ara..."
                                    className="h-9 pr-8 pl-8.5"
                                    aria-label="Nitelik ara"
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

                            {/* Tür ve Sıralama Seçicileri */}
                            <div className="flex flex-wrap items-center gap-2 self-end sm:self-auto">
                                {/* Tür Filtresi */}
                                <div className="flex items-center gap-1.5">
                                    <ListFilter className="size-3.5 text-muted-foreground" />
                                    <Select
                                        value={typeFilter}
                                        onValueChange={setTypeFilter}
                                    >
                                        <SelectTrigger
                                            className="h-9 w-[150px] text-xs"
                                            aria-label="Tür filtresi"
                                        >
                                            <SelectValue placeholder="Tüm Türler" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                value="all"
                                                className="text-xs"
                                            >
                                                Tüm Türler
                                            </SelectItem>
                                            {types.map((t) => (
                                                <SelectItem
                                                    key={t.value}
                                                    value={t.value}
                                                    className="text-xs"
                                                >
                                                    {t.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Sıralama */}
                                <div className="flex items-center gap-1.5">
                                    <ArrowUpDown className="size-3.5 text-muted-foreground" />
                                    <Select
                                        value={sortOption}
                                        onValueChange={(val) =>
                                            setSortOption(val as SortOption)
                                        }
                                    >
                                        <SelectTrigger
                                            className="h-9 w-[170px] text-xs"
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
                                                value="values-desc"
                                                className="text-xs"
                                            >
                                                Değer Sayısı (Çoktan Aza)
                                            </SelectItem>
                                            <SelectItem
                                                value="values-asc"
                                                className="text-xs"
                                            >
                                                Değer Sayısı (Azdan Çoğa)
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Arama ve Filtre Durumu */}
                        {(searchTerm.trim() || typeFilter !== 'all') && (
                            <div className="flex items-center justify-between px-1 text-xs text-muted-foreground">
                                <span>
                                    {searchTerm && (
                                        <>
                                            "
                                            <strong className="text-foreground">
                                                {searchTerm}
                                            </strong>
                                            "{' '}
                                        </>
                                    )}
                                    {typeFilter !== 'all' && (
                                        <>
                                            (
                                            <strong>
                                                {getTypeLabel(typeFilter)}
                                            </strong>
                                            ){' '}
                                        </>
                                    )}
                                    için{' '}
                                    <strong className="font-mono text-foreground tabular-nums">
                                        {filteredAndSortedAttributes.length}
                                    </strong>{' '}
                                    nitelik bulundu.
                                </span>
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    onClick={() => {
                                        setSearchTerm('');
                                        setTypeFilter('all');
                                    }}
                                    className="h-auto p-0 text-xs"
                                >
                                    Filtreleri Temizle
                                </Button>
                            </div>
                        )}

                        {/* Arama Sıfır Eşleşme Durumu */}
                        {filteredAndSortedAttributes.length === 0 ? (
                            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card p-8 text-center">
                                <SlidersHorizontal className="mb-2 size-8 text-muted-foreground/60" />
                                <h3 className="text-sm font-medium">
                                    Eşleşen nitelik bulunamadı
                                </h3>
                                <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                    Arama veya filtre kriterlerinize uygun
                                    nitelik bulunmuyor. Filtreleri
                                    temizleyebilir veya yeni bir nitelik
                                    tanımlayabilirsiniz.
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        setSearchTerm('');
                                        setTypeFilter('all');
                                    }}
                                    className="mt-3"
                                >
                                    Filtreleri Temizle
                                </Button>
                            </div>
                        ) : (
                            /* Veri Tablosu */
                            <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-1/4">
                                                Nitelik Adı / Kod
                                            </TableHead>
                                            <TableHead className="w-32">
                                                Tür
                                            </TableHead>
                                            <TableHead className="w-36 text-center">
                                                Varyant Tanımlayıcı
                                            </TableHead>
                                            <TableHead className="w-auto">
                                                Tanımlı Değerler
                                            </TableHead>
                                            <TableHead className="w-24 pr-4 text-right">
                                                İşlemler
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredAndSortedAttributes.map(
                                            (attr) => (
                                                <TableRow key={attr.id}>
                                                    <TableCell>
                                                        <div className="flex flex-col">
                                                            <span className="font-medium text-foreground">
                                                                {attr.name}
                                                            </span>
                                                            <span className="font-mono text-[11px] text-muted-foreground">
                                                                {attr.code}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            variant="outline"
                                                            className="text-[11px] font-normal"
                                                        >
                                                            {getTypeLabel(
                                                                attr.type,
                                                            )}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-center">
                                                        {attr.isVariantDefining ? (
                                                            <Badge
                                                                variant="default"
                                                                className="gap-1 text-[11px] font-normal shadow-2xs"
                                                            >
                                                                <Check className="size-3" />
                                                                Evet
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-xs text-muted-foreground">
                                                                Hayır (Özellik)
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex max-w-md flex-wrap items-center gap-1">
                                                            {attr.values
                                                                .length > 0 ? (
                                                                <>
                                                                    {attr.values
                                                                        .slice(
                                                                            0,
                                                                            5,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                v,
                                                                            ) => (
                                                                                <Badge
                                                                                    key={
                                                                                        v.id ??
                                                                                        v.value
                                                                                    }
                                                                                    variant="secondary"
                                                                                    className="h-5 px-1.5 text-[11px] font-normal"
                                                                                >
                                                                                    {
                                                                                        v.value
                                                                                    }
                                                                                </Badge>
                                                                            ),
                                                                        )}
                                                                    {attr.values
                                                                        .length >
                                                                        5 && (
                                                                        <span className="text-[11px] text-muted-foreground">
                                                                            +
                                                                            {attr
                                                                                .values
                                                                                .length -
                                                                                5}{' '}
                                                                            daha
                                                                        </span>
                                                                    )}
                                                                </>
                                                            ) : (
                                                                <span className="text-xs text-muted-foreground/60 italic">
                                                                    Değer
                                                                    tanımlanmamış
                                                                </span>
                                                            )}
                                                        </div>
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
                                                                                aria-label={`${attr.name} düzenle`}
                                                                            >
                                                                                <Link
                                                                                    href={edit(
                                                                                        {
                                                                                            attribute:
                                                                                                attr.id,
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
                                                                            aria-label={`${attr.name} sil`}
                                                                            onClick={() =>
                                                                                openDelete(
                                                                                    attr,
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

            {/* Nitelik Silme Onay Modalı */}
            <AttributeDeleteDialog
                attribute={attributeToDelete}
                onClose={() => setAttributeToDelete(null)}
            />
        </>
    );
}

AttributeIndex.layout = {
    breadcrumbs: [
        {
            title: 'Tanımlamalar',
            href: definitions(),
        },
        {
            title: 'Özel Alanlar',
            href: index(),
        },
    ],
};
