import { Deferred, Head, Link } from '@inertiajs/react';
import { CircleCheck, CircleDashed, TriangleAlert } from 'lucide-react';
import { WidgetCard, WidgetSkeleton } from '@/components/dashboard/widget-card';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as connectionsRoute } from '@/routes/apps';
import { index as ordersRoute } from '@/routes/orders';
import { create as productCreateRoute } from '@/routes/products';
import { index as stockRoute } from '@/routes/stock';
import { monitor as monitorRoute } from '@/routes/sync';
import { index as operationsRoute } from '@/routes/sync/operations';

type Props = {
    setup: {
        hasConnections: boolean;
        hasProducts: boolean;
        hasOrders: boolean;
    };
    /** `count`/`total` secili donemin toplamidir, `today` yalnizca bugunun adedi. */
    sales?: { count: number; total: string; today: number };
    unmatched?: { lines: number; orders: number };
    syncHealth?: {
        failedOperations: number;
        pendingOperations: number;
        runs: {
            id: number;
            connection: string | null;
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
};

const RUN_STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    succeeded: 'default',
    running: 'secondary',
    failed: 'destructive',
};

const CONNECTION_STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    active: 'default',
    paused: 'secondary',
    error: 'destructive',
};

const unmatchedOrders = ordersRoute.url(undefined, {
    query: { unmatched: 1 },
});

export default function Dashboard({
    setup,
    sales,
    unmatched,
    syncHealth,
    criticalStock,
    connections,
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
                <Heading
                    title="Gösterge Paneli"
                    description="Bugün neyin aksadığını ve nereye bakmanız gerektiğini gösterir."
                />

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
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {/* Hepsi ayni deferred grubunda: istemci TEK ek istek atar. */}
                        <Deferred data="sales" fallback={<WidgetSkeleton />}>
                            <WidgetCard
                                title="Siparişler"
                                href={ordersRoute().url}
                            >
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Bugün
                                        </p>
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {sales?.today ?? 0}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">
                                            Seçili dönem
                                        </p>
                                        <p className="text-2xl font-semibold tabular-nums">
                                            {sales?.count ?? 0}
                                        </p>
                                        <p className="text-sm text-muted-foreground tabular-nums">
                                            {sales?.total}
                                        </p>
                                    </div>
                                </div>
                            </WidgetCard>
                        </Deferred>

                        <Deferred
                            data="unmatched"
                            fallback={<WidgetSkeleton />}
                        >
                            <WidgetCard
                                title="Eşleşmemiş sipariş satırı"
                                href={unmatchedOrders}
                                alert={(unmatched?.lines ?? 0) > 0}
                            >
                                <p className="text-2xl font-semibold tabular-nums">
                                    {unmatched?.lines ?? 0}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {(unmatched?.lines ?? 0) === 0
                                        ? 'Bütün sipariş satırları katalogla eşleşti.'
                                        : `${unmatched?.orders} siparişte, barkodu katalogda bulunamayan satır var; eşlenmeden hazırlanamaz.`}
                                </p>
                            </WidgetCard>
                        </Deferred>

                        <Deferred
                            data="syncHealth"
                            fallback={<WidgetSkeleton rows={3} />}
                        >
                            <WidgetCard
                                title="Senkron sağlığı"
                                href={operationsRoute().url}
                                alert={(syncHealth?.failedOperations ?? 0) > 0}
                            >
                                <div className="flex items-baseline gap-4">
                                    <span className="text-2xl font-semibold tabular-nums">
                                        {syncHealth?.failedOperations ?? 0}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        başarısız işlem ·{' '}
                                        {syncHealth?.pendingOperations ?? 0}{' '}
                                        kuyrukta
                                    </span>
                                </div>

                                {syncHealth?.runs.length === 0 ? (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Henüz bir senkron koşusu yok.
                                    </p>
                                ) : (
                                    <ul className="mt-3 space-y-1 text-sm">
                                        {syncHealth?.runs.map((run) => (
                                            <li
                                                key={run.id}
                                                className="flex items-center justify-between gap-2"
                                            >
                                                <span className="truncate">
                                                    {run.connection ??
                                                        'Bilinmeyen kanal'}{' '}
                                                    · {run.resource}
                                                </span>
                                                <span className="flex shrink-0 items-center gap-2">
                                                    <span className="text-xs text-muted-foreground tabular-nums">
                                                        {run.startedAt ?? '—'}
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
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </WidgetCard>
                        </Deferred>

                        <Deferred
                            data="criticalStock"
                            fallback={<WidgetSkeleton rows={3} />}
                        >
                            <WidgetCard
                                title="Kritik stok"
                                href={stockRoute().url}
                                alert={(criticalStock?.count ?? 0) > 0}
                            >
                                <p className="text-2xl font-semibold tabular-nums">
                                    {criticalStock?.count ?? 0}
                                </p>
                                {criticalStock?.count === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Emniyet stokunun altına inen varyant
                                        yok.
                                    </p>
                                ) : (
                                    <ul className="mt-3 space-y-1 text-sm">
                                        {criticalStock?.items.map((item) => (
                                            <li
                                                key={item.id}
                                                className="flex items-center justify-between gap-2"
                                            >
                                                <span className="truncate">
                                                    {item.product}{' '}
                                                    <span className="text-muted-foreground">
                                                        {item.sku}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 tabular-nums">
                                                    {item.available} /{' '}
                                                    {item.safetyStock}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </WidgetCard>
                        </Deferred>

                        <Deferred
                            data="connections"
                            fallback={<WidgetSkeleton rows={3} />}
                        >
                            <WidgetCard
                                title="Kanal bağlantıları"
                                href={connectionsRoute().url}
                                alert={(connections?.errored ?? 0) > 0}
                            >
                                {connections?.items.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Bağlı kanal yok.
                                    </p>
                                ) : (
                                    <ul className="space-y-1 text-sm">
                                        {connections?.items.map(
                                            (connection) => (
                                                <li
                                                    key={connection.id}
                                                    className="flex items-center justify-between gap-2"
                                                >
                                                    <span className="truncate">
                                                        {connection.name}
                                                    </span>
                                                    <span className="flex shrink-0 items-center gap-2">
                                                        <span className="text-xs text-muted-foreground tabular-nums">
                                                            {connection.checkedAt ??
                                                                'Kontrol edilmedi'}
                                                        </span>
                                                        <Badge
                                                            variant={
                                                                CONNECTION_STATUS_VARIANTS[
                                                                    connection
                                                                        .status
                                                                ] ?? 'outline'
                                                            }
                                                        >
                                                            {
                                                                connection.statusLabel
                                                            }
                                                        </Badge>
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                                {(connections?.errored ?? 0) > 0 && (
                                    <p className="mt-3 flex items-start gap-2 text-sm text-amber-700 dark:text-amber-300">
                                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                                        Hata veren bağlantı sessizce durur;
                                        kimlik bilgilerini kontrol edin.
                                    </p>
                                )}
                            </WidgetCard>
                        </Deferred>
                    </div>
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
