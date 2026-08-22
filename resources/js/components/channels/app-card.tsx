import { Check, Plus } from 'lucide-react';
import { PermissionButton } from '@/components/catalog/permission-button';
import type { CredentialField } from '@/components/channels/connection-drawer';
import type { PermissionCheck } from '@/hooks/use-permission';
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

export function AppCard({
    app,
    canManage,
    onInstall,
}: {
    app: StoreApp;
    canManage: PermissionCheck;
    onInstall: (app: StoreApp) => void;
}) {
    const check: PermissionCheck = app.available
        ? canManage
        : { allowed: false, reason: 'Bu uygulama henüz yayında değil.' };

    return (
        <PermissionButton
            check={check}
            variant="outline"
            // disabled:opacity-100: soluklastirma beyaz logo karosunu griye
            // dondurur; pasiflik kosedeki "Yakinda" etiketiyle verilir.
            className="relative h-24 w-full rounded-xl bg-white p-0 hover:border-foreground/30 hover:bg-white disabled:opacity-100 dark:bg-transparent dark:hover:bg-white/5"
            title={app.name}
            aria-label={
                app.installed > 0
                    ? `${app.name} için yeni bağlantı ekle`
                    : `${app.name} aktifleştir`
            }
            onClick={() => onInstall(app)}
        >
            <AppIcon
                app={app}
                className="size-full rounded-xl px-8"
                imageClassName="max-h-9"
            />

            {app.installed > 0 ? (
                <span
                    title={
                        app.installed > 1
                            ? `${app.installed} bağlantı kurulu`
                            : 'Kurulu'
                    }
                    className="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-emerald-600 text-white"
                >
                    <Check className="size-3.5" />
                </span>
            ) : app.available ? (
                <span className="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-neutral-100 text-neutral-500 dark:bg-white/10 dark:text-neutral-300">
                    <Plus className="size-3.5" />
                </span>
            ) : (
                <span className="absolute top-2 right-2 rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-400 dark:bg-white/10 dark:text-neutral-500">
                    Yakında
                </span>
            )}
        </PermissionButton>
    );
}
