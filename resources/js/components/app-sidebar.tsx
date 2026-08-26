import { Link } from '@inertiajs/react';
import {
    BarChart3,
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
import { index as claims } from '@/routes/claims';
import { index as connections } from '@/routes/connections';
import { index as definitions } from '@/routes/definitions';
import { index as mapping } from '@/routes/mapping';
import { index as orders } from '@/routes/orders';
import { index as priceLists } from '@/routes/price-lists';
import { index as products } from '@/routes/products';
import {
    channels as reportsChannels,
    index as reports,
    orders as reportsOrders,
    penalties as reportsPenalties,
    products as reportsProducts,
} from '@/routes/reports';
import { index as stock } from '@/routes/stock';
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
            title: 'Raporlar',
            icon: BarChart3,
            items: [
                { title: 'Finans ve Satış', href: reports() },
                { title: 'Kanal Dağılımı', href: reportsChannels() },
                { title: 'Ürün Satışları', href: reportsProducts() },
                { title: 'Kargo & Cezalar', href: reportsPenalties() },
                { title: 'Sipariş Statüleri', href: reportsOrders() },
            ],
        },
        {
            title: 'Ürünler',
            icon: Package,
            items: [
                { title: 'Ürünler', href: products() },
                { title: 'Tanımlamalar', href: definitions() },
                { title: 'Fiyat Listesi', href: priceLists() },
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
