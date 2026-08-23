import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as notificationPreferences } from '@/routes/notification-preferences';
import { edit } from '@/routes/profile';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editSecurity } from '@/routes/security';
import { index as team } from '@/routes/team';
import type { NavItem } from '@/types';

/**
 * FRONTEND-PLAN.md §6'nin tek gizleme istisnasi: tamamen alakasiz bolumler
 * menuden cikarilir. Aksiyonlar hala gizlenmez, devre disi birakilir.
 */
/**
 * Fonksiyon, sabit degil: Wayfinder'in tenant varsayilani modul yuklendikten
 * sonra kuruluyor (bkz. app-sidebar.tsx).
 */
function sidebarNavItems(): (NavItem & { permission?: string })[] {
    return [
        { title: 'Profil', href: edit(), icon: null },
        { title: 'Görünüm', href: editAppearance(), icon: null },
        { title: 'Güvenlik', href: editSecurity(), icon: null },
        {
            title: 'Ekip & Roller',
            href: team(),
            icon: null,
            permission: 'users.manage',
        },
        {
            title: 'Bildirim Tercihleri',
            href: notificationPreferences(),
            icon: null,
        },
    ];
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { permissions } = usePage().props;
    const items = sidebarNavItems().filter(
        (item) =>
            item.permission === undefined ||
            permissions.includes(item.permission),
    );

    return (
        <div className="px-4 py-6">
            <Heading
                title="Ayarlar"
                description="Profilinizi ve hesap ayarlarınızı yönetin"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Ayarlar"
                    >
                        {items.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start text-xs', {
                                    'bg-accent text-accent-foreground':
                                        isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
