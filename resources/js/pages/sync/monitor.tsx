import { Head, Link, usePoll } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { monitor } from '@/routes/sync';
import { index as operations } from '@/routes/sync/operations';

type RunRow = {
    id: number;
    connectionId: number;
    connection: string;
    marketplace: string;
    resource: string;
    resourceLabel: string;
    direction: string;
    status: string;
    statusLabel: string;
    startedAt: string | null;
    durationSeconds: number | null;
    items: number;
    pages: number;
    error: string | null;
    watermark: string | null;
};

type FailedRun = {
    id: number;
    connection: string;
    resource: string;
    startedAt: string | null;
    message: string | null;
};

type Props = {
    runs: RunRow[];
    ledger: Record<string, number>;
    failedRuns: FailedRun[];
};

const LEDGER_LABELS: Record<string, string> = {
    pending: 'Beklemede',
    in_flight: 'Gönderildi',
    completed: 'Tamamlandı',
    failed: 'Başarısız',
};

const statusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'failed') {
        return 'destructive';
    }

    return status === 'running' ? 'default' : 'secondary';
};

export default function SyncMonitor({ runs, ledger, failedRuns }: Props) {
    // Pazaryeri sonuclari asenkron gelir; ekran canli olmali. `keepAlive`
    // varsayilan olarak kapali: sekme gorunmez oldugunda polling durur.
    usePoll(
        10000,
        { only: ['runs', 'ledger', 'failedRuns'] },
        { keepAlive: false },
    );

    return (
        <>
            <Head title="Senkron Monitörü" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Senkron Monitörü"
                    description="Kanal ve kaynak bazında son senkron çalışmaları. Sayfa açıkken kendini yeniler."
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {Object.entries(LEDGER_LABELS).map(([key, label]) => (
                        <div key={key} className="rounded-xl border p-4">
                            <p className="text-sm text-muted-foreground">
                                {label}
                            </p>
                            <p className="text-2xl font-semibold tabular-nums">
                                {ledger[key] ?? 0}
                            </p>
                        </div>
                    ))}
                </div>

                <p className="text-sm text-muted-foreground">
                    İşlem defterinin tamamı için{' '}
                    <Link
                        href={operations()}
                        className="underline underline-offset-4"
                        prefetch
                    >
                        işlem kuyruğuna
                    </Link>{' '}
                    bakın.
                </p>

                {runs.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Henüz senkron çalışması yok.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kanal</TableHead>
                                    <TableHead>Kaynak</TableHead>
                                    <TableHead>Durum</TableHead>
                                    <TableHead>Başlangıç</TableHead>
                                    <TableHead className="text-right">
                                        Süre
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Kayıt
                                    </TableHead>
                                    <TableHead>İmleç</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {runs.map((run) => (
                                    <TableRow key={run.id}>
                                        <TableCell>{run.connection}</TableCell>
                                        <TableCell>
                                            {run.resourceLabel}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={statusVariant(
                                                    run.status,
                                                )}
                                            >
                                                {run.statusLabel}
                                            </Badge>
                                            {run.error && (
                                                <p className="mt-1 text-xs text-destructive">
                                                    {run.error}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {run.startedAt ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {run.durationSeconds === null
                                                ? '—'
                                                : `${run.durationSeconds} sn`}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {run.items}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {run.watermark ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {failedRuns.length > 0 && (
                    <div className="rounded-xl border border-destructive/40 p-4">
                        <h2 className="text-sm font-medium">
                            Son başarısız çalışmalar
                        </h2>
                        <ul className="mt-2 space-y-1 text-sm text-muted-foreground">
                            {failedRuns.map((run) => (
                                <li key={run.id}>
                                    <span className="text-foreground">
                                        {run.connection} · {run.resource}
                                    </span>{' '}
                                    {run.startedAt} — {run.message ?? 'Hata'}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </>
    );
}

SyncMonitor.layout = {
    breadcrumbs: [
        {
            title: 'Senkron Monitörü',
            href: monitor(),
        },
    ],
};
