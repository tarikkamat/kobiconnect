import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, update } from '@/routes/notification-preferences';

type Event = {
    value: string;
    label: string;
    group: string;
};

type Channel = {
    value: string;
    label: string;
    /** Yapilandirilmamis kanal (ornegin broadcast) secilemez. */
    available: boolean;
};

type Props = {
    events: Event[];
    channels: Channel[];
    /** Olay -> secili kanallar. Satiri olmayan olay icin varsayilanlar gelir. */
    preferences: Record<string, string[]>;
};

/**
 * Olay x kanal matrisi — BACKEND-PLAN §11.3.
 *
 * Tercih KISIYE ozeldir; baskasinin tercihini goren veya degistiren bir yol
 * yok, bu yuzden burada yetki kontrolu de yok.
 *
 * Isaretlenmemis kutu FORMLA GONDERILMEZ; sunucu matrisin tamamini olaylar
 * uzerinden yeniden yazar, dolayisiyla "hicbir kanal" tercihi kaybolmaz.
 */
export default function NotificationPreferences({
    events,
    channels,
    preferences,
}: Props) {
    const groups = [...new Set(events.map((event) => event.group))];

    return (
        <>
            <Head title="Bildirim Tercihleri" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Bildirim Tercihleri"
                    description="Hangi olayı hangi kanaldan duymak istediğinizi seçin. Bu tercihler yalnızca sizi ilgilendirir; ekip arkadaşlarınızın tercihleri ayrıdır."
                />

                <Form
                    {...update.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing }) => (
                        <>
                            {groups.map((group) => (
                                <div
                                    key={group}
                                    className="overflow-hidden rounded-lg border border-border"
                                >
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>{group}</TableHead>
                                                {channels.map((channel) => (
                                                    <TableHead
                                                        key={channel.value}
                                                        className="w-24 text-center"
                                                    >
                                                        {channel.available ? (
                                                            channel.label
                                                        ) : (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <span className="text-muted-foreground">
                                                                        {
                                                                            channel.label
                                                                        }
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Bu kanal
                                                                    henüz
                                                                    yapılandırılmadı;
                                                                    seçilse bile
                                                                    bildirim
                                                                    ulaşmaz.
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        )}
                                                    </TableHead>
                                                ))}
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {events
                                                .filter(
                                                    (event) =>
                                                        event.group === group,
                                                )
                                                .map((event) => (
                                                    <TableRow key={event.value}>
                                                        <TableCell>
                                                            {event.label}
                                                        </TableCell>
                                                        {channels.map(
                                                            (channel) => (
                                                                <TableCell
                                                                    key={
                                                                        channel.value
                                                                    }
                                                                    className="text-center"
                                                                >
                                                                    <Checkbox
                                                                        name={`preferences[${event.value}][${channel.value}]`}
                                                                        value="on"
                                                                        disabled={
                                                                            !channel.available
                                                                        }
                                                                        defaultChecked={(
                                                                            preferences[
                                                                                event
                                                                                    .value
                                                                            ] ??
                                                                            []
                                                                        ).includes(
                                                                            channel.value,
                                                                        )}
                                                                        aria-label={`${event.label} — ${channel.label}`}
                                                                    />
                                                                </TableCell>
                                                            ),
                                                        )}
                                                    </TableRow>
                                                ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            ))}

                            <Button type="submit" disabled={processing}>
                                Tercihleri kaydet
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

NotificationPreferences.layout = {
    breadcrumbs: [
        {
            title: 'Bildirim Tercihleri',
            href: edit(),
        },
    ],
};
