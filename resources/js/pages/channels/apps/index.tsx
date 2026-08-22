import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { AppCard } from '@/components/channels/app-card';
import type { StoreApp } from '@/components/channels/app-card';
import { ConnectionDrawer } from '@/components/channels/connection-drawer';
import Heading from '@/components/heading';
import { usePermission } from '@/hooks/use-permission';
import { index } from '@/routes/apps';

type Props = {
    apps: StoreApp[];
    categories: { value: string; label: string }[];
};

export default function AppStoreIndex({ apps, categories }: Props) {
    const canManage = usePermission()('channels.manage');
    const [setup, setSetup] = useState<StoreApp | null>(null);

    // Vitrin kategoriye gore gruplanir; arama YOK — liste bir ekrana sigiyor.
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

    return (
        <>
            <Head title="Uygulama Mağazası" />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="Uygulama Mağazası"
                    description="Kurmak istediğiniz uygulamayı seçin; kimlik bilgilerini açılan pencerede girersiniz."
                />

                {groups.map((group) => (
                    <section key={group.value} className="flex flex-col gap-3">
                        <div className="flex items-baseline gap-2">
                            <h2 className="text-base font-semibold">
                                {group.label}
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {group.apps.length} uygulama
                            </span>
                        </div>

                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
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

            <ConnectionDrawer
                marketplace={
                    setup === null
                        ? null
                        : {
                              value: setup.code,
                              label: setup.name,
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
