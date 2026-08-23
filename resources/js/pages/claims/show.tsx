import { Head, Link } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { ClaimStatusBadge } from '@/components/claims/claim-status-badge';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/claims';
import { show as orderShow } from '@/routes/orders';

type Props = {
    claim: {
        id: number;
        remoteClaimId: string;
        status: string;
        statusLabel: string;
        externalStatus: string;
        reason: string | null;
        openedAt: string | null;
    };
    order: {
        id: number;
        orderNumber: string;
        packageId: string;
        connection: string | null;
        placedAt: string | null;
    };
    items: {
        id: number;
        remoteItemId: string;
        quantity: number;
        status: string;
        statusLabel: string;
        externalStatus: string;
        reason: string | null;
        sku: string | null;
        barcode: string | null;
        unitPrice: string | null;
    }[];
};

/** `mono`: sayisal/kimlik degerler mono ile hizalanir — DESIGN.md §2. */
function Field({
    label,
    children,
    mono = false,
}: {
    label: string;
    children: string;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className={mono ? 'text-sm tabular-nums' : 'text-sm'}>
                {children}
            </dd>
        </div>
    );
}

export default function ClaimShow({ claim, order, items }: Props) {
    return (
        <>
            <Head title={`İade ${claim.remoteClaimId}`} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex items-center gap-3">
                    <Heading title={`İade ${claim.remoteClaimId}`} />
                    <ClaimStatusBadge
                        status={claim.status}
                        label={claim.statusLabel}
                    />
                </div>

                <div className="flex items-start gap-3 rounded-lg border border-border px-4 py-3 text-sm text-muted-foreground">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <p>
                        Onay ve red pazaryeri panelinden yapılır; bu ekran
                        yalnızca talebin durumunu gösterir.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Talep</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4">
                                <Field label="Pazaryeri durumu">
                                    {claim.externalStatus}
                                </Field>
                                <Field label="Açılma" mono>
                                    {claim.openedAt ?? '—'}
                                </Field>
                                <div className="col-span-2">
                                    <dt className="text-xs text-muted-foreground">
                                        Sebep
                                    </dt>
                                    <dd className="text-sm">
                                        {claim.reason ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Sipariş</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-2 gap-4">
                                <div>
                                    <dt className="text-xs text-muted-foreground">
                                        Sipariş numarası
                                    </dt>
                                    <dd className="text-sm">
                                        <Link
                                            href={orderShow({
                                                order: order.id,
                                            })}
                                            instant
                                            className="tabular-nums underline underline-offset-4"
                                        >
                                            {order.orderNumber}
                                        </Link>
                                    </dd>
                                </div>
                                <Field label="Paket" mono>
                                    {order.packageId}
                                </Field>
                                <Field label="Kanal">
                                    {order.connection ?? '—'}
                                </Field>
                                <Field label="Sipariş tarihi" mono>
                                    {order.placedAt ?? '—'}
                                </Field>
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <div className="overflow-hidden rounded-lg border border-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kalem</TableHead>
                                <TableHead>SKU</TableHead>
                                <TableHead>Barkod</TableHead>
                                <TableHead className="text-right">
                                    Adet
                                </TableHead>
                                <TableHead className="text-right">
                                    Birim fiyat
                                </TableHead>
                                <TableHead>Durum</TableHead>
                                <TableHead>Sebep</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="font-medium tabular-nums">
                                        {item.remoteItemId}
                                    </TableCell>
                                    {/* Siparis satiri eslesmemis olabilir; talep yine de gorunur. */}
                                    <TableCell className="tabular-nums">
                                        {item.sku ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground tabular-nums">
                                        {item.barcode ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.quantity}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.unitPrice ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <ClaimStatusBadge
                                            status={item.status}
                                            label={item.statusLabel}
                                        />
                                        <span className="block text-xs text-muted-foreground">
                                            {item.externalStatus}
                                        </span>
                                    </TableCell>
                                    <TableCell className="max-w-48 truncate text-muted-foreground">
                                        {item.reason ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ClaimShow.layout = {
    breadcrumbs: [
        {
            title: 'İadeler',
            href: index(),
        },
    ],
};
