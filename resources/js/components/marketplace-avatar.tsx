import { AppIcon } from '@/components/channels/app-card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Pazaryeri logosu, avatar boyunda. Logo yolu koddan türetilir
 * (`/apps/{kod}.svg`) — `AppCatalog::present()` ile aynı kaynak. Beyaz karo
 * `AppIcon`'dan gelir: koyu wordmark'lar karanlık temada da okunur kalır.
 */
export function MarketplaceAvatar({
    code,
    name,
    size = 'md',
    className,
}: {
    code: string;
    name?: string;
    size?: 'sm' | 'md';
    className?: string;
}) {
    return (
        <AppIcon
            app={{ logo: `/apps/${code}.svg`, name: name ?? code }}
            className={cn(
                'shrink-0 ring-1 ring-border',
                size === 'sm'
                    ? 'size-6 rounded-md p-1'
                    : 'size-8 rounded-lg p-1.5',
                className,
            )}
        />
    );
}

export type MarketplaceChannel = {
    marketplace: string;
    name: string;
    /** channel_listings.sync_state — `App\Enums\ListingSyncState`. */
    state: string;
};

const STATE_LABELS: Record<string, string> = {
    pending: 'Gönderim bekliyor',
    syncing: 'Gönderiliyor',
    synced: 'Satışta',
    failed: 'Gönderim hatası',
};

/**
 * Bir varyantın satışta olduğu kanallar, üst üste binen avatarlar halinde.
 * Kimlik logodan okunur; durum tooltip'te ve hatalı kanalda kırmızı halkada.
 */
export function MarketplaceAvatarStack({
    channels,
}: {
    channels: MarketplaceChannel[];
}) {
    if (channels.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex items-center -space-x-1.5">
            {channels.map((channel) => (
                <Tooltip key={`${channel.marketplace}-${channel.name}`}>
                    <TooltipTrigger asChild>
                        <span tabIndex={0} className="rounded-md">
                            <MarketplaceAvatar
                                code={channel.marketplace}
                                name={channel.name}
                                size="sm"
                                className={cn(
                                    'ring-2 ring-background outline outline-border',
                                    channel.state === 'failed' &&
                                        'outline-2 outline-destructive',
                                )}
                            />
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        {channel.name} ·{' '}
                        {STATE_LABELS[channel.state] ?? channel.state}
                    </TooltipContent>
                </Tooltip>
            ))}
        </div>
    );
}
