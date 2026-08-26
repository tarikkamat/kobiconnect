import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, FolderPlus, Loader2, Save } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Catalog/CategoryController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/categories';
import { index as definitions } from '@/routes/definitions';

type CategoryItem = {
    id: number;
    name: string;
    depth: number;
};

const ROOT = 'root';

export default function CategoryCreate({
    categories = [],
}: {
    categories?: CategoryItem[];
}) {
    const [name, setName] = useState('');
    const [parentId, setParentId] = useState<number | null>(null);

    return (
        <>
            <Head title="Yeni Kategori Ekle" />

            <div className="mx-auto flex max-w-7xl flex-col gap-5 p-4 font-sans sm:p-6 lg:p-8">
                {/* Top Navigation */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 h-8 gap-1.5 text-muted-foreground hover:text-foreground"
                        >
                            <Link href={index()}>
                                <ArrowLeft className="size-4" />
                                <span>Kategoriler</span>
                            </Link>
                        </Button>
                        <span className="text-muted-foreground/40">/</span>
                        <span className="font-sans text-sm font-semibold text-foreground">
                            Yeni Kategori
                        </span>
                    </div>
                </div>

                {/* Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                    <div className="space-y-1">
                        <h1 className="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Yeni Kategori Ekle
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Ürünlerinizi kategorilere ayırarak
                            ziyaretçilerinizin aradıkları ürünü daha hızlı
                            bulmalarını sağlayın.
                        </p>
                    </div>
                </div>

                {/* Form */}
                <Form
                    {...CategoryController.store.form()}
                    className="grid grid-cols-1 gap-5 lg:grid-cols-12"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Hidden Parent ID */}
                            <input
                                type="hidden"
                                name="parent_id"
                                value={parentId ?? ''}
                            />

                            {/* Left Column */}
                            <div className="space-y-5 lg:col-span-8">
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <FolderPlus className="size-4 text-primary" />
                                            Kategori Bilgileri
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Kategori adı ve hiyerarşik üst
                                            kategori bilgisini belirleyin.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="name"
                                                className="text-xs font-medium"
                                            >
                                                Kategori Adı{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                autoFocus
                                                value={name}
                                                onChange={(e) =>
                                                    setName(e.target.value)
                                                }
                                                placeholder="Örn: Akıllı Telefonlar, Tişört..."
                                                className="h-9 text-xs font-medium"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="parent-category"
                                                className="text-xs font-medium"
                                            >
                                                Üst Kategori
                                            </Label>
                                            <Select
                                                value={
                                                    parentId === null
                                                        ? ROOT
                                                        : String(parentId)
                                                }
                                                onValueChange={(val) =>
                                                    setParentId(
                                                        val === ROOT
                                                            ? null
                                                            : Number(val),
                                                    )
                                                }
                                            >
                                                <SelectTrigger
                                                    id="parent-category"
                                                    className="h-9 text-xs"
                                                    aria-label="Üst kategori seçimi"
                                                >
                                                    <SelectValue placeholder="Kök kategori (Ana Dal)" />
                                                </SelectTrigger>
                                                <SelectContent className="max-h-64">
                                                    <SelectItem
                                                        value={ROOT}
                                                        className="text-xs"
                                                    >
                                                        📁 Kök Kategori (Ana
                                                        Dal)
                                                    </SelectItem>
                                                    {categories.map((cat) => (
                                                        <SelectItem
                                                            key={cat.id}
                                                            value={String(
                                                                cat.id,
                                                            )}
                                                            className="text-xs"
                                                        >
                                                            {'\u00A0'.repeat(
                                                                cat.depth * 4,
                                                            ) +
                                                                (cat.depth > 0
                                                                    ? '└─ '
                                                                    : '') +
                                                                cat.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <p className="text-xs text-muted-foreground">
                                                Kök kategori seçilirse en üst
                                                seviyede ana kategori
                                                oluşturulur.
                                            </p>
                                            <InputError
                                                message={errors.parent_id}
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Right Column */}
                            <div className="space-y-5 lg:col-span-4">
                                <Card className="gap-0 overflow-hidden border-primary/20 bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="text-sm font-semibold">
                                            Kaydet
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-3.5 p-4">
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing || !name.trim()
                                            }
                                            className="h-9 w-full gap-2 text-xs font-semibold shadow-xs"
                                        >
                                            {processing ? (
                                                <Loader2 className="size-3.5 animate-spin" />
                                            ) : (
                                                <Save className="size-3.5" />
                                            )}
                                            Kategoriyi Kaydet
                                        </Button>

                                        <Button
                                            asChild
                                            type="button"
                                            variant="outline"
                                            className="h-9 w-full text-xs"
                                        >
                                            <Link href={index()}>Vazgeç</Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

CategoryCreate.layout = {
    breadcrumbs: [
        {
            title: 'Tanımlamalar',
            href: definitions(),
        },
        {
            title: 'Kategoriler',
            href: index(),
        },
        {
            title: 'Yeni Kategori',
        },
    ],
};
