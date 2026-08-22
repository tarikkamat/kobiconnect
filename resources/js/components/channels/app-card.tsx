import type { CredentialField } from '@/components/channels/connection-drawer';
import { cn } from '@/lib/utils';

/**
 * Magazadaki bir uygulama — `config/apps.php` + surucu kaydinin birlesimi
 * (App\Support\AppCatalog). `available` surucusu var mi demektir; olmayan
 * uygulama vitrinde "Yakında" olarak durur.
 */
export type StoreApp = {
    code: string;
    name: string;
    category: string;
    categoryLabel: string;
    logo: string;
    /** Kendi tuvalinde kucuk cizilmis markalar icin carpan (config/apps.php). */
    logoScale: number;
    /** Koyu wordmark karanlik temada beyaza boyanir (config/apps.php). */
    logoDarkInvert: boolean;
    capabilities: { value: string; label: string }[];
    available: boolean;
    fields: CredentialField[];
    installed: number;
};

/**
 * Acik temada logo beyaz zemin uzerinde durur. Karanlik temada davranis
 * `logoDarkInvert` ile belirlenir: koyu wordmark'lar (Trendyol #231F20,
 * ikas #1C1C1A) seffaf zeminde tamamen beyaza boyanir, renkli logolar
 * (n11, WooCommerce...) oldugu gibi kalir. Bayragi BILMEYEN cagiranlar
 * (`logoDarkInvert` undefined, orn. dashboard grafik lejanti) eski guvenli
 * davranisi alir: her temada beyaz zemin.
 */
export function AppIcon({
    app,
    className,
    imageClassName,
}: {
    app: Pick<StoreApp, 'logo' | 'name'> & {
        logoScale?: number;
        logoDarkInvert?: boolean;
    };
    className?: string;
    imageClassName?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center justify-center bg-white',
                app.logoDarkInvert !== undefined && 'dark:bg-transparent',
                className,
            )}
        >
            <img
                src={app.logo}
                alt={app.name}
                loading="lazy"
                style={
                    app.logoScale === undefined || app.logoScale === 1
                        ? undefined
                        : { scale: app.logoScale }
                }
                className={cn(
                    'max-h-full max-w-full object-contain',
                    app.logoDarkInvert && 'dark:brightness-0 dark:invert',
                    imageClassName,
                )}
            />
        </div>
    );
}
