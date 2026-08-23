import { Head, Link, router, usePoll } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { usePermission } from '@/hooks/use-permission';
import { index, retry } from '@/routes/sync/operations';

type OperationRow = {
    id: number;
    connection: string;
    entity: string;
    operation: string;
    operationLabel: string;
    status: string;
    statusLabel: string;
    attempts: number;
    remoteBatchId: string | null;
    message: string | null;
    scheduledAt: string;
    sentAt: string | null;
    completedAt: string | null;
};

type Filters = {
    status: string | null;
    connection: number | null;
};

type Props = {
    operations: {
        data: OperationRow[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    filters: Filters;
    statuses: { value: string; label: string }[];
    connections: { id: number; name: string }[];
};

/** Radix Select bos deger kabul etmez; "hepsi" secenegi bu sabitle temsil edilir. */
const ALL = 'all';

const statusVariant = (
    status: string,
): 'success' | 'info' | 'destructive' | 'outline' => {
    if (status === 'failed') {
        return 'destructive';
    }

    if (status === 'in_flight') {
        return 'info';
    }

    return status === 'completed' ? 'success' : 'outline';
};

export default function OperationQueue({
    operations,
    filters,
    statuses,
    connections,
}: Props) {
    const canManage = usePermission()('channels.manage');
    const [selected, setSelected] = useState<number[]>([]);

    // Defter canli: gonderilen isler uzakta islenirken durum degisir.
    usePoll(5000, { only: ['operations'] }, { keepAlive: false });

    const apply = (changes: Partial<Filters>): void => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );

        router.get(
            index.url(undefined, { query }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const retryOperations = (ids: number[]): void => {
        router.post(
            retry.url(),
            ids.length > 0
                ? { ids, connection: filters.connection }
                : { connection: filters.connection },
            {
                preserveScroll: true,
                onError: toastError,
                onSuccess: () => setSelected([]),
            },
        );
    };

    const failed = operations.data.filter((row) => row.status === 'failed');

    return (
        <>
            <Head title="İşlem Kuyruğu" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="İşlem Kuyruğu"
                    description="channel_operations defteri: her satır beklemede → gönderildi → tamamlandı/başarısız yolunu izler. Pazaryeri sonucu okunana kadar iş açık kalır."
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) =>
                            apply({ status: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="Durum" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tüm durumlar</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.connection?.toString() ?? ALL}
                        onValueChange={(value) =>
                            apply({
                                connection:
                                    value === ALL ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger className="w-52">
                            <SelectValue placeholder="Kanal" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tüm kanallar</SelectItem>
                            {connections.map((connection) => (
                                <SelectItem
                                    key={connection.id}
                                    value={connection.id.toString()}
                                >
                                    {connection.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Button
                        variant="outline"
                        onClick={() => apply({ status: 'failed' })}
                    >
                        Yalnızca başarısızlar
                    </Button>

                    <PermissionButton
                        check={canManage}
                        variant="secondary"
                        disabled={selected.length === 0}
                        onClick={() => retryOperations(selected)}
                    >
                        <RotateCcw />
                        Seçilenleri yeniden dene (
                        <span className="font-mono tabular-nums">
                            {selected.length}
                        </span>
                        )
                    </PermissionButton>

                    <PermissionButton
                        check={canManage}
                        variant="ghost"
                        onClick={() => retryOperations([])}
                    >
                        Tüm başarısızları yeniden dene
                    </PermissionButton>
                </div>

                {operations.data.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-border p-8 text-center font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                        Bu filtreye uyan işlem yok.
                    </p>
                ) : (
                    <div className="overflow-hidden rounded-lg border border-border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">
                                        <Checkbox
                                            aria-label="Başarısızların hepsini seç"
                                            checked={
                                                failed.length > 0 &&
                                                selected.length ===
                                                    failed.length
                                            }
                                            disabled={failed.length === 0}
                                            onCheckedChange={(checked) =>
                                                setSelected(
                                                    checked === true
                                                        ? failed.map(
                                                              (row) => row.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                        />
                                    </TableHead>
                                    <TableHead>Kanal</TableHead>
                                    <TableHead>Kayıt</TableHead>
                                    <TableHead>İşlem</TableHead>
                                    <TableHead>Durum</TableHead>
                                    <TableHead className="text-right">
                                        Deneme
                                    </TableHead>
                                    <TableHead>Batch</TableHead>
                                    <TableHead>Zaman</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {operations.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell>
                                            <Checkbox
                                                aria-label={`${row.id} numaralı işlemi seç`}
                                                disabled={
                                                    row.status !== 'failed'
                                                }
                                                checked={selected.includes(
                                                    row.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    setSelected((current) =>
                                                        checked === true
                                                            ? [
                                                                  ...current,
                                                                  row.id,
                                                              ]
                                                            : current.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      row.id,
                                                              ),
                                                    )
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>{row.connection}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {row.entity}
                                        </TableCell>
                                        <TableCell>
                                            {row.operationLabel}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={statusVariant(
                                                    row.status,
                                                )}
                                            >
                                                {row.statusLabel}
                                            </Badge>
                                            {row.message && (
                                                <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                                    {row.message}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.attempts}
                                        </TableCell>
                                        <TableCell className="max-w-40 truncate text-xs text-muted-foreground tabular-nums">
                                            {row.remoteBatchId ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground tabular-nums">
                                            {row.completedAt ??
                                                row.sentAt ??
                                                row.scheduledAt}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <div className="flex items-center justify-between text-sm text-muted-foreground">
                    <span>
                        <span className="tabular-nums">
                            {operations.total}
                        </span>{' '}
                        işlem
                    </span>
                    <span className="flex gap-2">
                        {operations.prev_page_url && (
                            <Link
                                href={operations.prev_page_url}
                                preserveScroll
                                className="underline underline-offset-4"
                            >
                                Önceki
                            </Link>
                        )}
                        {operations.next_page_url && (
                            <Link
                                href={operations.next_page_url}
                                preserveScroll
                                className="underline underline-offset-4"
                            >
                                Sonraki
                            </Link>
                        )}
                    </span>
                </div>
            </div>
        </>
    );
}

OperationQueue.layout = {
    breadcrumbs: [
        {
            title: 'İşlem Kuyruğu',
            href: index(),
        },
    ],
};
