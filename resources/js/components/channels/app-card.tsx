import { Link } from '@inertiajs/react';
import { Check, Lock, Clock } from 'lucide-react';
import type { CredentialField } from '@/components/channels/connection-drawer';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { show } from '@/routes/apps';

/**
 * Magazadaki bir uygulama — `config/apps.php` + surucu kaydinin birlesimi
 * (App\Support\AppCatalog). `available` surucusu var mi, `entitled` lisans
 * izin veriyor mu demektir; ikisi ayri sorudur ve kart ikisini ayri gosterir.
 */
export type StoreApp = {
    code: string;
    name: string;
    category: string;
    categoryLabel: string;
    summary: string;
    logo: string;
    capabilities: { value: string; label: string }[];
    available: boolean;
    entitled: boolean;
    /** `null` = plana dahil, ayrica ucretlendirilmiyor. */
    price: { monthly: string; yearly: string } | null;
    fields: CredentialField[];
    installed: number;
};

/**
 * Marka logolari koyu renkli wordmark'lardir (Trendyol #231F20, ikas #1C1C1A);
 * karanlik temada kaybolmamalari icin her zaman beyaz bir zemin uzerinde durur
 * — App Store'larin tamaminin yaptigi sey.
 */
export function AppIcon({
    app,
    className,
}: {
    app: Pick<StoreApp, 'logo' | 'name'>;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex size-12 shrink-0 items-center justify-center rounded-xl bg-white p-2 ring-1 ring-black/5',
                className,
            )}
        >
            <img
                src={app.logo}
                alt={app.name}
                loading="lazy"
                className="max-h-full max-w-full object-contain"
            />
        </div>
    );
}

export function AppStatusBadge({ app }: { app: StoreApp }) {
    if (!app.available) {
        return (
            <Badge variant="secondary">
                <Clock /> Yakında
            </Badge>
        );
    }

    if (!app.entitled) {
        return (
            <Badge variant="outline" className="text-muted-foreground">
                <Lock /> Planınızda yok
            </Badge>
        );
    }

    if (app.installed > 0) {
        return (
            <Badge variant="outline" className="border-emerald-600/30 text-emerald-700 dark:text-emerald-400">
                <Check />
                {app.installed > 1 ? `${app.installed} bağlantı` : 'Kurulu'}
            </Badge>
        );
    }

    return null;
}

export function AppCard({ app }: { app: StoreApp }) {
    return (
        <Link
            href={show({ app: app.code })}
            className="group flex flex-col gap-4 rounded-xl border p-5 transition-colors hover:border-foreground/20 hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3">
                    <AppIcon app={app} />
                    <div>
                        <div className="font-medium">{app.name}</div>
                        <div className="text-xs text-muted-foreground">
                            {app.categoryLabel}
                        </div>
                    </div>
                </div>

                <AppStatusBadge app={app} />
            </div>

            <p className="line-clamp-2 text-sm text-muted-foreground">
                {app.summary}
            </p>

            <div className="mt-auto flex items-center justify-between gap-2">
                <div className="flex flex-wrap gap-1">
                    {app.capabilities.slice(0, 3).map((capability) => (
                        <Badge key={capability.value} variant="outline">
                            {capability.label}
                        </Badge>
                    ))}
                    {app.capabilities.length > 3 ? (
                        <Badge variant="outline">
                            +{app.capabilities.length - 3}
                        </Badge>
                    ) : null}
                </div>

                <span className="shrink-0 text-xs whitespace-nowrap text-muted-foreground">
                    {app.price === null ? 'Planınıza dahil' : app.price.monthly}
                </span>
            </div>
        </Link>
    );
}
