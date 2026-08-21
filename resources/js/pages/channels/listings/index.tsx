import { Head, InfiniteScroll, Link, router, usePoll } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { index } from '@/routes/listings';
import { index as matches } from '@/routes/matches';
import { show as product } from '@/routes/products';
import { index as operations } from '@/routes/sync/operations';

type ListingRow = {
    id: number;
    connectionId: number;
    connection: string;
    productId: number;
    product: string;
    sku: string;
    remoteId: string | null;
    syncState: string;
    syncStateLabel: string;
    remoteStatus: string | null;
    remoteStatusLabel: string;
    lastPushedAt: string | null;
    degraded: boolean;
    degradedMessage: string | null;
    error: string | null;
};

type Filters = {
    status: string | null;
    connection: number | null;
    problem: boolean;
};

type Props = {
    listings: { data: ListingRow[] };
    filters: Filters;
    statuses: { value: string; label: string }[];
    connections: { id: number; name: string }[];
    degradedCount: number;
};

/** Radix Select bos deger kabul etmez; "hepsi" secenegi bu sabitle temsil edilir. */
const ALL = 'all';

const syncVariant = (
    state: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (state === 'failed') {
        return 'destructive';
    }

    return state === 'synced' ? 'secondary' : 'outline';
};

/**
 * Sorunun tarifi degil, DUZELTME YOLU. Her sorunlu satirin gidecegi tek bir yer
 * vardir; kullaniciya "bir sorun var" deyip birakmak destek kuyrugu uretir.
 */
const fix = (
    row: ListingRow,
): { label: string; href: ReturnType<typeof product> | string } | null => {
    if (row.remoteStatus === 'awaiting_match_decision') {
        return {
            label: 'Ön eşleşme kutusunda karar verin',
            href: matches.url(undefined, {
                query: { connection: row.connectionId },
            }),
        };
    }

    if (row.syncState === 'failed') {
        return {
            label: 'İşlem kuyruğunda yeniden deneyin',
            href: operations.url(undefined, {
                query: { connection: row.connectionId, status: 'failed' },
            }),
        };
    }

    if (row.degraded || row.remoteStatus === 'rejected') {
        return {
            label: 'Ürünü düzeltin',
            href: product({ product: row.productId }),
        };
    }

    return null;
};

/**
 * Varyant x kanal matrisi — FRONTEND-PLAN §1 "Kanallar › Listelemeler".
 *
 * `channel_listings` tablosu doluydu ama gorunmuyordu. Ekranin en onemli isi
 * `degraded` satirlari one cikarmak: pazaryeri fiyat bandi ihlalinde urunu
 * reddetmez, 0 fiyat / 0 stok ile CANLIYA cikarir (HEPSIBURADA.md §3 H6). Push
 * basarili, defter yesil, satis yok. Bu yuzden ayri bir rozet, ayri bir sayac
 * ve ust bantta bir uyari olarak durur.
 */
export default function ChannelListings({
    listings,
    filters,
    statuses,
    connections,
    degradedCount,
}: Props) {
    // Senkron durumu canli: push sonuclari uzakta, dakikalar sonra kapanir.
    // `reset` sart — <InfiniteScroll> birlestirilmis sayfalari aksi halde
    // yeniden ekler ve satirlar cogalir.
    usePoll(15000, {
        only: ['listings', 'degradedCount'],
        reset: ['listings'],
    });

    const apply = (changes: Partial<Filters>): void => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) =>
                    value !== null && value !== '' && value !== false,
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
            <Head title="Kanal Listelemeleri" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Kanal Listelemeleri"
                    description="Hangi varyant hangi kanalda, hangi durumda. Pazaryeri durumu ile bizim gönderim durumumuz ayrı iki eksendir."
                />

                {degradedCount > 0 && (
                    <Alert variant="destructive">
                        <TriangleAlert />
                        <AlertTitle>
                            {degradedCount} listeleme 0 fiyat / 0 stok ile
                            yayında
                        </AlertTitle>
                        <AlertDescription>
                            Pazaryeri bu ürünleri kabul etti ama fiyatı geçersiz
                            bulduğu için listelemeyi etkisiz açtı: sayfa açık,
                            satış imkânsız. Fiyatı düzeltip yeniden gönderin.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={filters.status ?? ALL}
                        onValueChange={(value) =>
                            apply({ status: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger className="w-52" aria-label="Durum">
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
                        <SelectTrigger className="w-52" aria-label="Kanal">
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
                        variant={filters.problem ? 'default' : 'outline'}
                        aria-pressed={filters.problem}
                        onClick={() => apply({ problem: !filters.problem })}
                    >
                        Yalnızca sorunlular
                    </Button>
                </div>

                {listings.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Bu filtreye uyan listeleme yok.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        {/* Sayfalama tiklamasi yok: <InfiniteScroll> sonraki sayfayi kendisi ekler. */}
                        <InfiniteScroll data="listings" buffer={300}>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Varyant</TableHead>
                                        <TableHead>Kanal</TableHead>
                                        <TableHead>Pazaryeri durumu</TableHead>
                                        <TableHead>Gönderim</TableHead>
                                        <TableHead>Son gönderim</TableHead>
                                        <TableHead>Uzak kimlik</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {listings.data.map((row) => {
                                        const path = fix(row);

                                        return (
                                            <TableRow
                                                key={row.id}
                                                className={
                                                    row.degraded
                                                        ? 'bg-destructive/5'
                                                        : undefined
                                                }
                                            >
                                                <TableCell>
                                                    <Link
                                                        href={product({
                                                            product:
                                                                row.productId,
                                                        })}
                                                        instant
                                                        className="font-medium underline-offset-4 hover:underline"
                                                    >
                                                        {row.product}
                                                    </Link>
                                                    <span className="block font-mono text-xs text-muted-foreground">
                                                        {row.sku}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    {row.connection}
                                                </TableCell>
                                                <TableCell>
                                                    <span className="flex flex-wrap gap-1">
                                                        <Badge variant="outline">
                                                            {
                                                                row.remoteStatusLabel
                                                            }
                                                        </Badge>
                                                        {row.degraded && (
                                                            <Badge variant="destructive">
                                                                0 fiyat / 0 stok
                                                            </Badge>
                                                        )}
                                                    </span>
                                                    {(row.error ??
                                                        row.degradedMessage) && (
                                                        <p className="mt-1 max-w-sm text-xs text-muted-foreground">
                                                            {row.error ??
                                                                row.degradedMessage}
                                                        </p>
                                                    )}
                                                    {path !== null && (
                                                        <Link
                                                            href={path.href}
                                                            className="mt-1 inline-block text-xs underline underline-offset-4"
                                                        >
                                                            {path.label}
                                                        </Link>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={syncVariant(
                                                            row.syncState,
                                                        )}
                                                    >
                                                        {row.syncStateLabel}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-xs text-muted-foreground">
                                                    {row.lastPushedAt ?? '—'}
                                                </TableCell>
                                                <TableCell className="max-w-40 truncate font-mono text-xs text-muted-foreground">
                                                    {row.remoteId ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        </InfiniteScroll>
                    </div>
                )}
            </div>
        </>
    );
}

ChannelListings.layout = {
    breadcrumbs: [
        {
            title: 'Kanal Listelemeleri',
            href: index(),
        },
    ],
};
