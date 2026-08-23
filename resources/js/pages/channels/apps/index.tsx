import { Head } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AppCard } from '@/components/channels/app-card';
import type { StoreApp } from '@/components/channels/app-card';
import { ConnectionDrawer } from '@/components/channels/connection-drawer';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermission } from '@/hooks/use-permission';
import { cn } from '@/lib/utils';
import { index } from '@/routes/apps';

type Props = {
    apps: StoreApp[];
    categories: { value: string; label: string }[];
};

export default function AppStoreIndex({ apps, categories }: Props) {
    const canManage = usePermission()('channels.manage');
    const [setup, setSetup] = useState<StoreApp | null>(null);
    const [search, setSearch] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<string>('all');
    const [selectedStatus, setSelectedStatus] = useState<string>('all');

    const stats = useMemo(() => {
        const installedCount = apps.filter((app) => app.installed > 0).length;
        const availableCount = apps.filter((app) => app.available).length;

        return {
            total: apps.length,
            installed: installedCount,
            available: availableCount,
        };
    }, [apps]);

    const isFiltered =
        search.trim() !== '' ||
        selectedCategory !== 'all' ||
        selectedStatus !== 'all';

    const filteredApps = useMemo(() => {
        return apps.filter((app) => {
            if (
                selectedCategory !== 'all' &&
                app.category !== selectedCategory
            ) {
                return false;
            }

            if (selectedStatus === 'installed' && app.installed === 0) {
                return false;
            }

            if (selectedStatus === 'available' && !app.available) {
                return false;
            }

            if (search.trim() !== '') {
                const query = search.toLowerCase().trim();
                const matchesName = app.name.toLowerCase().includes(query);
                const matchesCategory = app.categoryLabel
                    .toLowerCase()
                    .includes(query);
                const matchesCapability = app.capabilities.some(
                    (c) =>
                        c.label.toLowerCase().includes(query) ||
                        c.value.toLowerCase().includes(query),
                );

                if (!matchesName && !matchesCategory && !matchesCapability) {
                    return false;
                }
            }

            return true;
        });
    }, [apps, selectedCategory, selectedStatus, search]);

    const groups = useMemo(
        () =>
            categories
                .map((category) => ({
                    ...category,
                    apps: apps.filter((app) => app.category === category.value),
                }))
                .filter((group) => group.apps.length > 0),
        [apps, categories],
    );

    const resetFilters = () => {
        setSearch('');
        setSelectedCategory('all');
        setSelectedStatus('all');
    };

    return (
        <>
            <Head title="Uygulama Mağazası" />

            <div className="flex flex-col gap-6 p-4">
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <Heading
                        title="Uygulama Mağazası"
                        description="Pazaryerleri ve e-ticaret altyapılarınızı bağlayın; ürün, stok ve sipariş senkronizasyonunu başlatın."
                    />

                    <div className="flex items-center gap-2 self-start md:self-auto">
                        <div className="flex items-center gap-1.5 rounded-lg border border-border bg-secondary/60 px-3 py-1.5 font-mono text-xs">
                            <span className="size-2 rounded-full bg-primary" />
                            <span className="text-foreground">
                                <span className="font-semibold tabular-nums">
                                    {stats.installed}
                                </span>{' '}
                                Bağlı
                            </span>
                        </div>

                        <div className="flex items-center gap-1.5 rounded-lg border border-border bg-secondary/60 px-3 py-1.5 font-mono text-xs text-muted-foreground">
                            <span className="text-foreground">
                                <span className="font-semibold tabular-nums">
                                    {stats.available}
                                </span>{' '}
                                Hazır
                            </span>
                        </div>

                        <div className="flex items-center gap-1.5 rounded-lg border border-border bg-secondary/60 px-3 py-1.5 font-mono text-xs text-muted-foreground">
                            <span className="tabular-nums">
                                {stats.total} Toplam
                            </span>
                        </div>
                    </div>
                </div>

                {/* Arama ve Filtre Kontrolleri */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full max-w-sm">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Uygulama veya özellik ara..."
                            className="h-[34px] pr-8 pl-9 text-xs"
                        />
                        {search.trim() !== '' && (
                            <button
                                type="button"
                                onClick={() => setSearch('')}
                                className="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X className="size-3.5" />
                                <span className="sr-only">Aramayı temizle</span>
                            </button>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <div className="flex items-center rounded-md border border-border bg-secondary/50 p-0.5">
                            <button
                                type="button"
                                onClick={() => setSelectedCategory('all')}
                                className={cn(
                                    'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                    selectedCategory === 'all'
                                        ? 'bg-white text-background'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Tümü
                            </button>
                            {categories.map((category) => (
                                <button
                                    key={category.value}
                                    type="button"
                                    onClick={() =>
                                        setSelectedCategory(category.value)
                                    }
                                    className={cn(
                                        'rounded px-2.5 py-1 text-xs font-medium transition-colors',
                                        selectedCategory === category.value
                                            ? 'bg-white text-background'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {category.label}
                                </button>
                            ))}
                        </div>

                        <div className="flex items-center rounded-md border border-border bg-secondary/50 p-0.5">
                            <button
                                type="button"
                                onClick={() => setSelectedStatus('all')}
                                className={cn(
                                    'rounded px-2 py-1 text-xs font-medium transition-colors',
                                    selectedStatus === 'all'
                                        ? 'border border-border bg-card text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Hepsi
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedStatus('installed')}
                                className={cn(
                                    'rounded px-2 py-1 text-xs font-medium transition-colors',
                                    selectedStatus === 'installed'
                                        ? 'border border-border bg-card text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Kurulu
                            </button>
                            <button
                                type="button"
                                onClick={() => setSelectedStatus('available')}
                                className={cn(
                                    'rounded px-2 py-1 text-xs font-medium transition-colors',
                                    selectedStatus === 'available'
                                        ? 'border border-border bg-card text-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Hazır
                            </button>
                        </div>
                    </div>
                </div>

                {/* Vitrin Grid Listesi */}
                {isFiltered ? (
                    filteredApps.length === 0 ? (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border p-12 text-center">
                            <p className="font-serif text-2xl tracking-[-0.02em] text-muted-foreground">
                                Aramanızla eşleşen entegrasyon bulunamadı.
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground/70">
                                Farklı bir anahtar kelime deneyebilir veya
                                filtreleri sıfırlayabilirsiniz.
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                className="mt-4 text-xs"
                                onClick={resetFilters}
                            >
                                Filtreleri Temizle
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3">
                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                <span>
                                    <strong className="font-mono font-medium text-foreground tabular-nums">
                                        {filteredApps.length}
                                    </strong>{' '}
                                    uygulama listeleniyor
                                </span>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {filteredApps.map((app) => (
                                    <AppCard
                                        key={app.code}
                                        app={app}
                                        canManage={canManage}
                                        onInstall={setSetup}
                                    />
                                ))}
                            </div>
                        </div>
                    )
                ) : (
                    <div className="flex flex-col gap-8">
                        {groups.map((group) => (
                            <section
                                key={group.value}
                                className="flex flex-col gap-3.5"
                            >
                                <div className="flex items-baseline gap-2 border-b border-border/60 pb-2">
                                    <h2 className="text-base font-semibold tracking-tight text-foreground">
                                        {group.label}
                                    </h2>
                                    <span className="font-mono text-xs text-muted-foreground tabular-nums">
                                        ({group.apps.length})
                                    </span>
                                </div>

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {group.apps.map((app) => (
                                        <AppCard
                                            key={app.code}
                                            app={app}
                                            canManage={canManage}
                                            onInstall={setSetup}
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>

            <ConnectionDrawer
                marketplace={
                    setup === null
                        ? null
                        : {
                              value: setup.code,
                              label: setup.name,
                              logo: setup.logo,
                              logoScale: setup.logoScale,
                              logoDarkInvert: setup.logoDarkInvert,
                              capabilities: setup.capabilities,
                              fields: setup.fields,
                          }
                }
                connection={null}
                canManage={canManage}
                open={setup !== null}
                onOpenChange={(next) => !next && setSetup(null)}
            />
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

