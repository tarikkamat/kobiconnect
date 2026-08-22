import { Deferred, Head, Link, router } from '@inertiajs/react';
import {
    Cable,
    CircleAlert,
    CircleCheck,
    CircleDashed,
    PackageOpen,
    Unlink,
} from 'lucide-react';
import {
    AlertsCard,
    AlertsCardSkeleton,
} from '@/components/dashboard/alerts-card';
import { ChannelShareChart } from '@/components/dashboard/channel-share-chart';
import type { ChannelShare } from '@/components/dashboard/channel-share-chart';
import { ChartSkeleton } from '@/components/dashboard/chart-kit';
import { KpiStrip, KpiStripSkeleton } from '@/components/dashboard/kpi-strip';
import type { Kpis } from '@/components/dashboard/kpi-strip';
import type { RowTone } from '@/components/dashboard/list-card';
import {
    ListCard,
    ListCardSkeleton,
    ListEmpty,
    ListRow,
    RowPill,
    RowText,
    toneIcon,
} from '@/components/dashboard/list-card';
import {
    OrderVolumeChart,
    OrderVolumeSkeleton,
} from '@/components/dashboard/order-volume-chart';
import type { OrderVolume } from '@/components/dashboard/order-volume-chart';
import { SalesTargetChart } from '@/components/dashboard/sales-target-chart';
import type { SalesTarget } from '@/components/dashboard/sales-target-chart';
import { SalesTrendChart } from '@/components/dashboard/sales-trend-chart';
import type { SalesTrend } from '@/components/dashboard/sales-trend-chart';
import { SyncThroughputChart } from '@/components/dashboard/sync-throughput-chart';
import type { SyncThroughput } from '@/components/dashboard/sync-throughput-chart';
import { WidgetCard, WidgetSkeleton } from '@/components/dashboard/widget-card';
import { DateRangePicker } from '@/components/date-range-picker';
import Heading from '@/components/heading';
import { MarketplaceAvatar } from '@/components/marketplace-avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as connectionsRoute } from '@/routes/apps';
import { index as ordersRoute } from '@/routes/orders';
import { create as productCreateRoute } from '@/routes/products';
import { index as stockRoute } from '@/routes/stock';
import { monitor as monitorRoute } from '@/routes/sync';
import { index as operationsRoute } from '@/routes/sync/operations';

type Range = { from: string; to: string };

type Props = {
    range: Range;
    setup: {
        hasConnections: boolean;
        hasProducts: boolean;
        hasOrders: boolean;
    };
    sales?: { count: number; total: string; today: number };
    unmatched?: { lines: number; orders: number };
    syncHealth?: {
        failedOperations: number;
        pendingOperations: number;
        runs: {
            id: number;
            connection: string | null;
            marketplace: string | null;
            resource: string;
            status: string;
            startedAt: string | null;
        }[];
    };
    criticalStock?: {
        count: number;
        items: {
            id: number;
            sku: string;
            product: string;
            available: number;
            safetyStock: number;
        }[];
    };
    connections?: {
        errored: number;
        items: {
            id: number;
            name: string;
            marketplace: string;
            status: string;
            statusLabel: string;
            checkedAt: string | null;
        }[];
    };
    /** Grafikler — MVP'de örnek veri (App\Support\DashboardDemoData). */
    kpis?: Kpis;
    salesTrend?: SalesTrend;
    channelShare?: ChannelShare;
    orderVolume?: OrderVolume;
    salesTarget?: SalesTarget;
    syncThroughput?: SyncThroughput;
};

const RUN_STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    completed: 'default',
    succeeded: 'default',
    running: 'secondary',
    failed: 'destructive',
};

/** Calisan baglanti satiri sakin durur; duraklatilan ve hatali one cikar. */
const CONNECTION_ROW_TONES: Record<string, RowTone> = {
    paused: 'warn',
    error: 'alert',
};

const unmatchedOrders = ordersRoute.url(undefined, {
    query: { unmatched: 1 },
});

