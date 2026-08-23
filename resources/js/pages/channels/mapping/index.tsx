import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, FolderTree, Split } from 'lucide-react';
import type {
    MappingCategoryRow,
    MappingStatus,
} from '@/components/channels/mapping/types';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
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
import { index, show } from '@/routes/mapping';

type Props = {
    connections: { id: number; name: string }[];
    connectionId: number | null;
    categories: MappingCategoryRow[];
};

const STATUS: Record<
    MappingStatus,
    { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
    unmapped: { label: 'Eşlenmedi', variant: 'outline' },
    partial: { label: 'Eksik', variant: 'secondary' },
    mapped: { label: 'Eşlendi', variant: 'default' },
};

export default function MappingIndex({
    connections,
    connectionId,
    categories,
}: Props) {
    if (connections.length === 0) {
        return (
            <>
                <Head title="Eşlemeler" />
                <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                    <Heading
                        title="Kanal Eşlemeleri"
                        description="Kategori, özellik ve marka eşlemeleri."
                    />
                    <EmptyState
                        icon={Split}
                        title="Önce bir pazaryeri bağlantısı ekleyin"
                        description="Kategori, özellik ve marka eşlemeleri bağlı olan mağazanın kataloğuna göre yapılır."
                    />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Eşlemeler" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Kanal Eşlemeleri"
                        description="Kendi kategorileriniz pazaryeri kategorilerine burada bağlanır. Ürün gönderiminin ön koşuludur."
                    />

                    <div className="w-56">
                        <Select
                            value={
                                connectionId === null
                                    ? undefined
                                    : String(connectionId)
                            }
                            onValueChange={(value) =>
                                router.get(
                                    index.url(undefined, {
                                        query: { connection: value },
                                    }),
                                    {},
                                    { preserveState: true, replace: true },
                                )
                            }
                        >
                            <SelectTrigger aria-label="Pazaryeri bağlantısı seçin">
                                <SelectValue placeholder="Bağlantı seçin" />
                            </SelectTrigger>
                            <SelectContent>
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
                </div>

                {categories.length === 0 ? (
                    <EmptyState
                        icon={FolderTree}
                        title="Henüz kategoriniz yok"
                        description="Eşleme yapabilmek için önce Katalog → Kategoriler ekranından kategori ekleyin."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Kategori</TableHead>
                                    <TableHead>Ürün</TableHead>
                                    <TableHead>Pazaryeri kategorisi</TableHead>
                                    <TableHead>Özellik</TableHead>
                                    <TableHead>Durum</TableHead>
                                    <TableHead className="w-32" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categories.map((category) => (
                                    <TableRow key={category.id}>
                                        <TableCell>
                                            <span
                                                style={{
                                                    paddingLeft: `${category.depth * 16}px`,
                                                }}
                                            >
                                                {category.name}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground tabular-nums">
                                            {category.productCount}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {category.remotePath ?? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground tabular-nums">
                                            {category.attributeCount}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    STATUS[category.status]
                                                        .variant
                                                }
                                            >
                                                {STATUS[category.status].label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {connectionId === null ? null : (
                                                <Link
                                                    href={show({
                                                        connection:
                                                            connectionId,
                                                        category: category.id,
                                                    })}
                                                    prefetch
                                                    className="inline-flex items-center gap-1 text-sm font-medium hover:underline"
                                                >
                                                    Eşle
                                                    <ArrowRight className="size-4" />
                                                </Link>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <p className="text-xs text-muted-foreground">
                    “Eşlendi” rozeti veritabanındaki eşlemelere bakar; zorunlu
                    ama eşlenmemiş özellikler pazaryeri kataloğuna sorularak
                    bulunur ve sihirbazın önizleme adımında listelenir.
                </p>
            </div>
        </>
    );
}

MappingIndex.layout = {
    breadcrumbs: [
        {
            title: 'Eşlemeler',
            href: index(),
        },
    ],
};
