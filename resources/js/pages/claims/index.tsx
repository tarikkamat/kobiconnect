import { Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { Info, Search } from 'lucide-react';
import { ClaimStatusBadge } from '@/components/claims/claim-status-badge';
import Heading from '@/components/heading';
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
import { index, show } from '@/routes/claims';
import { show as orderShow } from '@/routes/orders';

type ClaimRow = {
    id: number;
    remoteClaimId: string;
    orderId: number;
    orderNumber: string;
    connection: string | null;
    status: string;
    statusLabel: string;
    externalStatus: string;
    reason: string | null;
    itemCount: number;
    quantity: number;
    openedAt: string | null;
};

type Filters = {
    search: string;
    status: string | null;
    connection: number | null;
};

type Props = {
    claims: { data: ClaimRow[] };
    filters: Filters;
    statuses: { value: string; label: string }[];
    connections: { id: number; name: string }[];
    actionableTotal: number;
};

/** Radix Select bos deger kabul etmez; "hepsi" secenegi bu sabitle temsil edilir. */
const ALL = 'all';

export default function ClaimIndex({
    claims,
    filters,
    statuses,
    connections,
    actionableTotal,
}: Props) {
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

    return (
        <>
            <Head title="İadeler" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="İadeler"
                    description="Pazaryerlerinden çekilen iade talepleri."
                />

                {/* Onay/red BILEREK yok: pazaryerine yazma demek, bu fazda
                    kapsam disi. Kullanici neyi yapamayacagini bilmeli. */}
                <div className="flex items-start gap-3 rounded-lg border px-4 py-3 text-sm text-muted-foreground">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <p>
                        Bu ekran yalnızca görünürlük sağlar. Talepleri onaylama
                        ve reddetme pazaryeri panelinden yapılır; buradan
                        yapılan bir işlem pazaryerine yansımaz.
                        {actionableTotal > 0 && (
                            <>
                                {' '}
                                Şu anda{' '}
                                <strong>
                                    {actionableTotal} talep aksiyon bekliyor
                                </strong>
                                .
                            </>
                        )}
                    </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-64 flex-1">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            aria-label="İade ara"
                            placeholder="İade veya sipariş numarası, Enter'a basın"
                            defaultValue={filters.search}
                            className="pl-8"
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    apply({
                                        search: event.currentTarget.value,
                                    });
                                }
                            }}
                        />
                    </div>

                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) =>
                            apply({ status: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger className="w-48" aria-label="Durum">
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
                        value={
                            filters.connection === null
                                ? ALL
                                : String(filters.connection)
                        }
                        onValueChange={(value) =>
                            apply({
                                connection:
                                    value === ALL ? null : Number(value),
                            })
                        }
                    >
                        <SelectTrigger className="w-44" aria-label="Kanal">
                            <SelectValue placeholder="Kanal" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tüm kanallar</SelectItem>
                            {connections.map((connection) => (
                                <SelectItem
                                    key={connection.id}
                                    value={String(connection.id)}
                                >
                                    {connection.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {claims.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Bu filtrelerle eşleşen iade talebi yok.
                    </p>
                ) : (
                    <div className="rounded-xl border">
                        <InfiniteScroll data="claims" buffer={300}>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>İade</TableHead>
                                        <TableHead>Sipariş</TableHead>
                                        <TableHead>Kanal</TableHead>
                                        <TableHead>Durum</TableHead>
                                        <TableHead>Sebep</TableHead>
                                        <TableHead className="text-right">
                                            Kalem / Adet
                                        </TableHead>
                                        <TableHead>Açılma</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {claims.data.map((claim) => (
                                        <TableRow key={claim.id}>
                                            <TableCell>
                                                <Link
                                                    href={show({
                                                        claim: claim.id,
                                                    })}
                                                    instant
                                                    className="font-medium underline-offset-4 hover:underline"
                                                >
                                                    {claim.remoteClaimId}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={orderShow({
                                                        order: claim.orderId,
                                                    })}
                                                    className="underline-offset-4 hover:underline"
                                                >
                                                    {claim.orderNumber}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {claim.connection ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <ClaimStatusBadge
                                                    status={claim.status}
                                                    label={claim.statusLabel}
                                                />
                                                <span className="block text-xs text-muted-foreground">
                                                    {claim.externalStatus}
                                                </span>
                                            </TableCell>
                                            <TableCell className="max-w-64 truncate text-muted-foreground">
                                                {claim.reason ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {claim.itemCount} /{' '}
                                                {claim.quantity}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {claim.openedAt ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </InfiniteScroll>
                    </div>
                )}
            </div>
        </>
    );
}

ClaimIndex.layout = {
    breadcrumbs: [
        {
            title: 'İadeler',
            href: index(),
        },
    ],
};
