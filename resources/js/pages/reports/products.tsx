import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { ReportHeader } from '@/components/reports/report-header';
import type { ConnectionItem } from '@/components/reports/report-header';
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
import {
    index as reportsRoute,
    products as productsRoute,
} from '@/routes/reports';

type ProductRow = {
    sku: string;
    barcode: string | null;
    quantitySold: number;
    grossSales: string;
    rawGrossSales: number;
    commissionTotal: string;
    rawCommissionTotal: number;
    netEarnings: string;
    rawNetEarnings: number;
};

type Props = {
    range: { from: string; to: string };
    filters: { connection: number | null; search: string | null };
    connections: ConnectionItem[];
    products: ProductRow[];
};

export default function ReportsProducts({
    range,
    filters,
    connections,
    products,
}: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search ?? '');

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const query: Record<string, string | number> = {
            from: range.from,
            to: range.to,
        };

        if (filters.connection !== null && filters.connection !== undefined) {
            query.connection = filters.connection;
        }

        if (searchTerm.trim() !== '') {
            query.search = searchTerm.trim();
        }

        router.get(productsRoute.url(undefined, { query }), query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Ürün Satış Raporu" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <ReportHeader
                    title="Ürün Satış Performansı"
                    description="Seçilen dönemde en yüksek brüt ciro ve net gelir getiren ürünlerin detaylı analizi."
                    activeTab="products"
                    range={range}
                    filters={filters}
                    connections={connections}
                />

                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex flex-col gap-3 border-b border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-1">
                            <h3 className="text-sm font-semibold text-foreground">
                                Ürün Satış Listesi
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                SKU veya barkod bazında satılan adet ve kârlılık
                                dökümü.
                            </p>
                        </div>

                        <form
                            onSubmit={handleSearchSubmit}
                            className="flex items-center gap-2"
                        >
                            <div className="relative w-full sm:w-64">
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    placeholder="SKU veya barkod ara..."
                                    value={searchTerm}
                                    onChange={(e) =>
                                        setSearchTerm(e.target.value)
                                    }
                                    className="h-8 pl-8 text-xs"
                                />
                            </div>
                            <Button
                                type="submit"
                                size="sm"
                                variant="secondary"
                                className="h-8 text-xs"
                            >
                                Filtrele
                            </Button>
                        </form>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border hover:bg-transparent">
                                <TableHead>SKU / Ürün Kodu</TableHead>
                                <TableHead>Barkod</TableHead>
                                <TableHead className="text-right">
                                    Satılan Adet
                                </TableHead>
                                <TableHead className="text-right">
                                    Toplam Ciro (Brüt)
                                </TableHead>
                                <TableHead className="text-right">
                                    Komisyon Kesintisi
                                </TableHead>
                                <TableHead className="text-right">
                                    Net Satış Geliri
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {products.length > 0 ? (
                                products.map((p, idx) => (
                                    <TableRow key={`${p.sku}-${idx}`}>
                                        <TableCell className="font-mono text-xs font-semibold text-foreground">
                                            {p.sku}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-muted-foreground">
                                            {p.barcode || '—'}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs tabular-nums">
                                            {p.quantitySold} adet
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs font-medium text-foreground tabular-nums">
                                            {p.grossSales}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs text-rose-500 tabular-nums">
                                            {p.commissionTotal}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-xs font-semibold text-emerald-500 tabular-nums">
                                            {p.netEarnings}
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="py-12 text-center text-xs text-muted-foreground"
                                    >
                                        {filters.search
                                            ? 'Arama kriterine uygun ürün kaydı bulunamadı.'
                                            : 'Seçilen tarih aralığında ürün satış kaydı bulunamadı.'}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ReportsProducts.layout = {
    breadcrumbs: [
        {
            title: 'Raporlar',
            href: reportsRoute(),
        },
        {
            title: 'Ürün Satışları',
            href: productsRoute(),
        },
    ],
};
