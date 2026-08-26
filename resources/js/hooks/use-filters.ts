import { router } from '@inertiajs/react';
import type { QueryParams, RouteQueryOptions } from '@/wayfinder';

/** Wayfinder route'unun `.url()` fonksiyonu — tenant argumani varsayilandan gelir. */
type RouteUrl = (args?: undefined, options?: RouteQueryOptions) => string;

/**
 * Liste ekranlarinin filtre uygulama davranisi.
 *
 * Bes sayfada (urunler, siparisler, iadeler, stok, bildirimler) birebir ayni
 * fonksiyon kopyalanmisti; tek fark hangi bos degerin ayiklandigiydi ve bu da
 * bazi ekranlarda URL'de `?status=` gibi anlamsiz parametreler birakiyordu.
 *
 * Bos degerler (null / undefined / '' / false) sorgudan dusulur: filtre yoksa
 * URL'de de olmaz. `replace: true` filtre denemelerini tarayici gecmisine
 * yigmaz, `preserveScroll` uzun tablolarda konumu korur.
 */
export function useFilters<TFilters extends QueryParams>(
    url: RouteUrl,
    filters: TFilters,
): {
    apply: (changes: Partial<TFilters>) => void;
    clear: () => void;
} {
    const apply = (changes: Partial<TFilters>): void => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) =>
                    value !== null &&
                    value !== undefined &&
                    value !== '' &&
                    value !== false,
            ),
        );

        visit(url(undefined, { query }));
    };

    const clear = (): void => visit(url());

    return { apply, clear };
}

function visit(target: string): void {
    router.get(
        target,
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
