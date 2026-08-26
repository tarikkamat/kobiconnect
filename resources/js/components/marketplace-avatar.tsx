import { AppIcon } from '@/components/channels/app-card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/**
 * Pazaryeri logosu doğrudan görünüm (çerçevesiz, temiz, orijinal oranında).
 * Trendyol, Hepsiburada vb. logoların yatay oranlarını korur ve net gösterir.
 */
export function MarketplaceLogo({
    code,
    name,
    className,
    imageClassName,
    height = 'h-5 sm:h-6',
}: {
    code: string;
    name?: string;
    className?: string;
    imageClassName?: string;
    height?: string;
}) {
    return (
        <div className={cn('inline-flex shrink-0 items-center', className)}>
            <img
                src={`/apps/${code}.svg`}
                alt={name ?? code}
                loading="lazy"
                className={cn(
                    'max-h-7 w-auto max-w-[85px] object-contain object-left',
                    height,
                    imageClassName,
                )}
            />
        </div>
    );
}

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
    imageClassName,
}: {
    code: string;
    name?: string;
    size?: 'sm' | 'md' | 'lg';
    className?: string;
    imageClassName?: string;
}) {
    return (
        <AppIcon
            app={{ logo: `/apps/${code}.svg`, name: name ?? code }}
            className={cn(
                'shrink-0 bg-white ring-1 ring-border',
                size === 'sm' && 'size-7 rounded-full p-0.5',
                size === 'md' && 'size-8 rounded-full p-0.5',
                size === 'lg' && 'size-10 rounded-full p-1',
                className,
            )}
            imageClassName={cn('h-full w-full object-contain', imageClassName)}
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
    size = 'md',
    className,
}: {
    channels: MarketplaceChannel[];
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}) {
    if (channels.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className={cn('flex items-center -space-x-2', className)}>
            {channels.map((channel) => (
                <Tooltip key={`${channel.marketplace}-${channel.name}`}>
                    <TooltipTrigger asChild>
                        <span tabIndex={0} className="inline-flex rounded-full">
                            <MarketplaceAvatar
                                code={channel.marketplace}
                                name={channel.name}
                                size={size}
                                className={cn(
                                    'rounded-full bg-white ring-2 ring-background outline outline-1 outline-border',
                                    channel.state === 'failed' &&
                                        'outline-2 outline-destructive',
                                )}
                                imageClassName="w-full h-full object-contain"
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
