import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    BadgePercent,
    Check,
    Coins,
    DollarSign,
    Pencil,
    RefreshCw,
    Search,
    Sliders,
    X,
} from 'lucide-react';
import { useState } from 'react';
import PriceListController from '@/actions/App/Http/Controllers/Catalog/PriceListController';
import { toastError } from '@/components/catalog/toast-error';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';

type ItemData = {
    id: number;
    variantId: number;
    sku: string;
    barcode: string | null;
    productName: string;
    listPrice: number;
    listPriceFormatted: string;
    salePrice: number | null;
    salePriceFormatted: string | null;
    currency: string;
};

type PriceListData = {
    id: number;
    name: string;
    type: 'manual' | 'currency' | 'dynamic';
    typeLabel: string;
    sourceCurrency: string;
    targetCurrency: string;
    exchangeRate: number | null;
    roundingMethod: string;
    roundingMethodLabel: string;
    isActive: boolean;
    description: string | null;
    createdAt: string;
    rules: {
        id: number;
        field: string;
        fieldLabel: string;
        conditionValue: any;
        adjustmentType: string;
        adjustmentTypeLabel: string;
        adjustmentValue: number;
        position: number;
    }[];
};

export default function PriceListShow({
    priceList,
    items,
    filters = { search: '' },
}: {
    priceList: PriceListData;
    items: {
        data: ItemData[];
        links: any[];
        total: number;
    };
    filters: { search?: string };
}) {
    const canManage = usePermission()('catalog.manage');

    const [search, setSearch] = useState(filters.search ?? '');
    const [regenerating, setRegenerating] = useState(false);

    // Satır içi düzenleme state'i
    const [editingItemId, setEditingItemId] = useState<number | null>(null);
    const [editingListPrice, setEditingListPrice] = useState<string>('');
    const [editingSalePrice, setEditingSalePrice] = useState<string>('');
    const [savingItem, setSavingItem] = useState(false);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            PriceListController.show.url({ priceList: priceList.id }),
            { search: search.trim() || undefined },
            { preserveState: true, replace: true },
        );
    };

    const handleRegenerate = () => {
        setRegenerating(true);
        router.post(
            PriceListController.regenerate.url({ priceList: priceList.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRegenerating(false),
            },
        );
    };

    const startEditing = (item: ItemData) => {
        setEditingItemId(item.id);
        setEditingListPrice(String(item.listPrice));
        setEditingSalePrice(
            item.salePrice !== null ? String(item.salePrice) : '',
        );
    };

    const cancelEditing = () => {
        setEditingItemId(null);
        setEditingListPrice('');
        setEditingSalePrice('');
    };

    const saveEditing = async (item: ItemData) => {
        setSavingItem(true);

        try {
            const res = await fetch(
                `/catalog/price-lists/${priceList.id}/items/${item.id}`,
                {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN':
                            (
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                ) as HTMLMetaElement
                            )?.content ?? '',
                    },
                    body: JSON.stringify({
                        list_price: parseFloat(editingListPrice) || 0,
                        sale_price: editingSalePrice
                            ? parseFloat(editingSalePrice)
                            : null,
                    }),
                },
            );

            const json = await res.json();

            if (res.ok && json.success) {
                // UI'ı güncelle
                item.listPrice = json.item.list_price;
                item.listPriceFormatted = json.item.list_price_formatted;
                item.salePrice = json.item.sale_price;
                item.salePriceFormatted = json.item.sale_price_formatted;
                cancelEditing();
            } else {
                toastError(json.errors || { message: 'Fiyat kaydedilemedi.' });
            }
        } catch (err: any) {
            toastError({ message: err.message });
        } finally {
            setSavingItem(false);
        }
    };

    return (
        <>
            <Head title={`${priceList.name} - Fiyat Listesi`} />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="-ml-2 gap-1.5"
                    >
                        <Link href={PriceListController.index.url()}>
                            <ArrowLeft className="size-4" />
                            Tüm Fiyat Listeleri
                        </Link>
                    </Button>
                </div>

                {/* Üst Başlık & Aksiyonlar */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            {priceList.type === 'currency' ? (
                                <Coins className="size-5 text-amber-500" />
                            ) : priceList.type === 'dynamic' ? (
                                <BadgePercent className="size-5 text-blue-500" />
                            ) : (
                                <Sliders className="size-5 text-emerald-500" />
                            )}
                            <Heading
                                title={priceList.name}
                                description={
                                    priceList.description ||
                                    'Bu listedeki fiyatlar seçilen satış kanalı veya para birimi için geçerlidir.'
                                }
                            />
                        </div>

                        <div className="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <Badge variant="outline" className="text-[11px]">
                                {priceList.typeLabel}
                            </Badge>
                            <span>•</span>
                            <span className="font-mono font-semibold text-foreground">
                                {priceList.type === 'currency'
                                    ? `${priceList.sourceCurrency} → ${priceList.targetCurrency} (Kur: ${priceList.exchangeRate})`
                                    : priceList.targetCurrency}
                            </span>
                            <span>•</span>
                            <span>{items.total} kalem</span>
                            <span>•</span>
                            <span>Oluşturulma: {priceList.createdAt}</span>
                        </div>
                    </div>

                    {priceList.type !== 'manual' && canManage && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleRegenerate}
                            disabled={regenerating}
                            className="gap-1.5 self-start sm:self-auto"
                        >
                            <RefreshCw
                                className={`size-4 ${regenerating ? 'animate-spin' : ''}`}
                            />
                            Fiyatları Yeniden Hesapla
                        </Button>
                    )}
                </div>

                {/* Dinamik Liste Kuralları Özeti (varsa) */}
                {priceList.type === 'dynamic' && priceList.rules.length > 0 && (
                    <Card>
                        <CardHeader className="px-4 py-3">
                            <CardTitle className="flex items-center gap-2 text-xs font-semibold">
                                <BadgePercent className="size-3.5 text-blue-500" />
                                Tanımlı Fiyatlama Kuralları (
                                {priceList.rules.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 py-2">
                            <div className="flex flex-wrap gap-2 text-xs">
                                {priceList.rules.map((r, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-1.5 rounded-md border border-border bg-muted/30 p-2"
                                    >
                                        <span className="font-medium text-foreground">
                                            {r.fieldLabel}
                                        </span>
                                        <span className="text-muted-foreground">
                                            →
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className="font-mono text-xs"
                                        >
                                            {r.adjustmentType === 'percentage'
                                                ? `${r.adjustmentValue > 0 ? '+' : ''}${r.adjustmentValue}%`
                                                : `${r.adjustmentValue > 0 ? '+' : ''}${r.adjustmentValue} ₺`}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Kalemler Tablosu ve Arama */}
                <div className="space-y-4">
                    <form
                        onSubmit={handleSearch}
                        className="flex max-w-md gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Ürün adı, SKU veya barkod ara..."
                                className="h-9 pl-8.5"
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="secondary"
                            size="sm"
                            className="h-9"
                        >
                            Ara
                        </Button>
                    </form>

                    {items.data.length === 0 ? (
                        <EmptyState
                            icon={DollarSign}
                            title="Fiyatlandırılmış ürün bulunamadı"
                            description="Aramanızla eşleşen veya bu listeye eklenmiş bir ürün varyantı bulunmuyor."
                        />
                    ) : (
                        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-1/3">
                                            Ürün & Varyant
                                        </TableHead>
                                        <TableHead className="w-1/4">
                                            SKU / Barkod
                                        </TableHead>
                                        <TableHead className="w-36 text-right">
                                            Liste Fiyatı
                                        </TableHead>
                                        <TableHead className="w-36 text-right">
                                            İndirimli Fiyat
                                        </TableHead>
                                        {priceList.type === 'manual' &&
                                            canManage && (
                                                <TableHead className="w-20 pr-4 text-right">
                                                    İşlem
                                                </TableHead>
                                            )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {items.data.map((item) => {
                                        const isEditingThis =
                                            editingItemId === item.id;

                                        return (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <span className="font-medium text-foreground">
                                                        {item.productName}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="font-mono text-xs text-muted-foreground">
                                                        {item.sku}
                                                    </div>
                                                    {item.barcode && (
                                                        <div className="font-mono text-[11px] text-muted-foreground/70">
                                                            {item.barcode}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {isEditingThis ? (
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            value={
                                                                editingListPrice
                                                            }
                                                            onChange={(e) =>
                                                                setEditingListPrice(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="ml-auto h-8 w-28 text-right font-mono text-xs"
                                                            autoFocus
                                                        />
                                                    ) : (
                                                        <span className="font-mono text-sm font-semibold tabular-nums">
                                                            {
                                                                item.listPriceFormatted
                                                            }
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {isEditingThis ? (
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            value={
                                                                editingSalePrice
                                                            }
                                                            onChange={(e) =>
                                                                setEditingSalePrice(
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Opsiyonel"
                                                            className="ml-auto h-8 w-28 text-right font-mono text-xs"
                                                        />
                                                    ) : item.salePriceFormatted ? (
                                                        <span className="font-mono text-sm font-semibold text-emerald-600 tabular-nums dark:text-emerald-400">
                                                            {
                                                                item.salePriceFormatted
                                                            }
                                                        </span>
                                                    ) : (
                                                        <span className="font-mono text-xs text-muted-foreground">
                                                            -
                                                        </span>
                                                    )}
                                                </TableCell>
                                                {priceList.type === 'manual' &&
                                                    canManage && (
                                                        <TableCell className="pr-4 text-right">
                                                            {isEditingThis ? (
                                                                <div className="flex items-center justify-end gap-1">
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-7 text-emerald-600 hover:text-emerald-700"
                                                                        onClick={() =>
                                                                            saveEditing(
                                                                                item,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            savingItem
                                                                        }
                                                                    >
                                                                        <Check className="size-3.5" />
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-7 text-muted-foreground"
                                                                        onClick={
                                                                            cancelEditing
                                                                        }
                                                                        disabled={
                                                                            savingItem
                                                                        }
                                                                    >
                                                                        <X className="size-3.5" />
                                                                    </Button>
                                                                </div>
                                                            ) : (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-7 text-muted-foreground hover:text-foreground"
                                                                    onClick={() =>
                                                                        startEditing(
                                                                            item,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil className="size-3.5" />
                                                                </Button>
                                                            )}
                                                        </TableCell>
                                                    )}
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
