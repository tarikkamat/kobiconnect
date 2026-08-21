import { Link, useHttp } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { NotificationItem } from '@/components/notifications/types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { feed, index, read } from '@/routes/notifications';

type Feed = {
    unreadCount: number;
    items: NotificationItem[];
};

const EMPTY: Feed = { unreadCount: 0, items: [] };

/**
 * Panel zili — FRONTEND-PLAN §2, §3.
 *
 * Sayac ve son bildirimler PAYLASILAN PROP'TA DEGIL: paylasilan prop her
 * Inertia yanitina binerdi, oysa bu veri yalnizca zil acildiginda okunur.
 * Bunun yerine `useHttp` ile ayri bir JSON ucu cagrilir — sayfa basina tek
 * XHR, gezinmeyi hic beklemez.
 *
 * ponytail: websocket yok, poll yok. Sayfa gecisinde tazelenmesi bir KOBI
 * paneli icin yeterli; canli itme gerekirse `usePoll` yerine broadcast kanali
 * acilir (NotificationChannel::Broadcast zaten hazir).
 */
export function NotificationBell() {
    const { submit } = useHttp();
    const [state, setState] = useState<Feed>(EMPTY);

    // Yanit bir GERI CAGRIDA islenir: efekt govdesinde senkron setState
    // cagirmak zincirleme render uretir (react-hooks/set-state-in-effect).
    const load = useCallback((): void => {
        submit(feed())
            .then((data) => setState(data as Feed))
            .catch(() => {
                // Zil sessiz kalir. Bildirim bir defter degil bir uyaridir;
                // ucun cokmesi panelin geri kalanini bozmamali.
            });
    }, [submit]);

    useEffect(load, [load]);

    const markAllRead = async (): Promise<void> => {
        await submit(read());
        load();
    };

    return (
        <DropdownMenu
            onOpenChange={(open) => {
                if (open) {
                    load();
                }
            }}
        >
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={
                        state.unreadCount > 0
                            ? `Bildirimler (${state.unreadCount} okunmamış)`
                            : 'Bildirimler'
                    }
                >
                    <Bell />
                    {state.unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-none font-medium text-white">
                            {state.unreadCount > 99 ? '99+' : state.unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-96 p-0">
                <div className="flex items-center justify-between gap-2 px-3 py-2">
                    <span className="text-sm font-medium">Bildirimler</span>
                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={state.unreadCount === 0}
                        onClick={(event) => {
                            event.preventDefault();
                            void markAllRead();
                        }}
                    >
                        Tümünü okundu işaretle
                    </Button>
                </div>

                <DropdownMenuSeparator className="m-0" />

                {state.items.length === 0 ? (
                    <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                        Bildiriminiz yok.
                    </p>
                ) : (
                    <ul className="max-h-96 overflow-y-auto">
                        {state.items.map((item) => (
                            <li key={item.id}>
                                <BellRow item={item} />
                            </li>
                        ))}
                    </ul>
                )}

                <DropdownMenuSeparator className="m-0" />

                <Link
                    href={index()}
                    className="block px-3 py-2 text-center text-sm underline-offset-4 hover:underline"
                >
                    Tüm bildirimler
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * Satir kendi hedefine gider; okundu isaretlemesi tekil olarak bildirim
 * SAYFASINDA yapilir. Zil bir gorev listesi degil, bir ozet.
 */
function BellRow({ item }: { item: NotificationItem }) {
    const body = (
        <>
            <span className="flex items-start gap-2">
                {!item.read && (
                    <span
                        className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                        aria-label="Okunmadı"
                    />
                )}
                <span className="text-sm font-medium">{item.title}</span>
            </span>
            <span className="mt-0.5 block text-xs text-muted-foreground">
                {item.body}
            </span>
            <span className="mt-0.5 block text-xs text-muted-foreground">
                {item.eventLabel ?? '—'} · {item.createdAt ?? '—'}
            </span>
        </>
    );

    const className = 'block px-3 py-2 hover:bg-accent';

    if (item.url === null) {
        return <span className={className}>{body}</span>;
    }

    return (
        <Link href={item.url} className={className}>
            {body}
        </Link>
    );
}
