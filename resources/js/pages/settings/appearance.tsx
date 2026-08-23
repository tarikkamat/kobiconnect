import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Görünüm ayarları" />

            <h1 className="sr-only">Görünüm ayarları</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Görünüm"
                    description="Hesabınızın tema ve görünüm tercihlerini özelleştirin"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Görünüm ayarları',
            href: editAppearance(),
        },
    ],
};
