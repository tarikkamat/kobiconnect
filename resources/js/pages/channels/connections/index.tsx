import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    Clock,
    Layers,
    Plus,
    RefreshCw,
    Search,
    Settings2,
    Store,
    Trash2,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import ConnectionController from '@/actions/App/Http/Controllers/Channels/ConnectionController';
import { PermissionButton } from '@/components/catalog/permission-button';
import { toastError } from '@/components/catalog/toast-error';
import { AppIcon } from '@/components/channels/app-card';
import { ConnectionDrawer } from '@/components/channels/connection-drawer';
import type {
    ConnectionRow,
    Marketplace,
} from '@/components/channels/connection-drawer';
import { ConnectionStatusBadge } from '@/components/channels/connection-status-badge';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermission } from '@/hooks/use-permission';
import { index as appsRoute } from '@/routes/apps';
import { index as connectionsRoute } from '@/routes/connections';

type ConnectionItem = ConnectionRow & {
    marketplaceLabel: string;
    logo?: string;
    logoScale?: number;
    logoDarkInvert?: boolean;
    sellerId?: string | null;
    createdAt?: string;
};

type Props = {
    connections: ConnectionItem[];
    marketplaces: Marketplace[];
    statuses: { value: string; label: string }[];
    filters: {
        marketplace: string | null;
        status: string | null;
        search: string | null;
    };
};

const ALL = 'all';

