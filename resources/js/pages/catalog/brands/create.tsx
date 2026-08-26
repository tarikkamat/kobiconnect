import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Loader2, Save, Tag } from 'lucide-react';
import { useMemo, useState } from 'react';
import BrandController from '@/actions/App/Http/Controllers/Catalog/BrandController';
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
import { index } from '@/routes/brands';
import { index as definitions } from '@/routes/definitions';

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

export default function BrandCreate() {
    const [name, setName] = useState('');
    const previewSlug = useMemo(() => slugify(name), [name]);

    return (
        <>
            <Head title="Yeni Marka Ekle" />

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
                                <span>Markalar</span>
                            </Link>
                        </Button>
                        <span className="text-muted-foreground/40">/</span>
                        <span className="font-sans text-sm font-semibold text-foreground">
                            Yeni Marka
                        </span>
                    </div>
                </div>

                {/* Header Banner */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-xs sm:p-5">
                    <div className="space-y-1">
                        <h1 className="font-sans text-xl font-bold tracking-tight text-foreground sm:text-2xl">
                            Yeni Marka Ekle
                        </h1>
                        <p className="text-xs text-muted-foreground">
                            Ürünlerinizi markalarına göre gruplamak ve pazaryeri
                            eşlemelerini yönetmek için yeni marka oluşturun.
                        </p>
                    </div>
                </div>

                {/* Form */}
                <Form
                    {...BrandController.store.form()}
                    className="grid grid-cols-1 gap-5 lg:grid-cols-12"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Left Column */}
                            <div className="space-y-5 lg:col-span-8">
                                <Card className="gap-0 overflow-hidden border-border bg-card py-0 shadow-xs">
                                    <CardHeader className="border-b border-border bg-muted/40 px-4 py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Tag className="size-4 text-primary" />
                                            Temel Bilgiler
                                        </CardTitle>
                                        <CardDescription className="text-xs">
                                            Markanın adını ve otomatik
                                            üretilecek kod bilgisini girin.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4 p-4">
                                        <div className="grid gap-1.5">
                                            <Label
                                                htmlFor="name"
                                                className="text-xs font-medium"
                                            >
                                                Marka Adı{' '}
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
                                                placeholder="Örn: Apple, Philips, Samsung..."
                                                className="h-9 text-xs font-medium"
                                            />
                                            <InputError
                                                message={
                                                    errors.name ?? errors.slug
                                                }
                                            />
                                        </div>

                                        {previewSlug && (
                                            <div className="rounded-lg border border-border bg-muted/40 p-2.5 text-xs text-muted-foreground">
                                                <span className="font-medium text-foreground">
                                                    Slug Önizleme:{' '}
                                                </span>
                                                <code className="font-mono text-foreground">
                                                    {previewSlug}
                                                </code>
                                                <p className="mt-1 text-[11px] text-muted-foreground/80">
                                                    Pazaryeri entegrasyonu ve
                                                    URL tanımlamalarında bu
                                                    benzersiz kod kullanılır.
                                                </p>
                                            </div>
                                        )}
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
                                            Markayı Kaydet
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

BrandCreate.layout = {
    breadcrumbs: [
        {
            title: 'Tanımlamalar',
            href: definitions(),
        },
        {
            title: 'Markalar',
            href: index(),
        },
        {
            title: 'Yeni Marka',
        },
    ],
};
