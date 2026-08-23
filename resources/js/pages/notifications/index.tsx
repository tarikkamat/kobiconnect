import { Form, Head, InfiniteScroll, Link, router } from '@inertiajs/react';
import { BellOff, CheckCheck } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import type { NotificationItem } from '@/components/notifications/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { edit as preferences } from '@/routes/notification-preferences';
import { index, read } from '@/routes/notifications';

type Filters = {
    unread: boolean;
    event: string | null;
};

type Props = {
    notifications: { data: NotificationItem[] };
    filters: Filters;
    unreadCount: number;
    events: { value: string; label: string }[];
};

/** Radix Select bos deger kabul etmez; "hepsi" secenegi bu sabitle temsil edilir. */
const ALL = 'all';

/**
 * Bildirim merkezi — BACKEND-PLAN §11.3.
 *
 * Zil yalnizca son sekiz kaydi gosterir; burasi tam gecmistir. Bildirim
 * KISIYE ozeldir, sunucu her sorguyu `$request->user()` uzerinden kurar.
 */
export default function Notifications({
    notifications,
    filters,
    unreadCount,
    events,
}: Props) {
    const apply = (changes: Partial<Filters>): void => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) =>
                    value !== null && value !== '' && value !== false,
            ),
        );

        router.get(
            index.url(undefined, { query }),
            {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Bildirimler" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Bildirimler"
                        description={
                            unreadCount > 0
                                ? `${unreadCount} okunmamış bildiriminiz var.`
                                : 'Okunmamış bildiriminiz yok.'
                        }
                    />

                    <Link
                        href={preferences()}
                        className="text-sm underline underline-offset-4"
                    >
                        Bildirim tercihleri
                    </Link>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant={filters.unread ? 'default' : 'outline'}
                        aria-pressed={filters.unread}
                        onClick={() => apply({ unread: !filters.unread })}
                    >
                        Yalnızca okunmamışlar
                    </Button>

                    <Select
                        value={filters.event ?? ALL}
                        onValueChange={(value) =>
                            apply({ event: value === ALL ? null : value })
                        }
                    >
                        <SelectTrigger className="w-64" aria-label="Olay">
                            <SelectValue placeholder="Olay" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>Tüm olaylar</SelectItem>
                            {events.map((event) => (
                                <SelectItem
                                    key={event.value}
                                    value={event.value}
                                >
                                    {event.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Tekil ve toplu okundu isareti AYNI ucu kullanir; tek
                        fark `id` alaninin gonderilip gonderilmedigidir. */}
                    <Form {...read.form()} options={{ preserveScroll: true }}>
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={unreadCount === 0}
                        >
                            <CheckCheck />
                            Tümünü okundu işaretle
                        </Button>
                    </Form>
                </div>

                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={BellOff}
                        title={
                            filters.unread
                                ? 'Okunmamış bildiriminiz yok'
                                : 'Henüz bildiriminiz yok'
                        }
                        description={
                            filters.unread
                                ? 'Tüm bildirimlerinizi okudunuz.'
                                : 'Siparişler, stok uyarıları ve senkronizasyon bildirimleri burada görüntülenir.'
                        }
                    />
                ) : (
                    <InfiniteScroll data="notifications" buffer={300}>
                        <ul className="flex flex-col gap-2">
                            {notifications.data.map((item) => (
                                <li
                                    key={item.id}
                                    className={
                                        item.read
                                            ? 'rounded-lg border border-border p-4'
                                            : 'rounded-lg border border-primary/25 bg-primary/10 p-4'
                                    }
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="font-medium">
                                                {item.title}
                                            </p>
                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                {item.body}
                                            </p>
                                            <p className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <Badge variant="outline">
                                                    {item.eventLabel ?? '—'}
                                                </Badge>
                                                {item.group !== null && (
                                                    <span>{item.group}</span>
                                                )}
                                                <span className="font-mono tabular-nums">
                                                    {item.createdAt ?? '—'}
                                                </span>
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-2">
                                            {item.url !== null && (
                                                <Link
                                                    href={item.url}
                                                    instant
                                                    className="text-sm underline underline-offset-4"
                                                >
                                                    Aç
                                                </Link>
                                            )}
                                            {!item.read && (
                                                <Form
                                                    {...read.form()}
                                                    options={{
                                                        preserveScroll: true,
                                                    }}
                                                >
                                                    <input
                                                        type="hidden"
                                                        name="id"
                                                        value={item.id}
                                                    />
                                                    <Button
                                                        type="submit"
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        Okundu işaretle
                                                    </Button>
                                                </Form>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </InfiniteScroll>
                )}
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [
        {
            title: 'Bildirimler',
            href: index(),
        },
    ],
};
