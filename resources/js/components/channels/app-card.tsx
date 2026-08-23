import { Check, Plus, Store } from 'lucide-react';
import { PermissionButton } from '@/components/catalog/permission-button';
import type {
    ConnectionRow,
    CredentialField,
} from '@/components/channels/connection-drawer';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    connections?: ConnectionRow[];
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
                'flex items-center justify-center bg-white overflow-hidden',
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
                    'w-full h-full object-contain',
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
    onManage,
}: {
    app: StoreApp;
    canManage: PermissionCheck;
    onInstall: (app: StoreApp) => void;
    onManage?: (app: StoreApp) => void;
}) {
    const check: PermissionCheck = app.available
        ? canManage
        : { allowed: false, reason: 'Bu uygulama henüz yayında değil.' };

    if (app.installed > 0) {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        className="group relative flex h-24 w-full cursor-pointer items-center justify-center rounded-xl border border-border bg-card p-0 transition-colors hover:border-foreground/30 hover:bg-secondary focus-visible:outline-none"
                        title={`${app.name} — bağlantıları yönet`}
                    >
                        <AppIcon
                            app={app}
                            className="size-full rounded-xl px-6 bg-transparent"
                            imageClassName="max-h-10 max-w-[140px]"
                        />

                        <span
                            title={
                                app.installed > 1
                                    ? `${app.installed} bağlantı kurulu`
                                    : 'Kurulu'
                            }
                            className="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground font-mono text-[11px] font-medium"
                        >
                            {app.installed > 1 ? (
                                <span className="tabular-nums">{app.installed}</span>
                            ) : (
                                <Check className="size-3.5" />
                            )}
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <DropdownMenuItem
                        onClick={() => onManage?.(app)}
                        className="cursor-pointer gap-2 py-2"
                    >
                        <Store className="size-4 text-primary" />
                        <div>
                            <p className="text-xs font-medium">
                                Mevcut Bağlantıları Görüntüle
                            </p>
                            <p className="font-mono text-[10px] text-muted-foreground tabular-nums">
                                {app.installed} mağaza hesabı kayıtlı
                            </p>
                        </div>
                    </DropdownMenuItem>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem
                        onClick={() => onInstall(app)}
                        disabled={!canManage.allowed}
                        className="cursor-pointer gap-2 py-2"
                    >
                        <Plus className="size-4" />
                        <span className="text-xs font-medium">
                            Yeni Bağlantı / Mağaza Ekle
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <PermissionButton
            check={check}
            variant="outline"
            className="relative h-24 w-full rounded-xl border border-border bg-card p-0 hover:border-foreground/30 hover:bg-secondary disabled:opacity-100 transition-colors"
            title={app.name}
            aria-label={`${app.name} aktifleştir`}
            onClick={() => onInstall(app)}
        >
            <AppIcon
                app={app}
                className="size-full rounded-xl px-6 bg-transparent"
                imageClassName="max-h-10 max-w-[140px]"
            />

            {app.available ? (
                <span className="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-secondary text-muted-foreground">
                    <Plus className="size-3.5" />
                </span>
            ) : (
                <span className="absolute top-2 right-2 rounded-full bg-secondary px-2 py-0.5 text-[11px] font-medium text-foreground/30">
                    Yakında
                </span>
            )}
        </PermissionButton>
    );
}


