import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { IntegrationsDiagram } from '@/components/integrations-diagram';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * shadcn `login-02` blogu: solda form, sagda kapak paneli.
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
                            <h1 className="text-2xl font-bold">{title}</h1>
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        </div>

                        {children}
                    </div>
                </div>
            </div>

            <div className="relative hidden overflow-hidden bg-muted lg:block">
                <IntegrationsDiagram className="absolute inset-0 size-full" />
                {/* ponytail: sema acik tonlu, o yuzden metin koyu. */}
                <div className="absolute inset-x-0 bottom-0 flex flex-col gap-2 p-10 text-neutral-800">
                    <p className="text-2xl font-medium text-balance">
                        Pazaryerlerinizi tek panelden yönetin.
                    </p>
                    <p className="text-sm text-neutral-600">
                        Ürün, stok, fiyat ve siparişler; Trendyol, Hepsiburada
                        ve diğer kanallar için tek yerde.
                    </p>
                </div>
            </div>
        </div>
    );
}