/** Yerel gün — dönem hazır seçenekleri operatörün takvimine göre kurulur. */
function isoDay(daysAgo = 0): string {
    const date = new Date();
    date.setDate(date.getDate() - daysAgo);

    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

const PRESETS = [7, 30, 90];

/**
 * Dönem seçici: hazır 7/30/90 gün + serbest iki tarih. Varsayılanı sunucu
 * belirler (son 30 gün); burada tarih hesabı yalnızca hazır seçenekler için
 * yapılır. Seçim değişince aynı sayfa yeni dönemle yeniden istenir; deferred
 * widget'lar iskeletleriyle geri gelir.
 */
function RangePicker({ range }: { range: Range }) {
    const submit = (from: string, to: string): void => {
        if (from === '' || to === '') {
            return;
        }

        router.get(
            dashboard.url(undefined, { query: { from, to } }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const activePreset = PRESETS.find(
        (days) => range.to === isoDay(0) && range.from === isoDay(days - 1),
    );

    return (
        <div className="flex flex-wrap items-center gap-2">
            <ToggleGroup
                type="single"
                variant="outline"
                size="sm"
                value={activePreset === undefined ? '' : String(activePreset)}
                onValueChange={(value) => {
                    if (value !== '') {
                        submit(isoDay(Number(value) - 1), isoDay(0));
                    }
                }}
                aria-label="Hazır dönem"
            >
                {PRESETS.map((days) => (
                    <ToggleGroupItem key={days} value={String(days)}>
                        {days} gün
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            <DateRangePicker
                from={range.from}
                to={range.to}
                onSelect={submit}
            />
        </div>
    );
}

/** Beş özet kartın hepsi tek deferred grubunda: tek istek, tek yerleşim. */
const STAT_PROPS = [
    'sales',
    'unmatched',
    'syncHealth',
    'criticalStock',
    'connections',
];

export default function Dashboard({
    range,
    setup,
    sales,
    unmatched,
    syncHealth,
    criticalStock,
    connections,
    kpis,
    salesTrend,
    channelShare,
    orderVolume,
    salesTarget,
    syncThroughput,
}: Props) {
    /**
     * Yeni tenant "veri yok" gormemeli, NE YAPACAGINI gormeli. Bu uc adim
     * `exists()` ile senkron gelir; iskeletin arkasinda saklanmaz.
     */
    const steps = [
        {
            done: setup.hasConnections,
            title: 'Pazaryeri bağlantınızı ekleyin',
            description:
                'Satıcı bilgilerinizi girin; siparişler ve ürünler bu bağlantı üzerinden akar.',
            href: connectionsRoute().url,
        },
        {
            done: setup.hasProducts,
            title: 'Kataloğunuza ürün ekleyin',
            description:
                'Bir ürün en az bir varyantla doğar; stok ve fiyat varyanta bağlıdır.',
            href: productCreateRoute().url,
        },
        {
            done: setup.hasOrders,
            title: 'İlk senkronu çalıştırın',
            description:
                'Siparişler otomatik çekilir. Senkron monitöründen ilk koşuyu izleyebilirsiniz.',
            href: monitorRoute().url,
        },
    ];

    const pending = steps.filter((step) => !step.done);
    const started = setup.hasConnections || setup.hasProducts;

    return (
        <>
            <Head title="Gösterge Paneli" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading
                        title="Gösterge Paneli"
                        description="Seçili dönemde neyin aksadığını ve nereye bakmanız gerektiğini gösterir."
                    />
                    {started && <RangePicker range={range} />}
                </div>

                {pending.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {started
                                    ? 'Kurulumu tamamlayın'
                                    : 'Henüz başlamadınız'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {steps.map((step) => (
                                <div
                                    key={step.title}
                                    className="flex items-start gap-3"
                                >
                                    {step.done ? (
                                        <CircleCheck className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                    ) : (
                                        <CircleDashed className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
                                    )}
                                    <div>
                                        {step.done ? (
                                            <p className="font-medium text-muted-foreground line-through">
                                                {step.title}
                                            </p>
                                        ) : (
                                            <Link
                                                href={step.href}
                                                className="font-medium underline-offset-4 hover:underline"
                                            >
                                                {step.title}
                                            </Link>
                                        )}
                                        <p className="text-sm text-muted-foreground">
                                            {step.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {started && (
                    <>
                        {/* Uyarılar ve satış trendi yan yana: "neye
                            müdahale etmeliyim" ile "işler nereye gidiyor"
                            aynı ekran yüksekliğinde durur. */}
                        <div className="grid gap-4 lg:grid-cols-3">
                            <Deferred
                                data="salesTrend"
                                fallback={
                                    <ChartSkeleton className="lg:col-span-2" />
                                }
                            >
                                {salesTrend && (
                                    <SalesTrendChart
                                        trend={salesTrend}
                                        summary={`${sales?.count ?? 0} sipariş · ${sales?.total ?? ''} · bugün ${sales?.today ?? 0}`}
                                        className="lg:col-span-2"
                                    />
                                )}
                            </Deferred>

                            <Deferred
                                data={STAT_PROPS}
                                fallback={<AlertsCardSkeleton />}
                            >
                                <AlertsCard
                                    alerts={[
                                        {
                                            key: 'unmatched',
                                            label: 'Eşleşmemiş satır',
                                            detail:
                                                (unmatched?.lines ?? 0) === 0
                                                    ? 'Bütün satırlar katalogla eşleşti'
                                                    : `${unmatched?.orders} siparişte, eşlenmeden hazırlanamaz`,
                                            count: unmatched?.lines ?? 0,
                                            icon: Unlink,
                                            tone: 'warn',
                                            href: unmatchedOrders,
                                        },
                                        {
                                            key: 'operations',
                                            label: 'Başarısız işlem',
                                            detail: `${syncHealth?.pendingOperations ?? 0} işlem kuyrukta bekliyor`,
                                            count:
                                                syncHealth?.failedOperations ??
                                                0,
                                            icon: CircleAlert,
                                            tone: 'alert',
                                            href: operationsRoute().url,
                                        },
                                        {
                                            key: 'stock',
                                            label: 'Kritik stok',
                                            detail:
                                                (criticalStock?.count ?? 0) ===
                                                0
                                                    ? 'Emniyet stoğu altında varyant yok'
                                                    : 'Varyant emniyet stoğunun altında',
                                            count: criticalStock?.count ?? 0,
                                            icon: PackageOpen,
                                            tone: 'warn',
                                            href: stockRoute().url,
                                        },
                                        {
                                            key: 'connections',
                                            label: 'Bağlantı hatası',
                                            detail:
                                                (connections?.errored ?? 0) ===
                                                0
                                                    ? `${connections?.items.length ?? 0} bağlantı sorunsuz çalışıyor`
                                                    : 'Hatalı bağlantı sessizce durur',
                                            count: connections?.errored ?? 0,
                                            icon: Cable,
                                            tone: 'alert',
                                            href: connectionsRoute().url,
                                        },
                                    ]}
                                />
                            </Deferred>
                        </div>

                        <Deferred data="kpis" fallback={<KpiStripSkeleton />}>
                            {kpis && <KpiStrip kpis={kpis} />}
                        </Deferred>

                        <div className="grid gap-4 md:grid-cols-2">
                            <Deferred
                                data="channelShare"
                                fallback={<ChartSkeleton rows={3} />}
                            >
                                {channelShare && (
                                    <ChannelShareChart share={channelShare} />
                                )}
                            </Deferred>

                            <Deferred
                                data="salesTarget"
                                fallback={
                                    <ChartSkeleton height={240} rows={3} />
                                }
                            >
                                {salesTarget && (
                                    <SalesTargetChart target={salesTarget} />
                                )}
                            </Deferred>
                        </div>

                        {/* Detay listeleri: özet şeridindeki sayının arkasındaki
                            ilk beş satır. Üçü de aynı yükseklikte durur. */}
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <Deferred
                                data="syncHealth"
                                fallback={
                                    <WidgetSkeleton
                                        rows={4}
                                        className="min-h-56"
                                    />
                                }
                            >
                                <WidgetCard
                                    title="Son senkron koşuları"
                                    href={monitorRoute().url}
                                    className="min-h-56"
                                >
                                    {syncHealth?.runs.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            Henüz bir senkron koşusu yok.
                                        </p>
                                    ) : (
                                        <ul className="space-y-2.5 text-sm">
                                            {syncHealth?.runs.map((run) => (
                                                <li
                                                    key={run.id}
                                                    className="flex items-center gap-2.5"
                                                >
                                                    {run.marketplace && (
                                                        <MarketplaceAvatar
                                                            code={
                                                                run.marketplace
                                                            }
                                                            name={
                                                                run.connection ??
                                                                undefined
                                                            }
                                                            size="sm"
                                                        />
                                                    )}
                                                    <span className="min-w-0 flex-1 truncate">
                                                        {run.resource}
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {run.connection ??
                                                                'Bilinmeyen kanal'}{' '}
                                                            ·{' '}
                                                            {run.startedAt ??
                                                                '—'}
                                                        </span>
                                                    </span>
                                                    <Badge
                                                        variant={
                                                            RUN_STATUS_VARIANTS[
                                                                run.status
                                                            ] ?? 'outline'
                                                        }
                                                    >
                                                        {run.status}
                                                    </Badge>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </WidgetCard>
                            </Deferred>

                            <Deferred
                                data="criticalStock"
                                fallback={<ListCardSkeleton />}
                            >
                                <ListCard
                                    title="Kritik stok"
                                    href={stockRoute().url}
                                    badge={
                                        (criticalStock?.count ?? 0) > 0 ? (
                                            <Badge
                                                variant="secondary"
                                                className="tabular-nums"
                                            >
                                                {criticalStock?.count} varyant
                                            </Badge>
                                        ) : null
                                    }
                                >
                                    {criticalStock?.items.length ? (
                                        criticalStock.items.map((item) => {
                                            // Tukenmis stok satis durdurur;
                                            // emniyetin altina inmek sadece
                                            // siparis vermeyi hatirlatir.
                                            const tone =
                                                item.available <= 0
                                                    ? 'alert'
                                                    : 'warn';

                                            return (
                                                <ListRow
                                                    key={item.id}
                                                    tone={tone}
                                                    href={stockRoute().url}
                                                >
                                                    <PackageOpen
                                                        aria-hidden
                                                        className={cn(
                                                            'size-4 shrink-0',
                                                            toneIcon(tone),
                                                        )}
                                                    />
                                                    <RowText
                                                        title={item.product}
                                                        detail={item.sku}
                                                    />
                                                    <RowPill tone={tone}>
                                                        {item.available} /{' '}
                                                        {item.safetyStock}
                                                    </RowPill>
                                                </ListRow>
                                            );
                                        })
                                    ) : (
                                        <ListEmpty>
                                            Emniyet stokunun altına inen varyant
                                            yok.
                                        </ListEmpty>
                                    )}
                                </ListCard>
                            </Deferred>

                            <Deferred
                                data="connections"
                                fallback={
                                    <ListCardSkeleton className="md:col-span-2 xl:col-span-1" />
                                }
                            >
                                <ListCard
                                    title="Kanal bağlantıları"
                                    href={connectionsRoute().url}
                                    className="md:col-span-2 xl:col-span-1"
                                    badge={
                                        (connections?.errored ?? 0) > 0 ? (
                                            <Badge
                                                variant="secondary"
                                                className="tabular-nums"
                                            >
                                                {connections?.errored} hatalı
                                            </Badge>
                                        ) : null
                                    }
                                >
                                    {connections?.items.length ? (
                                        connections.items.map((connection) => {
                                            const tone =
                                                CONNECTION_ROW_TONES[
                                                    connection.status
                                                ];

                                            return (
                                                <ListRow
                                                    key={connection.id}
                                                    tone={tone}
                                                    href={
                                                        connectionsRoute().url
                                                    }
                                                >
                                                    <MarketplaceAvatar
                                                        code={
                                                            connection.marketplace
                                                        }
                                                        name={connection.name}
                                                        size="sm"
                                                    />
                                                    <RowText
                                                        title={connection.name}
                                                        detail={
                                                            connection.checkedAt ??
                                                            'Kontrol edilmedi'
                                                        }
                                                        muted={
                                                            tone === undefined
                                                        }
                                                    />
                                                    <RowPill tone={tone}>
                                                        {connection.statusLabel}
                                                    </RowPill>
                                                </ListRow>
                                            );
                                        })
                                    ) : (
                                        <ListEmpty>Bağlı kanal yok.</ListEmpty>
                                    )}
                                </ListCard>
                            </Deferred>
                        </div>

                        <div className="grid gap-4 xl:grid-cols-2">
                            <Deferred
                                data="orderVolume"
                                fallback={<OrderVolumeSkeleton />}
                            >
                                {orderVolume && (
                                    <OrderVolumeChart volume={orderVolume} />
                                )}
                            </Deferred>

                            <Deferred
                                data="syncThroughput"
                                fallback={<ChartSkeleton height={240} />}
                            >
                                {syncThroughput && (
                                    <SyncThroughputChart
                                        throughput={syncThroughput}
                                    />
                                )}
                            </Deferred>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Gösterge Paneli',
            href: dashboard(),
        },
    ],
};
