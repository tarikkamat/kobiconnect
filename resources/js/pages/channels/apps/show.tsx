import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AppIcon, AppStatusBadge } from '@/components/channels/app-card';
import type { StoreApp } from '@/components/channels/app-card';
import { ConnectionDrawer } from '@/components/channels/connection-drawer';
import type { ConnectionRow } from '@/components/channels/connection-drawer';
import { ConnectionStatusBadge } from '@/components/channels/connection-status-badge';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { usePermission } from '@/hooks/use-permission';
import type { PermissionCheck } from '@/hooks/use-permission';
import { index } from '@/routes/apps';
import { destroy, health } from '@/routes/connections';
import { index as license } from '@/routes/license';

type Props = {
    app: StoreApp;
    connections: ConnectionRow[];
};

export default function AppShow({ app, connections }: Props) {
    const canManage = usePermission()('channels.manage');
    const [setup, setSetup] = useState<{ connection: ConnectionRow | null } | null>(null);
    const [pendingDelete, setPendingDelete] = useState<ConnectionRow | null>(null);

    // Kilit bir gostergedir, yaptirim sunucudadir (ConnectionController::store).
    const installCheck: PermissionCheck = !app.available
        ? { allowed: false, reason: 'Bu uygulama henüz yayında değil.' }
        : !app.entitled
          ? {
                allowed: false,
                reason: 'Bu uygulama planınıza dahil değil. Planınızı yükselterek kurabilirsiniz.',
            }
          : canManage;

    const test = (connection: ConnectionRow): void => {
        router.post(
            health.url({ connection: connection.id }),
            {},
            { preserveScroll: true, onError: toastError },
        );
    };

    return (
        <>
            <Head title={app.name} />

            <div className="flex flex-col gap-6 p-4">
                <Link
                    href={index()}
                    className="flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Uygulama Mağazası
                </Link>

                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-4">
                        <AppIcon app={app} className="size-16 rounded-2xl p-3" />
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl font-semibold">
                                    {app.name}
                                </h1>
                                <AppStatusBadge app={app} />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {app.categoryLabel}
                            </p>
                        </div>
                    </div>

                    <PermissionButton
                        check={installCheck}
                        onClick={() => setSetup({ connection: null })}
                    >
                        <Plus />
                        {connections.length === 0
                            ? 'Kur'
                            : 'Yeni bağlantı ekle'}
                    </PermissionButton>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <section className="flex flex-col gap-3 rounded-xl border p-5">
                            <h2 className="font-medium">Ne yapar?</h2>
                            <p className="text-sm text-muted-foreground">
                                {app.summary}
                            </p>

                            {app.capabilities.length === 0 ? null : (
                                <div className="flex flex-wrap gap-1 pt-1">
                                    {app.capabilities.map((capability) => (
                                        <Badge
                                            key={capability.value}
                                            variant="outline"
                                        >
                                            {capability.label}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                        </section>

                        <section className="flex flex-col gap-3">
                            <h2 className="text-sm font-medium">
                                Kurulu bağlantılar
                            </h2>

                            {connections.length === 0 ? (
                                <div className="rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground">
                                    {app.available
                                        ? 'Henüz bağlantı kurmadınız. “Kur” ile mağaza kimlik bilgilerinizi girin.'
                                        : 'Bu uygulama yakında kullanıma açılacak.'}
                                </div>
                            ) : (
                                <div className="rounded-xl border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Bağlantı</TableHead>
                                                <TableHead>Durum</TableHead>
                                                <TableHead>
                                                    Son kontrol
                                                </TableHead>
                                                <TableHead>
                                                    Webhook adresi
                                                </TableHead>
                                                <TableHead className="w-32" />
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {connections.map((connection) => (
                                                <TableRow
                                                    key={connection.id}
                                                    className="align-top"
                                                >
                                                    <TableCell>
                                                        <div className="font-medium">
                                                            {connection.name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {connection.sellerId ||
                                                                '—'}
                                                        </div>
                                                    </TableCell>

                                                    <TableCell>
                                                        <ConnectionStatusBadge
                                                            status={
                                                                connection.status
                                                            }
                                                            label={
                                                                connection.statusLabel
                                                            }
                                                        />
                                                        {connection.lastHealthError ===
                                                        null ? null : (
                                                            <p
                                                                title={
                                                                    connection.lastHealthError
                                                                }
                                                                className="mt-1 max-w-64 text-xs text-red-600 dark:text-red-400"
                                                            >
                                                                {
                                                                    connection.lastHealthError
                                                                }
                                                            </p>
                                                        )}
                                                    </TableCell>

                                                    <TableCell className="text-sm whitespace-nowrap text-muted-foreground">
                                                        {connection.lastHealthCheckAt ??
                                                            'Hiç yapılmadı'}
                                                    </TableCell>

                                                    <TableCell>
                                                        <WebhookUrl
                                                            url={
                                                                connection.webhookUrl
                                                            }
                                                        />
                                                    </TableCell>

                                                    <TableCell>
                                                        <div className="flex justify-end gap-1">
                                                            <PermissionButton
                                                                check={
                                                                    canManage
                                                                }
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`${connection.name} bağlantısını test et`}
                                                                onClick={() =>
                                                                    test(
                                                                        connection,
                                                                    )
                                                                }
                                                            >
                                                                <RefreshCw />
                                                            </PermissionButton>
                                                            <PermissionButton
                                                                check={
                                                                    canManage
                                                                }
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`${connection.name} düzenle`}
                                                                onClick={() =>
                                                                    setSetup({
                                                                        connection,
                                                                    })
                                                                }
                                                            >
                                                                <Pencil />
                                                            </PermissionButton>
                                                            <PermissionButton
                                                                check={
                                                                    canManage
                                                                }
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`${connection.name} sil`}
                                                                onClick={() =>
                                                                    setPendingDelete(
                                                                        connection,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />
                                                            </PermissionButton>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}

                            {connections.length === 0 ? null : (
                                <p className="text-xs text-muted-foreground">
                                    Webhook adresi pazaryerine bu aşamada
                                    kaydedilmez; sipariş bildirimleri devreye
                                    alındığında bu adres kullanılacaktır.
                                </p>
                            )}
                        </section>
                    </div>

                    <aside className="flex flex-col gap-4">
                        <section className="flex flex-col gap-2 rounded-xl border p-5">
                            <h2 className="font-medium">Fiyatlandırma</h2>

                            {app.price === null ? (
                                <>
                                    <p className="text-2xl font-semibold">
                                        Planınıza dahil
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Bu uygulama için ek ücret alınmaz;
                                        kullanım hakkı aboneliğinizden gelir.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p className="text-2xl font-semibold">
                                        {app.price.monthly}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Yıllık ödemede {app.price.yearly}.
                                    </p>
                                </>
                            )}

                            {app.entitled ? null : (
                                <Button asChild variant="outline" className="mt-2">
                                    <Link href={license()}>
                                        Planınızı görüntüleyin
                                    </Link>
                                </Button>
                            )}
                        </section>

                        <section className="flex flex-col gap-2 rounded-xl border p-5 text-sm text-muted-foreground">
                            <h2 className="font-medium text-foreground">
                                Kurulum için gerekenler
                            </h2>
                            {app.fields.length === 0 ? (
                                <p>
                                    Bu uygulama yakında; kurulum bilgileri
                                    yayına alındığında burada listelenecek.
                                </p>
                            ) : (
                                <ul className="list-inside list-disc space-y-1">
                                    {app.fields.map((field) => (
                                        <li key={field.name}>{field.label}</li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    </aside>
                </div>
            </div>

            <ConnectionDrawer
                marketplace={{
                    value: app.code,
                    label: app.name,
                    capabilities: app.capabilities,
                    fields: app.fields,
                }}
                connection={setup?.connection ?? null}
                // Kilitli bir uygulamanin MEVCUT baglantisi duzenlenebilir;
                // yasak olan yenisini kurmaktir.
                canManage={
                    setup?.connection == null ? installCheck : canManage
                }
                open={setup !== null}
                onOpenChange={(next) => !next && setSetup(null)}
            />

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(next) => !next && setPendingDelete(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Bağlantı silinsin mi?</DialogTitle>
                        <DialogDescription>
                            {pendingDelete?.name} ve buna bağlı tüm eşlemeler,
                            listelemeler ve işlem kayıtları silinir. Kimlik
                            bilgileri de silinir, geri alınamaz.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setPendingDelete(null)}
                        >
                            Vazgeç
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                if (pendingDelete === null) {
                                    return;
                                }

                                router.delete(
                                    destroy.url({
                                        connection: pendingDelete.id,
                                    }),
                                    {
                                        preserveScroll: true,
                                        onError: toastError,
                                    },
                                );
                                setPendingDelete(null);
                            }}
                        >
                            Sil
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function WebhookUrl({ url }: { url: string }) {
    const [copied, copy] = useClipboard();

    return (
        <div className="flex max-w-72 items-center gap-1">
            <Input
                readOnly
                value={url}
                aria-label="Webhook adresi"
                className="h-8 font-mono text-xs"
                onFocus={(event) => event.currentTarget.select()}
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="Webhook adresini kopyala"
                onClick={() => copy(url)}
            >
                {copied === url ? <Check /> : <Copy />}
            </Button>
        </div>
    );
}

AppShow.layout = {
    breadcrumbs: [
        {
            title: 'Uygulama Mağazası',
            href: index(),
        },
    ],
};
