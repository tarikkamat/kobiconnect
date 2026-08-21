import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import type {
    MappingCategoryRow,
    MappingStatus,
} from '@/components/channels/mapping/types';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
                <div className="flex flex-col gap-4 p-4">
                    <Heading
                        title="Kanal Eşlemeleri"
                        description="Kategori, özellik ve marka eşlemeleri."
                    />
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Önce bir pazaryeri bağlantısı ekleyin; eşleme o
                        bağlantının kataloğuna göre yapılır.
                    </p>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Eşlemeler" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Kanal Eşlemeleri"
                        description="Kendi kategorileriniz pazaryeri kategorilerine burada bağlanır. Ürün gönderiminin ön koşuludur."
                    />

                    <div className="w-56">
                        <Select
                            value={String(connectionId ?? '')}
                            onValueChange={(value) =>
                                router.get(
                                    index.url(undefined, {
                                        query: { connection: value },
                                    }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <SelectTrigger aria-label="Bağlantı">
                                <SelectValue />
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
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Henüz kategoriniz yok. Katalog → Kategoriler ekranından
                        ekleyin.
                    </p>
                ) : (
                    <div className="rounded-xl border">
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
                                        <TableCell className="text-sm text-muted-foreground">
                                            {category.productCount}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {category.remotePath ?? (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
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