export default function ConnectionsIndex({
    connections,
    marketplaces,
    statuses,
    filters,
}: Props) {
    const canManage = usePermission()('channels.manage');
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedMarketplace, setSelectedMarketplace] = useState<string>(
        filters.marketplace ?? ALL,
    );
    const [selectedStatus, setSelectedStatus] = useState<string>(
        filters.status ?? ALL,
    );

    const [testingId, setTestingId] = useState<number | null>(null);
    const [pendingDelete, setPendingDelete] = useState<ConnectionItem | null>(
        null,
    );

    // Çekmece state'i (Yeni ekleme veya mevcut düzenleme)
    const [activeDrawer, setActiveDrawer] = useState<{
        marketplace: Marketplace | null;
        connection: ConnectionRow | null;
    } | null>(null);

    // İstatistikler
    const stats = useMemo(() => {
        const active = connections.filter((c) => c.status === 'active').length;
        const error = connections.filter((c) => c.status === 'error').length;
        const paused = connections.filter((c) => c.status === 'paused').length;

        return {
            total: connections.length,
            active,
            error,
            paused,
        };
    }, [connections]);

    // İstemci tarafı anlık arama / filtreleme
    const filteredConnections = useMemo(() => {
        return connections.filter((conn) => {
            if (
                selectedMarketplace !== ALL &&
                conn.marketplace !== selectedMarketplace
            ) {
                return false;
            }

            if (selectedStatus !== ALL && conn.status !== selectedStatus) {
                return false;
            }

            if (search.trim() !== '') {
                const query = search.toLowerCase().trim();
                const matchesName = conn.name.toLowerCase().includes(query);
                const matchesMarketplace = conn.marketplaceLabel
                    .toLowerCase()
                    .includes(query);
                const matchesSellerId = conn.sellerId
                    ? conn.sellerId.toLowerCase().includes(query)
                    : false;

                if (!matchesName && !matchesMarketplace && !matchesSellerId) {
                    return false;
                }
            }

            return true;
        });
    }, [connections, selectedMarketplace, selectedStatus, search]);

    const isFiltered =
        search.trim() !== '' ||
        selectedMarketplace !== ALL ||
        selectedStatus !== ALL;

    const resetFilters = () => {
        setSearch('');
        setSelectedMarketplace(ALL);
        setSelectedStatus(ALL);
    };

    const handleHealthCheck = (connection: ConnectionItem) => {
        setTestingId(connection.id);
        router.post(
            ConnectionController.health.url({ connection: connection.id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTestingId(null),
                onError: toastError,
            },
        );
    };

    const handleDelete = () => {
        if (pendingDelete === null) {
            return;
        }

        router.delete(
            ConnectionController.destroy.url({ connection: pendingDelete.id }),
            {
                preserveScroll: true,
                onSuccess: () => setPendingDelete(null),
                onError: toastError,
            },
        );
    };

    const handleEdit = (conn: ConnectionItem) => {
        const targetMarketplace = marketplaces.find(
            (m) => m.value === conn.marketplace,
        ) ?? {
            value: conn.marketplace,
            label: conn.marketplaceLabel,
            // Varsayilanlar sunucudaki AppCatalog::present() ile ayni.
            logo: conn.logo ?? `/apps/${conn.marketplace}.svg`,
            logoScale: conn.logoScale ?? 1,
            logoDarkInvert: conn.logoDarkInvert ?? false,
            capabilities: [],
            fields: [],
        };

        setActiveDrawer({
            marketplace: targetMarketplace,
            connection: conn,
        });
    };

    const handleCreateForMarketplace = (marketplace: Marketplace) => {
        setActiveDrawer({
            marketplace,
            connection: null,
        });
    };

    return (
        <>
            <Head title="Bağlantılarım" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                {/* Başlık ve Aksiyon */}
                <div className="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                    <Heading
                        title="Bağlantılarım"
                        description="Pazaryerleri ve e-ticaret altyapılarınıza bağlı mağaza hesaplarınızı yönetin, API sağlık durumlarını test edin."
                    />

                    <div className="flex flex-wrap items-center gap-2">
                        {/* Hızlı İstatistikler */}
                        <div className="flex items-center gap-2">
                            <div className="flex items-center gap-1.5 rounded-lg border border-border bg-secondary/60 px-3 py-1.5 text-xs">
                                <span className="size-2 rounded-full bg-primary" />
                                <span className="text-foreground">
                                    <span className="font-semibold tabular-nums">
                                        {stats.active}
                                    </span>{' '}
                                    Aktif
                                </span>
                            </div>

                            {stats.error > 0 && (
                                <div className="flex items-center gap-1.5 rounded-lg border border-destructive/40 bg-destructive/10 px-3 py-1.5 text-xs text-destructive">
                                    <span className="size-2 rounded-full bg-destructive" />
                                    <span>
                                        <span className="font-semibold tabular-nums">
                                            {stats.error}
                                        </span>{' '}
                                        Hata
                                    </span>
                                </div>
                            )}

                            <div className="flex items-center gap-1.5 rounded-lg border border-border bg-secondary/40 px-3 py-1.5 text-xs text-muted-foreground">
                                <span>
                                    <span className="font-semibold tabular-nums">
                                        {stats.total}
                                    </span>{' '}
                                    Toplam
                                </span>
                            </div>
                        </div>

                        {/* Yeni Bağlantı Ekle Dropdown / Buton */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <PermissionButton
                                    check={canManage}
                                    variant="default"
                                    size="sm"
                                    className="gap-1.5 text-xs font-medium"
                                >
                                    <Plus className="size-3.5" />
                                    Yeni Bağlantı Ekle
                                </PermissionButton>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                {marketplaces.map((marketplace) => (
                                    <DropdownMenuItem
                                        key={marketplace.value}
                                        onClick={() =>
                                            handleCreateForMarketplace(
                                                marketplace,
                                            )
                                        }
                                        className="cursor-pointer gap-2 py-2 text-xs"
                                    >
                                        <div className="flex size-5 shrink-0 items-center justify-center rounded border border-border bg-secondary p-0.5">
                                            {marketplace.logo ? (
                                                <img
                                                    src={marketplace.logo}
                                                    alt={marketplace.label}
                                                    className="max-h-full max-w-full object-contain"
                                                />
                                            ) : (
                                                <Store className="size-3" />
                                            )}
                                        </div>
                                        <span>{marketplace.label}</span>
                                    </DropdownMenuItem>
                                ))}
                                <div className="mt-1 border-t border-border pt-1">
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={appsRoute()}
                                            className="cursor-pointer gap-2 py-2 text-xs text-muted-foreground hover:text-foreground"
                                        >
                                            <Layers className="size-3.5" />
                                            <span>Uygulama Mağazasına Git</span>
                                        </Link>
                                    </DropdownMenuItem>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* Filtre ve Arama Çubuğu */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative w-full max-w-xs sm:w-64">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Mağaza adı veya satıcı ID ara..."
                                className="h-9 pr-8 pl-9 text-xs"
                            />
                            {search.trim() !== '' && (
                                <button
                                    type="button"
                                    onClick={() => setSearch('')}
                                    className="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                >
                                    <X className="size-3.5" />
                                    <span className="sr-only">
                                        Aramayı temizle
                                    </span>
                                </button>
                            )}
                        </div>

                        <Select
                            value={selectedMarketplace}
                            onValueChange={setSelectedMarketplace}
                        >
                            <SelectTrigger className="h-9 w-44 text-xs">
                                <SelectValue placeholder="Tüm Pazaryerleri" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Tüm Pazaryerleri
                                </SelectItem>
                                {marketplaces.map((m) => (
                                    <SelectItem key={m.value} value={m.value}>
                                        {m.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={selectedStatus}
                            onValueChange={setSelectedStatus}
                        >
                            <SelectTrigger className="h-9 w-36 text-xs">
                                <SelectValue placeholder="Tüm Durumlar" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    Tüm Durumlar
                                </SelectItem>
                                {statuses.map((s) => (
                                    <SelectItem key={s.value} value={s.value}>
                                        {s.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        {isFiltered && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={resetFilters}
                                className="h-9 text-xs text-muted-foreground hover:text-foreground"
                            >
                                Filtreleri Sıfırla
                            </Button>
                        )}
                    </div>

                    <div className="self-end text-xs text-muted-foreground sm:self-auto">
                        <strong className="font-mono font-semibold text-foreground tabular-nums">
                            {filteredConnections.length}
                        </strong>{' '}
                        bağlantı listeleniyor
                    </div>
                </div>

                {/* DataTable */}
                {filteredConnections.length === 0 ? (
                    <EmptyState
                        icon={Store}
                        title={
                            isFiltered
                                ? 'Filtreye uygun mağaza bağlantısı bulunamadı'
                                : 'Henüz tanımlanmış bir mağaza bağlantınız yok'
                        }
                        description={
                            isFiltered
                                ? 'Farklı bir arama terimi deneyebilir veya filtreleri sıfırlayabilirsiniz.'
                                : 'Trendyol, Hepsiburada veya diğer platformlarınızı bağlayarak entegrasyonu başlatın.'
                        }
                        action={
                            isFiltered ? (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={resetFilters}
                                >
                                    Filtreleri Temizle
                                </Button>
                            ) : (
                                <Button asChild size="sm" className="gap-1.5">
                                    <Link href={appsRoute()}>
                                        <Plus className="size-3.5" />
                                        Uygulama Mağazasına Git
                                    </Link>
                                </Button>
                            )
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="w-[300px]">
                                        Mağaza / Bağlantı Adı
                                    </TableHead>
                                    <TableHead className="w-[160px]">
                                        Pazaryeri
                                    </TableHead>
                                    <TableHead className="w-[160px]">
                                        Satıcı ID
                                    </TableHead>
                                    <TableHead className="w-[130px]">
                                        Durum
                                    </TableHead>
                                    <TableHead className="w-[180px]">
                                        Son API Testi
                                    </TableHead>
                                    <TableHead className="w-[160px] text-right">
                                        İşlemler
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredConnections.map((conn) => (
                                    <TableRow
                                        key={conn.id}
                                        className="transition-colors hover:bg-secondary/30"
                                    >
                                        {/* 1. Kolon: Mağaza Adı & Logo */}
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-border bg-secondary/80 p-2">
                                                    {conn.logo ? (
                                                        <AppIcon
                                                            app={{
                                                                name: conn.marketplaceLabel,
                                                                logo: conn.logo,
                                                                logoScale:
                                                                    conn.logoScale,
                                                                logoDarkInvert:
                                                                    conn.logoDarkInvert,
                                                            }}
                                                            className="size-full bg-transparent"
                                                            imageClassName="max-h-6"
                                                        />
                                                    ) : (
                                                        <Store className="size-4 text-muted-foreground" />
                                                    )}
                                                </div>
                                                <div className="min-w-0 space-y-0.5">
                                                    <p className="truncate font-semibold text-foreground">
                                                        {conn.name}
                                                    </p>
                                                    {conn.lastHealthError && (
                                                        <div className="flex max-w-xs items-center gap-1 truncate text-[11px] text-destructive">
                                                            <TriangleAlert className="size-3 shrink-0" />
                                                            <span className="truncate">
                                                                {
                                                                    conn.lastHealthError
                                                                }
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>

                                        {/* 2. Kolon: Pazaryeri */}
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className="bg-secondary/50 text-xs font-medium"
                                            >
                                                {conn.marketplaceLabel}
                                            </Badge>
                                        </TableCell>

                                        {/* 3. Kolon: Satıcı ID */}
                                        <TableCell className="font-mono text-xs text-muted-foreground tabular-nums">
                                            {conn.sellerId ? (
                                                <span className="rounded bg-secondary px-2 py-1 text-foreground">
                                                    {conn.sellerId}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground/50">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>

                                        {/* 4. Kolon: Durum */}
                                        <TableCell>
                                            <ConnectionStatusBadge
                                                status={conn.status}
                                                label={conn.statusLabel}
                                            />
                                        </TableCell>

                                        {/* 5. Kolon: Son Test */}
                                        <TableCell>
                                            <div className="flex items-center gap-1.5 font-mono text-xs text-muted-foreground">
                                                <Clock className="size-3.5 text-muted-foreground/60" />
                                                <span>
                                                    {conn.lastHealthCheckAt ??
                                                        'Henüz test edilmedi'}
                                                </span>
                                            </div>
                                        </TableCell>

                                        {/* 6. Kolon: İşlemler */}
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-1.5">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8 gap-1 bg-background/50 text-xs hover:bg-background"
                                                    title="Canlı API bağlantısını test et"
                                                    disabled={
                                                        testingId === conn.id
                                                    }
                                                    onClick={() =>
                                                        handleHealthCheck(conn)
                                                    }
                                                >
                                                    {testingId === conn.id ? (
                                                        <RefreshCw className="size-3.5 animate-spin" />
                                                    ) : (
                                                        <Activity className="size-3.5 text-primary" />
                                                    )}
                                                    <span className="hidden sm:inline">
                                                        Test Et
                                                    </span>
                                                </Button>

                                                <PermissionButton
                                                    check={canManage}
                                                    variant="outline"
                                                    size="sm"
                                                    className="h-8 gap-1 bg-background/50 text-xs hover:bg-background"
                                                    onClick={() =>
                                                        handleEdit(conn)
                                                    }
                                                >
                                                    <Settings2 className="size-3.5" />
                                                    <span>Düzenle</span>
                                                </PermissionButton>

                                                <PermissionButton
                                                    check={canManage}
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                    title="Bağlantıyı sil"
                                                    onClick={() =>
                                                        setPendingDelete(conn)
                                                    }
                                                >
                                                    <Trash2 className="size-3.5" />
                                                </PermissionButton>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            {/* Bağlantı Kurulum / Düzenleme Çekmecesi */}
            <ConnectionDrawer
                marketplace={activeDrawer?.marketplace ?? null}
                connection={activeDrawer?.connection ?? null}
                canManage={canManage}
                open={activeDrawer !== null}
                onOpenChange={(next) => !next && setActiveDrawer(null)}
            />

            {/* Silme Onay Penceresi */}
            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(next) => !next && setPendingDelete(null)}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Mağaza Bağlantısı Silinsin mi?
                        </DialogTitle>
                        <DialogDescription className="mt-1 text-xs leading-relaxed">
                            <strong>{pendingDelete?.name}</strong> bağlantısı ve
                            kayıtlı API anahtarları silinecektir. Bu işlem geri
                            alınamaz.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            variant="outline"
                            size="sm"
                            className="text-xs"
                            onClick={() => setPendingDelete(null)}
                        >
                            Vazgeç
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            className="text-xs"
                            onClick={handleDelete}
                        >
                            Bağlantıyı Sil
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ConnectionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Bağlantılarım',
            href: connectionsRoute(),
        },
    ],
};
