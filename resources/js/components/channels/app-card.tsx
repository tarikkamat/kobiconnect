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
 * Tema tek yonlu koyu; zemin `logoDarkInvert` ile belirlenir: koyu
 * wordmark'lar (Trendyol #231F20, ikas #1C1C1A) seffaf zeminde tamamen
 * beyaza boyanir, renkli logolar (n11, WooCommerce...) oldugu gibi kalir.
 * Bayragi BILMEYEN cagiranlar (`logoDarkInvert` undefined, orn. avatar
 * yigini) guvenli davranisi alir: beyaz plaka uzerinde logo.
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
                app.logoDarkInvert !== undefined && 'bg-transparent',
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
                    app.logoDarkInvert && 'brightness-0 invert',
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
        <div className="flex flex-col justify-between gap-5 rounded-xl border border-border bg-card/70 p-5 transition-colors duration-150 hover:border-white/20 hover:bg-card">
            <div className="flex flex-col gap-3.5">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex size-12 shrink-0 items-center justify-center rounded-lg border border-border bg-secondary/80 p-2">
                        <AppIcon
                            app={app}
                            className="size-full bg-transparent"
                            imageClassName="max-h-7"
                        />
                    </div>

                    {app.installed > 0 ? (
                        <span
                            title={
                                app.installed > 1
                                    ? `${app.installed} bağlantı kurulu`
                                    : 'Bağlantı kurulu'
                            }
                            className="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 font-mono text-[11px] font-medium text-primary"
                        >
                            <Check className="size-3" />
                            <span className="tabular-nums">
                                {app.installed > 1
                                    ? `${app.installed} Bağlantı`
                                    : 'Bağlı'}
                            </span>
                        </span>
                    ) : app.available ? (
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-white/[0.04] px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground">
                            <span className="size-1.5 rounded-full bg-primary" />
                            Hazır
                        </span>
                    ) : (
                        <span className="rounded-full border border-border bg-secondary px-2.5 py-0.5 text-[11px] font-medium text-foreground/40">
                            Yakında
                        </span>
                    )}
                </div>

                <div className="space-y-0.5">
                    <h3 className="text-base font-semibold tracking-tight text-foreground">
                        {app.name}
                    </h3>
                    <p className="text-xs text-muted-foreground">
                        {app.categoryLabel}
                    </p>
                </div>

                {app.capabilities.length > 0 ? (
                    <div className="flex flex-wrap gap-1 pt-0.5">
                        {app.capabilities.map((capability) => (
                            <span
                                key={capability.value}
                                className="inline-flex h-5 items-center rounded border border-white/5 bg-white/[0.03] px-1.5 font-mono text-[10px] text-muted-foreground"
                            >
                                {capability.label}
                            </span>
                        ))}
                    </div>
                ) : (
                    <p className="text-xs leading-relaxed text-muted-foreground/60">
                        Entegrasyon sürücüsü geliştirme aşamasındadır.
                    </p>
                )}
            </div>

            <div className="pt-2">
                {app.available ? (
                    <PermissionButton
                        check={check}
                        variant={app.installed > 0 ? 'outline' : 'default'}
                        size="sm"
                        className="w-full justify-center gap-1.5 text-xs font-medium"
                        onClick={() => onInstall(app)}
                    >
                        <Plus className="size-3.5" />
                        {app.installed > 0
                            ? 'Yeni Bağlantı Ekle'
                            : 'Bağlantı Kur'}
                    </PermissionButton>
                ) : (
                    <span
                        aria-disabled="true"
                        className="flex h-[34px] w-full cursor-not-allowed items-center justify-center rounded-md border border-dashed border-border bg-secondary/50 text-xs font-medium text-muted-foreground/50"
                    >
                        Yakında
                    </span>
                )}
            </div>
        </div>
    );
}
