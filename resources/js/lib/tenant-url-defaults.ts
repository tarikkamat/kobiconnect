import type { Page, SharedPageProps } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { setUrlDefaults } from '@/wayfinder';

/**
 * Tenant, URL'in ILK PATH SEGMENTIDIR (`/{tenant}/dashboard`).
 *
 * Sunucuda `URL::defaults(['tenant' => …])` kurulu, bu yuzden PHP'nin `route()`
 * fonksiyonu dogru URL uretir. Ama Wayfinder URL'leri ISTEMCIDE uretir ve kendi
 * varsayilan sozlugu vardir — beslenmezse `{tenant}` yerine parametrenin ADINI
 * koyar: `/tenant/orders`, yani 404. Kenar cubugundaki her link sessizce olur.
 *
 * Varsayilan IKI kaynaktan besleniyor, cunku tek basina hicbiri yetmiyor:
 *
 * 1. Modul yuklenir yuklenmez URL'nin ilk segmenti (asagidaki side-effect).
 *    Bazi dosyalar rota URL'lerini modul seviyesinde hesapliyor olabilir; o kod
 *    `withApp` calismadan once kosar ve varsayilani bulamazdi.
 * 2. Inertia'nin paylasilan `tenant` prop'u (initialise + her `navigate`).
 *    Asil kaynak budur; SPA icinde workspace degisimini de yakalar.
 *
 * Ilk segmentin tenant oldugunu SAYISAL oldugu icin guvenle anlayabiliyoruz —
 * tenant kimlikleri sequence'ten gelen numaralardir (1001, 1002, …), central
 * yollar (`/login`, `/register`) asla sayisal degildir.
 */
let currentTenant: string | null = null;

function tenantFromLocation(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const [segment] = window.location.pathname.slice(1).split('/');

    return /^\d+$/.test(segment) ? segment : null;
}

function readTenant(page: Page<SharedPageProps>): string | null {
    const tenant = (page.props as { tenant?: { id?: unknown } | null }).tenant;

    return typeof tenant?.id === 'string' && tenant.id !== ''
        ? tenant.id
        : null;
}

// Fonksiyon olarak veriyoruz: URL uretim aninda okunur, boot aninda degil.
setUrlDefaults(() => {
    const tenant = currentTenant ?? tenantFromLocation();

    return tenant === null ? {} : { tenant };
});

export function initialiseTenantUrlDefaults(page: Page<SharedPageProps>): void {
    currentTenant = readTenant(page);

    // Sonraki gezinmelerde de tazele: central sayfaya cikip tenant'a donmek ya
    // da workspace degistirmek varsayilani bayatlatir.
    router.on('navigate', (event) => {
        currentTenant = readTenant(event.detail.page);
    });
}
