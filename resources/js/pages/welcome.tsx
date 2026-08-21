import { Head, Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { login } from '@/routes/central';
import { register } from '@/routes/onboarding';

/*
 * Pazarlama sitesi ayri bir projedir (kobiconnect.com). Bu host
 * (app.kobiconnect.com) yalnizca uygulamadir, bu yuzden kok sayfa panelin
 * bulanik bir onizlemesi + giris/kayit kapisidir.
 *
 * ponytail: arkadaki panel GERCEK dashboard degil, statik bir taklit —
 * oturum yokken gercek veri de yok. Kapi acilmadan once gosterilecek gercek
 * bir icerik olmadigindan tasarim disi bir maliyeti yok.
 */
export default function Welcome() {
    return (
        <>
            <Head title="KobiConnect" />

            <div className="relative min-h-svh overflow-hidden bg-background">
                <div
                    aria-hidden
                    className="pointer-events-none flex h-svh blur-[6px] select-none"
                >
                    <aside className="hidden w-64 shrink-0 flex-col gap-3 border-r border-sidebar-border/70 bg-sidebar p-4 md:flex">
                        <div className="flex items-center gap-2">
                            <div className="size-8 rounded-md bg-muted" />
                            <div className="h-3 w-28 rounded bg-muted" />
                        </div>
                        <div className="mt-4 flex flex-col gap-2">
                            {Array.from({ length: 7 }).map((_, index) => (
                                <div
                                    key={index}
                                    className="h-8 rounded-md bg-muted/70"
                                />
                            ))}
                        </div>
                    </aside>

                    <div className="flex flex-1 flex-col">
                        <header className="flex h-16 items-center gap-3 border-b border-sidebar-border/70 px-6">
                            <div className="h-3 w-40 rounded bg-muted" />
                            <div className="ml-auto size-8 rounded-full bg-muted" />
                        </header>

                        <div className="flex flex-1 flex-col gap-4 p-4">
                            <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                                {Array.from({ length: 3 }).map((_, index) => (
                                    <div
                                        key={index}
                                        className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70"
                                    >
                                        <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                                    </div>
                                ))}
                            </div>
                            <div className="relative min-h-64 flex-1 overflow-hidden rounded-xl border border-sidebar-border/70">
                                <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="absolute inset-0 flex items-center justify-center bg-background/60 p-6 backdrop-blur-sm">
                    <div className="flex w-full max-w-sm flex-col items-center gap-8 rounded-xl border bg-background/95 p-8 shadow-lg">
                        <div className="flex flex-col items-center gap-3 text-center">
                            <AppLogoIcon className="size-10 fill-current text-foreground" />
                            <h1 className="text-2xl font-medium">
                                KobiConnect
                            </h1>
                            <p className="text-sm text-balance text-muted-foreground">
                                Ürünlerinizi, stoğunuzu ve siparişlerinizi tek
                                panelden yönetin; pazaryerlerine tek yerden
                                bağlanın.
                            </p>
                        </div>

                        <div className="flex w-full flex-col gap-3">
                            <Button asChild>
                                <Link href={register()}>
                                    Ücretsiz hesap oluştur
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={login()}>Giriş yap</Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
