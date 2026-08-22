import { type ColumnDef, tableFeatures, useTable } from '@tanstack/react-table';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

/** Kolon basligi ve hucrelerine birlikte uygulanir: hizalama ve genislik. */
type DataTableColumnMeta = {
    className?: string;
};

/**
 * Siralama, filtreleme ve sayfalama sunucuda yapilir (Inertia sorgu
 * parametreleri + `<InfiniteScroll>`), bu yuzden tabloya hicbir istemci
 * ozelligi kaydedilmez. TanStack burada sadece kolon tanimlarini satirlara
 * ceviren cekirdek model olarak durur; iki kaynakli siralama olusmaz.
 */
const features = tableFeatures({
    columnMeta: {} as DataTableColumnMeta,
});

export type DataTableColumns<TRow extends object> = ColumnDef<
    typeof features,
    TRow
>[];

export function DataTable<TRow extends object>({
    columns,
    data,
    getRowId,
    rowClassName,
}: {
    columns: DataTableColumns<TRow>;
    data: TRow[];
    getRowId?: (row: TRow) => string;
    rowClassName?: (row: TRow) => string | undefined;
}) {
    const table = useTable({ features, columns, data, getRowId });

    return (
        <Table>
            <TableHeader>
                {table.getHeaderGroups().map((group) => (
                    <TableRow key={group.id}>
                        {group.headers.map((header) => (
                            <TableHead
                                key={header.id}
                                className={header.column.columnDef.meta?.className}
                            >
                                {header.isPlaceholder ? null : (
                                    <table.FlexRender header={header} />
                                )}
                            </TableHead>
                        ))}
                    </TableRow>
                ))}
            </TableHeader>
            <TableBody>
                {table.getRowModel().rows.map((row) => (
                    <TableRow
                        key={row.id}
                        className={rowClassName?.(row.original)}
                    >
                        {row.getAllCells().map((cell) => (
                            <TableCell
                                key={cell.id}
                                className={cell.column.columnDef.meta?.className}
                            >
                                <table.FlexRender cell={cell} />
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
