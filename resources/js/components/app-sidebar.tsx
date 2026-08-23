import { Link } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    FolderGit2,
    LayoutGrid,
    Package,
    Plug,
    ShoppingCart,
    Store,
    Warehouse,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import type { NavGroup } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as apps } from '@/routes/apps';
import { index as brands } from '@/routes/brands';
import { index as categories } from '@/routes/categories';
import { index as claims } from '@/routes/claims';
import { index as connections } from '@/routes/connections';
import { index as listings } from '@/routes/listings';
import { index as mapping } from '@/routes/mapping';
import { index as matches } from '@/routes/matches';
import { index as orders } from '@/routes/orders';
import { index as products } from '@/routes/products';
import { index as stock } from '@/routes/stock';
import { monitor } from '@/routes/sync';
import { index as syncOperations } from '@/routes/sync/operations';
import { index as warehouses } from '@/routes/warehouses';
import type { NavItem } from '@/types';

/**
 * Bu diziler FONKSIYON, sabit degil. Wayfinder URL'leri istemcide uretir ve
 * tenant varsayilanini (`/{tenant}/...`) app.tsx'te `withApp` icinde alir —
 * yani MODUL YUKLENDIKTEN SONRA. Modul seviyesinde `orders()` cagirirsan URL
 * o an hesaplanir, varsayilan henuz yoktur ve link `/tenant/orders` olur: 404.
 * Render aninda cagirmak hem bunu cozer hem de workspace degisiminde tazelenir.
 */
function mainNavItems(): NavItem[] {
    return [
        {
            title: 'Gösterge Paneli',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Uygulama Mağazası',
            href: apps(),
            icon: Store,
        },
    ];
}

// Bilgi mimarisi FRONTEND-PLAN.md §1'de; henuz yapilmamis bolumler eklenmez.
function navGroups(): NavGroup[] {
    return [
        {
            title: 'Siparişler',
            icon: ShoppingCart,
            items: [
                { title: 'Tüm siparişler', href: orders() },
                {
                    title: 'Eşleşmemiş satırlar',
                    href: orders.url(undefined, { query: { unmatched: 1 } }),
                },
                { title: 'İadeler', href: claims() },
            ],
        },
        {
            title: 'Katalog',
            icon: Package,
            items: [
                { title: 'Ürünler', href: products() },
                { title: 'Markalar', href: brands() },
                { title: 'Kategoriler', href: categories() },
            ],
        },
        {
            title: 'Envanter',
            icon: Warehouse,
            items: [
                { title: 'Stok Durumu', href: stock() },
                { title: 'Depolar', href: warehouses() },
            ],
        },
        {
            title: 'Kanallar',
            icon: Plug,
            items: [
                { title: 'Bağlantılarım', href: connections() },
                { title: 'Eşlemeler', href: mapping() },
                { title: 'Listelemeler', href: listings() },
                { title: 'Ön Eşleşme', href: matches() },
            ],
        },
        {
            title: 'Operasyon',
            icon: Activity,
            items: [
                { title: 'Senkron Monitörü', href: monitor() },
                { title: 'İşlem Kuyruğu', href: syncOperations() },
            ],
        },
    ];
}

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems()} groups={navGroups()} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
