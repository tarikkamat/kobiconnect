import { Head, Link, router } from '@inertiajs/react';
import {
    Boxes,
    Building2,
    Check,
    ExternalLink,
    MapPin,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Star,
    Trash2,
    Warehouse,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { WarehouseDeleteDialog } from '@/components/inventory/warehouse-delete-dialog';
import type { WarehouseFormData } from '@/components/inventory/warehouse-form-dialog';
import { WarehouseFormDialog } from '@/components/inventory/warehouse-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { usePermission } from '@/hooks/use-permission';
import { index as stockIndex } from '@/routes/stock';
import { index, update } from '@/routes/warehouses';

type WarehouseRow = {
    id: number;
    name: string;
    code: string;
    isDefault: boolean;
    address: {
        line: string | null;
        city: string | null;
        district: string | null;
        postalCode: string | null;
    };
    itemCount: number;
    onHandTotal: number;
};

export default function WarehouseIndex({
    warehouses,
}: {
    warehouses: WarehouseRow[];
}) {
    const canManage = usePermission()('stock.manage');
    const [search, setSearch] = useState('');
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [editingWarehouse, setEditingWarehouse] =
        useState<WarehouseFormData | null>(null);
    const [deletingWarehouse, setDeletingWarehouse] =
        useState<WarehouseFormData | null>(null);

    // İstemci taraflı anlık arama / filtreleme
    const filteredWarehouses = useMemo(() => {
        const query = search.trim().toLowerCase();

        if (!query) {
            return warehouses;
        }

        return warehouses.filter((wh) => {
            const nameMatch = wh.name.toLowerCase().includes(query);
            const codeMatch = wh.code.toLowerCase().includes(query);
            const cityMatch = wh.address.city?.toLowerCase().includes(query);
            const districtMatch = wh.address.district
                ?.toLowerCase()
                .includes(query);
            const lineMatch = wh.address.line?.toLowerCase().includes(query);

            return (
                nameMatch ||
                codeMatch ||
                cityMatch ||
                districtMatch ||
                Boolean(lineMatch)
            );
        });
    }, [warehouses, search]);

    // Toplam eldeki stok ve varyant hesabı
    const totals = useMemo(() => {
        return warehouses.reduce(
            (acc, wh) => ({
                variants: acc.variants + wh.itemCount,
                onHand: acc.onHand + wh.onHandTotal,
            }),
            { variants: 0, onHand: 0 },
        );
    }, [warehouses]);

    const defaultWarehouse = useMemo(
        () => warehouses.find((wh) => wh.isDefault),
        [warehouses],
    );

    // Hızlı varsayılan depo yapma eylemi
    const handleSetDefault = (warehouse: WarehouseRow) => {
        if (warehouse.isDefault) {
            return;
        }

        router.patch(
            update.url({ warehouse: warehouse.id }),
            {
                name: warehouse.name,
                code: warehouse.code,
                address: warehouse.address,
                is_default: true,
            },
            {
                preserveScroll: true,
                onError: toastError,
            },
        );
    };

    return (
        <>
            <Head title="Depolar" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Üst Başlık ve Hızlı Eylemler */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Depolar"
                        description="Stok ve sipariş operasyonlarının yürütüldüğü fiziksel veya sanal depolar. En az bir depo bulunmalıdır."
                    />

                    <div className="flex shrink-0 items-center gap-2">
                        <PermissionButton
                            check={canManage}
                            onClick={() => setCreateDialogOpen(true)}
                            className="gap-1.5 shadow-sm"
                        >
                            <Plus className="size-4" />
                            Yeni Depo Ekle
                        </PermissionButton>
                    </div>
                </div>

                {/* Özet Şeridi */}
                {warehouses.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 border-y border-border/60 py-3 text-xs text-muted-foreground sm:text-sm">
                        <div className="flex items-center gap-1.5">
                            <Building2 className="size-4 text-muted-foreground/70" />
                            <span>Toplam:</span>
                            <span className="font-mono font-semibold text-foreground tabular-nums">
                                {warehouses.length} depo
                            </span>
                        </div>

                        <span className="text-border">•</span>

                        <div className="flex items-center gap-1.5">
                            <Star className="size-3.5 fill-amber-500/20 text-amber-500" />
                            <span>Varsayılan:</span>
                            <span className="font-medium text-foreground">
                                {defaultWarehouse?.name ?? '—'}
                            </span>
                        </div>

                        <span className="text-border">•</span>

                        <div className="flex items-center gap-1.5">
                            <Boxes className="size-4 text-muted-foreground/70" />
                            <span>Toplam Stok:</span>
                            <span className="font-mono font-semibold text-foreground tabular-nums">
                                {totals.onHand.toLocaleString('tr-TR')} adet
                            </span>
                        </div>
                    </div>
                )}

                {/* Arama ve Filtreleme */}
                {warehouses.length > 0 && (
                    <div className="flex items-center justify-between gap-3">
                        <div className="relative w-full max-w-sm">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                placeholder="Depo adı, kod veya şehir ara..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pr-8 pl-9"
                            />
                            {search && (
                                <button
                                    type="button"
                                    onClick={() => setSearch('')}
                                    className="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                    aria-label="Aramayı temizle"
                                >
                                    <X className="size-3.5" />
                                </button>
                            )}
                        </div>

                        {search && (
                            <div className="shrink-0 font-mono text-xs text-muted-foreground tabular-nums">
                                {filteredWarehouses.length} /{' '}
                                {warehouses.length} depo
                            </div>
                        )}
                    </div>
                )}

                {/* İçerik Görünümü */}
                {warehouses.length === 0 ? (
                    <EmptyState
                        icon={Warehouse}
                        title="Henüz depo tanımlanmamış"
                        description="Stok takibi yapabilmek ve siparişleri karşılayabilmek için en az bir depo gereklidir."
                        action={
                            <PermissionButton
                                check={canManage}
                                onClick={() => setCreateDialogOpen(true)}
                                className="gap-1.5"
                            >
                                <Plus className="size-4" />
                                İlk Depoyu Tanımla
                            </PermissionButton>
                        }
                    />
                ) : filteredWarehouses.length === 0 ? (
                    <EmptyState
                        icon={Search}
                        title="Eşleşen depo bulunamadı"
                        description="Arama kriterlerinize uygun bir depo kaydı bulunamadı."
                        action={
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setSearch('')}
                            >
                                Aramayı Temizle
                            </Button>
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-muted/40 hover:bg-muted/40">
                                    <TableHead className="w-[300px]">
                                        Depo Bilgisi
                                    </TableHead>
                                    <TableHead className="w-[120px]">
                                        Kod
                                    </TableHead>
                                    <TableHead>Konum / Adres</TableHead>
                                    <TableHead className="w-[130px] text-right">
                                        Varyant
                                    </TableHead>
                                    <TableHead className="w-[130px] text-right">
                                        Eldeki Stok
                                    </TableHead>
                                    <TableHead className="w-[110px] text-right" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredWarehouses.map((warehouse) => {
                                    const locationParts = [
                                        warehouse.address.district,
                                        warehouse.address.city,
                                    ].filter(Boolean);
                                    const locationText =
                                        locationParts.join(', ');

                                    return (
                                        <TableRow
                                            key={warehouse.id}
                                            className="group"
                                        >
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium text-foreground">
                                                            {warehouse.name}
                                                        </span>
                                                        {warehouse.isDefault && (
                                                            <Badge
                                                                variant="secondary"
                                                                className="gap-1 border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400"
                                                            >
                                                                <Star className="size-3 fill-current" />
                                                                Varsayılan
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    {warehouse.address.line && (
                                                        <span
                                                            className="max-w-[260px] truncate text-xs text-muted-foreground"
                                                            title={
                                                                warehouse
                                                                    .address
                                                                    .line
                                                            }
                                                        >
                                                            {
                                                                warehouse
                                                                    .address
                                                                    .line
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>

                                            <TableCell>
                                                <span className="inline-flex items-center rounded border border-border/60 bg-muted/60 px-2 py-0.5 font-mono text-xs text-foreground tabular-nums">
                                                    {warehouse.code}
                                                </span>
                                            </TableCell>

                                            <TableCell>
                                                {locationText ? (
                                                    <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                                                        <MapPin className="size-3.5 shrink-0 text-muted-foreground/70" />
                                                        {locationText}
                                                    </span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground/60">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>

                                            <TableCell className="text-right font-mono text-muted-foreground tabular-nums">
                                                {warehouse.itemCount.toLocaleString(
                                                    'tr-TR',
                                                )}
                                            </TableCell>

                                            <TableCell className="text-right font-mono font-medium text-foreground tabular-nums">
                                                {warehouse.onHandTotal.toLocaleString(
                                                    'tr-TR',
                                                )}
                                            </TableCell>

                                            <TableCell>
                                                <div className="flex items-center justify-end gap-1">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8 text-muted-foreground hover:text-foreground"
                                                            >
                                                                <Link
                                                                    href={stockIndex()}
                                                                >
                                                                    <Boxes className="size-4" />
                                                                </Link>
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Stok matrisini aç
                                                        </TooltipContent>
                                                    </Tooltip>

                                                    <PermissionButton
                                                        check={canManage}
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        aria-label={`${warehouse.name} düzenle`}
                                                        onClick={() =>
                                                            setEditingWarehouse(
                                                                warehouse,
                                                            )
                                                        }
                                                    >
                                                        <Pencil className="size-4" />
                                                    </PermissionButton>

                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8"
                                                                aria-label="Daha fazla seçenek"
                                                            >
                                                                <MoreHorizontal className="size-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            {!warehouse.isDefault && (
                                                                <DropdownMenuItem
                                                                    disabled={
                                                                        !canManage.allowed
                                                                    }
                                                                    onClick={() =>
                                                                        handleSetDefault(
                                                                            warehouse,
                                                                        )
                                                                    }
                                                                >
                                                                    <Check className="mr-2 size-4" />
                                                                    Varsayılan
                                                                    Yap
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={stockIndex()}
                                                                >
                                                                    <ExternalLink className="mr-2 size-4" />
                                                                    Stokları Gör
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                disabled={
                                                                    !canManage.allowed
                                                                }
                                                                onClick={() =>
                                                                    setEditingWarehouse(
                                                                        warehouse,
                                                                    )
                                                                }
                                                            >
                                                                <Pencil className="mr-2 size-4" />
                                                                Depoyu Düzenle
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                disabled={
                                                                    !canManage.allowed
                                                                }
                                                                onClick={() =>
                                                                    setDeletingWarehouse(
                                                                        warehouse,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="mr-2 size-4 text-destructive" />
                                                                Depoyu Sil
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            {/* Yeni Depo Ekleme / Düzenleme Dialogu */}
            <WarehouseFormDialog
                open={createDialogOpen || editingWarehouse !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setCreateDialogOpen(false);
                        setEditingWarehouse(null);
                    }
                }}
                warehouse={editingWarehouse}
            />

            {/* Depo Silme Onay Dialogu */}
            <WarehouseDeleteDialog
                open={deletingWarehouse !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeletingWarehouse(null);
                    }
                }}
                warehouse={deletingWarehouse}
                totalWarehousesCount={warehouses.length}
            />
        </>
    );
}

WarehouseIndex.layout = {
    breadcrumbs: [
        {
            title: 'Depolar',
            href: index(),
        },
    ],
};
