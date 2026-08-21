import { Head } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AppCard } from '@/components/channels/app-card';
import type { StoreApp } from '@/components/channels/app-card';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/apps';

type Props = {
    apps: StoreApp[];
    categories: { value: string; label: string }[];
};

export default function AppStoreIndex({ apps, categories }: Props) {
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState<string | null>(null);

    const term = search.trim().toLocaleLowerCase('tr');

    const filtered = useMemo(
        () =>
            apps.filter((app) => {
                if (category !== null && app.category !== category) {
                    return false;
                }

                return (
                    term === '' ||
                    `${app.name} ${app.summary} ${app.capabilities
                        .map((capability) => capability.label)
                        .join(' ')}`
                        .toLocaleLowerCase('tr')
                        .includes(term)
                );
            }),
        [apps, category, term],
    );

    const installed = apps.filter((app) => app.installed > 0);

    return (
        <>
            <Head title="Uygulama Mağazası" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Uygulama Mağazası"
                    description="Pazaryerleri ve e-ticaret altyapıları. Kurmak istediğiniz uygulamaya tıklayın; kimlik bilgilerini orada girersiniz."
                />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Uygulama ara… (örn. Trendyol, sipariş, stok)"
                            aria-label="Uygulama ara"
                            className="pl-9"
                        />
                        {search === '' ? null : (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Aramayı temizle"
                                className="absolute top-1/2 right-1 size-7 -translate-y-1/2"
                                onClick={() => setSearch('')}
                            >
                                <X />
                            </Button>
                        )}
                    </div>

                    <div className="flex gap-1">
                        <Button
                            variant={category === null ? 'secondary' : 'ghost'}
                            size="sm"
                            onClick={() => setCategory(null)}
                        >
                            Tümü
                        </Button>
                        {categories.map((option) => (
                            <Button
                                key={option.value}
                                variant={
                                    category === option.value
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                size="sm"
                                onClick={() => setCategory(option.value)}
                            >
                                {option.label}
                            </Button>
                        ))}
                    </div>
                </div>

                {installed.length === 0 ? null : (
                    <section className="flex flex-col gap-3">
                        <h2 className="text-sm font-medium">
                            Kurulu uygulamalarınız
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {installed.map((app) => (
                                <AppCard key={app.code} app={app} />
                            ))}
                        </div>
                    </section>
                )}

                <section className="flex flex-col gap-3">
                    {installed.length === 0 ? null : (
                        <h2 className="text-sm font-medium">Tüm uygulamalar</h2>
                    )}

                    {filtered.length === 0 ? (
                        <div className="flex flex-col items-center rounded-xl border border-dashed p-12 text-center">
                            <Search className="size-5 text-muted-foreground" />
                            <h3 className="mt-4 font-semibold">
                                Uygulama bulunamadı
                            </h3>
                            <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                                Arama kriterlerinizi değiştirip tekrar deneyin
                                veya tüm uygulamaları görüntüleyin.
                            </p>
                            <Button
                                variant="outline"
                                className="mt-4"
                                onClick={() => {
                                    setSearch('');
                                    setCategory(null);
                                }}
                            >
                                Tümünü göster
                            </Button>
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {filtered.map((app) => (
                                <AppCard key={app.code} app={app} />
                            ))}
                        </div>
                    )}
                </section>

                <p className="text-xs text-muted-foreground">
                    Aradığınız uygulama listede yok mu? Talebinizi iletin,
                    yol haritasına alalım.
                </p>
            </div>
        </>
    );
}

AppStoreIndex.layout = {
    breadcrumbs: [
        {
            title: 'Uygulama Mağazası',
            href: index(),
        },
    ],
};
