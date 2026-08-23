import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { MarketplaceMarquee } from '@/components/marketplace-marquee';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * shadcn `login-02` blogu: solda form, sagda kapak paneli. Kapakta pazaryeri
 * logolarinin capraz aktigi marquee durur (bkz. MarketplaceMarquee).
 */
export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh lg:grid-cols-2">
            <div className="flex flex-col gap-4 p-6 md:p-10">
                <div className="flex justify-center gap-2 md:justify-start">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-medium"
                    >
                        <div className="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <AppLogoIcon className="size-4 fill-current" />
                        </div>
                        KobiConnect
                    </Link>
                </div>

                <div className="flex flex-1 items-center justify-center">
                    <div className="w-full max-w-sm py-8">
                        <div className="mb-6 flex flex-col items-center gap-1 text-center">
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {title}
                            </h1>
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>

                        {children}
                    </div>
                </div>
            </div>

            <div className="relative hidden overflow-hidden border-l border-border bg-background lg:block">
                <MarketplaceMarquee className="absolute inset-0" />

                {/* ponytail: marquee koyu zeminli, o yuzden metin acik tonlu. */}
                <div className="absolute inset-x-0 bottom-0 flex flex-col gap-3 p-10 text-foreground">
                    <div className="flex items-center gap-2 font-mono text-xs font-medium text-muted-foreground tabular-nums">
                        <span className="relative flex size-2">
                            <span className="absolute inline-flex size-full animate-ping rounded-full bg-primary/60 motion-reduce:hidden" />
                            <span className="relative inline-flex size-2 rounded-full bg-primary" />
                        </span>
                        12+ pazaryeri ve e-ticaret altyapısı
                    </div>
                    <p className="text-3xl font-semibold tracking-tight text-balance text-foreground">
                        Tüm satış kanallarınız, tek panel.
                    </p>
                    <p className="max-w-md text-sm text-muted-foreground">
                        Ürünlerinizi bir kez ekleyin; Trendyol&apos;dan
                        Amazon&apos;a her kanalda satın. Stok, fiyat ve
                        siparişler kendiliğinden senkronda kalır.
                    </p>
                    <div className="mt-2 flex items-center gap-3">
                        <div className="flex -space-x-2">
                            {['AY', 'MK', 'EÖ', 'SD', 'ZT'].map((initials) => (
                                <Avatar
                                    key={initials}
                                    className="size-9 ring-2 ring-background"
                                >
                                    <AvatarFallback className="bg-secondary text-xs font-medium text-muted-foreground">
                                        {initials}
                                    </AvatarFallback>
                                </Avatar>
                            ))}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            <span className="font-mono font-medium text-foreground tabular-nums">
                                1.000+ KOBİ
                            </span>{' '}
                            satışını KobiConnect ile yönetiyor
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
