import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Layers, Loader2, Save } from 'lucide-react';
import { useMemo, useState } from 'react';
import ProductGroupController from '@/actions/App/Http/Controllers/Catalog/ProductGroupController';
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
import { Textarea } from '@/components/ui/textarea';
import { index as definitions } from '@/routes/definitions';
import { index } from '@/routes/product-groups';

function slugify(text: string): string {
    const trMap: Record<string, string> = {
        ç: 'c',
        Ç: 'c',
        ğ: 'g',
        Ğ: 'g',
        ı: 'i',
        I: 'i',
        İ: 'i',
        ö: 'o',
        Ö: 'o',
        ş: 's',
        Ş: 's',
        ü: 'u',
        Ü: 'u',
    };

    return text
        .split('')
        .map((char) => trMap[char] ?? char)
        .join('')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

export default function ProductGroupCreate() {
    const [name, setName] = useState('');
    const [slug, setSlug] = useState('');
    const [description, setDescription] = useState('');

    const previewSlug = useMemo(() => {
        if (slug.trim()) {
            return slugify(slug);
        }

        return slugify(name);
    }, [slug, name]);

    return (
        <>
            <Head title="Yeni Ürün Grubu Ekle" />

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
                                <span>Ürün Grupları</span>
                            </Link>
                        </Button>
                        <span className="text-muted-foreground/40">/</span>
                        <span className="font-sans text-sm font-semibold text-foreground">
                            Yeni Ürün Grubu
                        </span>
                    </div>
                </div>

                {/* Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                    <div className="space-y-1">
                        <h1 className="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Yeni Ürün Grubu Ekle
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Ürünlerinizi belirli kriterlere göre gruplayarak
                            detay sayfasında ve listelemelerde nasıl
                            görüneceklerini ayarlayın.
                        </p>
                    </div>
                </div>

                {/* Form */}
                <Form
                    {...ProductGroupController.store.form()}
                    className="grid grid-cols-1 gap-5 lg:grid-cols-12"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Left Column */}
                            <div className="space-y-5 lg:col-span-8">
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Layers className="size-4 text-primary" />
                                            Grup Bilgileri
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Grup adı, benzersiz kodu ve isteğe
                                            bağlı açıklama metnini girin.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="name"
                                                className="text-xs font-medium"
                                            >
                                                Grup Adı{' '}
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
                                                placeholder="Örn: 2026 Yaz Koleksiyonu, Aksesuarlar..."
                                                className="h-9 text-xs font-medium"
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="slug"
                                                className="text-xs font-medium"
                                            >
                                                Slug (Opsiyonel)
                                            </Label>
                                            <Input
                                                id="slug"
                                                name="slug"
                                                value={
                                                    slug ||
                                                    (previewSlug
                                                        ? previewSlug
                                                        : '')
                                                }
                                                onChange={(e) =>
                                                    setSlug(e.target.value)
                                                }
                                                placeholder={
                                                    previewSlug ||
                                                    'yaz-koleksiyonu'
                                                }
                                                className="h-9 font-mono text-xs"
                                            />
                                            <InputError message={errors.slug} />
                                        </div>

                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="description"
                                                className="text-xs font-medium"
                                            >
                                                Açıklama (Opsiyonel)
                                            </Label>
                                            <Textarea
                                                id="description"
                                                name="description"
                                                rows={3}
                                                value={description}
                                                onChange={(e) =>
                                                    setDescription(
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Bu grubun kullanım amacı ve detayları..."
                                                className="text-xs"
                                            />
                                            <InputError
                                                message={errors.description}
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
                                            Grubu Kaydet
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

ProductGroupCreate.layout = {
    breadcrumbs: [
        {
            title: 'Tanımlamalar',
            href: definitions(),
        },
        {
            title: 'Ürün Grupları',
            href: index(),
        },
        {
            title: 'Yeni Ürün Grubu',
        },
    ],
};
