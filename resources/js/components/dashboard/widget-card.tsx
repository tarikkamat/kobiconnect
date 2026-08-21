import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/**
 * Panel widget'i. Her widget TIKLANABILIR ve kendi ekranina goturur — sayi
 * gostermek tek basina ise yaramaz, operatorun oradan devam edebilmesi gerekir.
 */
export function WidgetCard({
    title,
    href,
    alert = false,
    children,
}: {
    title: string;
    href: string;
    /** Dikkat isteyen widget cerceve rengiyle de ayrisir; renk tek basina bilgi tasimaz. */
    alert?: boolean;
    children: ReactNode;
}) {
    return (
        <Card
            className={cn(
                'relative transition-colors hover:border-ring',
                alert && 'border-amber-500',
            )}
        >
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    <Link
                        href={href}
                        prefetch
                        className="flex items-center gap-1 after:absolute after:inset-0"
                    >
                        {title}
                        <ChevronRight className="size-3.5" />
                    </Link>
                </CardTitle>
            </CardHeader>
            {/* Icerik baglantilari kartin genis tiklama alaninin USTUNDE kalir. */}
            <CardContent className="relative z-10">{children}</CardContent>
        </Card>
    );
}

/** Deferred widget'in yanip sonen iskeleti — FRONTEND-PLAN §3. */
export function WidgetSkeleton({ rows = 2 }: { rows?: number }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <Skeleton className="h-4 w-32" />
            </CardHeader>
            <CardContent className="space-y-2">
                {Array.from({ length: rows }, (_, row) => (
                    <Skeleton key={row} className="h-6 w-full" />
                ))}
            </CardContent>
        </Card>
    );
}
