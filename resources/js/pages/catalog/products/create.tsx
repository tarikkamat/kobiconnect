import { Form, Head } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Catalog/ProductController';
import { PermissionButton } from '@/components/catalog/permission-button';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Textarea } from '@/components/ui/textarea';
import { usePermission } from '@/hooks/use-permission';
import { index } from '@/routes/products';

type Props = {
    brands: { id: number; name: string }[];
    categories: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
};

/** Radix Select bos deger kabul etmez; "secilmedi" bu sabitle temsil edilir. */
const NONE = 'none';

export default function ProductCreate({ brands, categories, statuses }: Props) {
    const can = usePermission();
    const canManage = can('catalog.manage');

    const [brandId, setBrandId] = useState<number | null>(null);
    const [categoryId, setCategoryId] = useState<number | null>(null);

    /**
     * Varyant satirlari yalnizca kimlik tasir; degerler dogrudan form
     * alanlarindan gider (`variants[0][sku]`) — kontrollu state tutmuyoruz,
     * dogrulama hatalarini sunucu zaten indis bazinda geri veriyor.
     */
    const [rows, setRows] = useState<number[]>([0]);

    return (
        <>
            <Head title="Yeni ürün" />

            <div className="flex flex-col gap-4 p-4">
                <Heading
                    title="Yeni ürün"
                    description="Bir ürün en az bir varyantla doğar: stok, fiyat ve pazaryeri listelemesi varyanta bağlıdır."
                />

                <Form
                    {...ProductController.store.form()}
                    options={{ preserveScroll: true }}
                    className="max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Ürün adı</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Açıklama</Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={5}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label>Marka</Label>
                                    {/* Radix Select bos degeri tasiyamadigi icin deger gizli alanla gider. */}
                                    <input
                                        type="hidden"
                                        name="brand_id"
                                        value={brandId ?? ''}
                                    />
                                    <Select
                                        value={
                                            brandId === null
                                                ? NONE
                                                : String(brandId)
                                        }
                                        onValueChange={(value) =>
                                            setBrandId(
                                                value === NONE
                                                    ? null
                                                    : Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger aria-label="Marka">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                Markasız
                                            </SelectItem>
                                            {brands.map((brand) => (
                                                <SelectItem
                                                    key={brand.id}
                                                    value={String(brand.id)}
                                                >
                                                    {brand.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.brand_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Kategori</Label>
                                    <input
                                        type="hidden"
                                        name="category_id"
                                        value={categoryId ?? ''}
                                    />
                                    <Select
                                        value={
                                            categoryId === null
                                                ? NONE
                                                : String(categoryId)
                                        }
                                        onValueChange={(value) =>
                                            setCategoryId(
                                                value === NONE
                                                    ? null
                                                    : Number(value),
                                            )
                                        }
                                    >
                                        <SelectTrigger aria-label="Kategori">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={NONE}>
                                                Kategorisiz
                                            </SelectItem>
                                            {categories.map((category) => (
                                                <SelectItem
                                                    key={category.id}
                                                    value={String(category.id)}
                                                >
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.category_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label>Durum</Label>
                                    <Select name="status" defaultValue="draft">
                                        <SelectTrigger aria-label="Durum">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                    <InputError message={errors.status} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Heading
                                    variant="small"
                                    title="Varyantlar"
                                    description="Stok varsayılan depoya yazılır. Fiyat ve stok boş bırakılabilir, envanter ekranından da girilebilir."
                                />
                                <InputError message={errors.variants} />

                                <div className="rounded-xl border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>SKU</TableHead>
                                                <TableHead>Barkod</TableHead>
                                                <TableHead>
                                                    Liste fiyatı (TRY)
                                                </TableHead>
                                                <TableHead>Stok</TableHead>
                                                <TableHead className="w-10" />
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {rows.map((row, position) => (
                                                <TableRow key={row}>
                                                    <TableCell>
                                                        <Input
                                                            name={`variants[${position}][sku]`}
                                                            required
                                                            aria-label={`${position + 1}. varyant SKU`}
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `variants.${position}.sku`
                                                                ]
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            name={`variants[${position}][barcode]`}
                                                            aria-label={`${position + 1}. varyant barkod`}
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `variants.${position}.barcode`
                                                                ]
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            name={`variants[${position}][list_price]`}
                                                            aria-label={`${position + 1}. varyant fiyat`}
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `variants.${position}.list_price`
                                                                ]
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            name={`variants[${position}][on_hand]`}
                                                            aria-label={`${position + 1}. varyant stok`}
                                                        />
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    `variants.${position}.on_hand`
                                                                ]
                                                            }
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`${position + 1}. varyantı kaldır`}
                                                            disabled={
                                                                rows.length ===
                                                                1
                                                            }
                                                            onClick={() =>
                                                                setRows(
                                                                    rows.filter(
                                                                        (
                                                                            value,
                                                                        ) =>
                                                                            value !==
                                                                            row,
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setRows([
                                            ...rows,
                                            Math.max(...rows, 0) + 1,
                                        ])
                                    }
                                >
                                    <Plus className="size-4" />
                                    Varyant ekle
                                </Button>
                            </div>

                            <PermissionButton
                                check={canManage}
                                type="submit"
                                disabled={processing}
                            >
                                Ürünü oluştur
                            </PermissionButton>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ProductCreate.layout = {
    breadcrumbs: [
        {
            title: 'Ürünler',
            href: index(),
        },
    ],
};
