import { Link, router } from '@inertiajs/react';
import { DollarSign, Layers, Package, Scale, ShoppingCart } from 'lucide-react';
import { DateRangePicker } from '@/components/date-range-picker';
import { MarketplaceAvatar } from '@/components/marketplace-avatar';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import {
    channels as channelsRoute,
    index as reportsRoute,
    orders as ordersRoute,
    penalties as penaltiesRoute,
    products as productsRoute,
} from '@/routes/reports';

export type ConnectionItem = {
    id: number;
    name: string;
    marketplace: string;
};

type Props = {
    title: string;
    description: string;
    activeTab: 'index' | 'channels' | 'products' | 'penalties' | 'orders';
    range: { from: string; to: string };
    filters: { connection: number | null; search?: string | null };
    connections: ConnectionItem[];
};

const ALL_CONNECTIONS = 'all';

function formatIsoDate(d: Date): string {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function ReportHeader({
    title,
    description,
    activeTab,
    range,
    filters,
    connections,
}: Props) {
    const buildQuery = (
        newRange?: { from: string; to: string },
        newConnection?: number | null,
        newSearch?: string | null,
    ) => {
        const targetRange = newRange ?? range;
        const targetConnection =
            newConnection !== undefined ? newConnection : filters.connection;
        const targetSearch =
            newSearch !== undefined ? newSearch : (filters.search ?? null);

        const query: Record<string, string | number> = {
            from: targetRange.from,
            to: targetRange.to,
        };

        if (targetConnection !== null && targetConnection !== undefined) {
            query.connection = targetConnection;
        }

        if (
            targetSearch !== null &&
            targetSearch !== undefined &&
            targetSearch !== ''
        ) {
            query.search = targetSearch;
        }

        return query;
    };

    const applyFilters = (
        newRange?: { from: string; to: string },
        newConnection?: number | null,
        newSearch?: string | null,
    ) => {
        const query = buildQuery(newRange, newConnection, newSearch);
        const url =
            typeof window !== 'undefined'
                ? window.location.pathname
                : reportsRoute.url(undefined, { query });

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handlePreset = (
        preset: 'today' | 'last7' | 'thisMonth' | 'last30' | 'thisYear',
    ) => {
        const now = new Date();
        const to = formatIsoDate(now);
        let from = to;

        if (preset === 'today') {
            from = to;
        } else if (preset === 'last7') {
            const d = new Date(
                now.getFullYear(),
                now.getMonth(),
                now.getDate() - 7,
            );
            from = formatIsoDate(d);
        } else if (preset === 'thisMonth') {
            const d = new Date(now.getFullYear(), now.getMonth(), 1);
            from = formatIsoDate(d);
        } else if (preset === 'last30') {
            const d = new Date(
                now.getFullYear(),
                now.getMonth(),
                now.getDate() - 30,
            );
            from = formatIsoDate(d);
        } else if (preset === 'thisYear') {
            const d = new Date(now.getFullYear(), 0, 1);
            from = formatIsoDate(d);
        }

        applyFilters({ from, to });
    };

    const currentQuery = buildQuery();

    const navTabs = [
        {
            id: 'index',
            title: 'Finans ve Satış',
            icon: DollarSign,
            href: reportsRoute.url(undefined, { query: currentQuery }),
        },
        {
            id: 'channels',
            title: 'Kanal Dağılımı',
            icon: Layers,
            href: channelsRoute.url(undefined, { query: currentQuery }),
        },
        {
            id: 'products',
            title: 'Ürün Satışları',
            icon: Package,
            href: productsRoute.url(undefined, { query: currentQuery }),
        },
        {
            id: 'penalties',
            title: 'Kargo & Cezalar',
            icon: Scale,
            href: penaltiesRoute.url(undefined, { query: currentQuery }),
        },
        {
            id: 'orders',
            title: 'Sipariş Statüleri',
            icon: ShoppingCart,
            href: ordersRoute.url(undefined, { query: currentQuery }),
        },
    ] as const;

    return (
        <div className="flex flex-col gap-5">
            {/* Top Heading & Filter Controls */}
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-1">
                    <h1 className="text-xl font-bold tracking-tight text-foreground">
                        {title}
                    </h1>
                    {description && (
                        <p className="max-w-2xl text-xs leading-relaxed text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-2 sm:pt-0.5">
                    {/* Channel Filter */}
                    <Select
                        value={
                            filters.connection !== null
                                ? String(filters.connection)
                                : ALL_CONNECTIONS
                        }
                        onValueChange={(val) => {
                            applyFilters(
                                undefined,
                                val === ALL_CONNECTIONS ? null : Number(val),
                            );
                        }}
                    >
                        <SelectTrigger className="h-9 w-[190px] text-xs">
                            <SelectValue placeholder="Tüm Kanallar" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL_CONNECTIONS}>
                                Tüm Kanallar ({connections.length})
                            </SelectItem>
                            {connections.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    <div className="flex items-center gap-2">
                                        <MarketplaceAvatar
                                            code={c.marketplace}
                                            className="size-4"
                                        />
                                        <span className="truncate">
                                            {c.name}
                                        </span>
                                    </div>
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Date Range Picker */}
                    <DateRangePicker
                        from={range.from}
                        to={range.to}
                        onSelect={(from, to) => applyFilters({ from, to })}
                    />
                </div>
            </div>

            {/* Quick Date Presets */}
            <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                <span className="mr-1 font-medium">Hızlı Aralık:</span>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2.5 text-xs"
                    onClick={() => handlePreset('today')}
                >
                    Bugün
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2.5 text-xs"
                    onClick={() => handlePreset('last7')}
                >
                    Son 7 Gün
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2.5 text-xs"
                    onClick={() => handlePreset('thisMonth')}
                >
                    Bu Ay
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2.5 text-xs"
                    onClick={() => handlePreset('last30')}
                >
                    Son 30 Gün
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="h-7 px-2.5 text-xs"
                    onClick={() => handlePreset('thisYear')}
                >
                    Bu Yıl
                </Button>
            </div>

            {/* Report Screen Navigation Tabs */}
            <div className="flex items-center gap-1 overflow-x-auto border-b border-border pb-1">
                {navTabs.map((tab) => {
                    const isActive = activeTab === tab.id;
                    const Icon = tab.icon;

                    return (
                        <Link
                            key={tab.id}
                            href={tab.href}
                            prefetch
                            className={cn(
                                'flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
                                isActive
                                    ? 'bg-primary/10 font-semibold text-primary'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            )}
                        >
                            <Icon className="size-3.5" />
                            <span>{tab.title}</span>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
