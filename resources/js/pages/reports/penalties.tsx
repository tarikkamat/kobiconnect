import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    Copy,
    Download,
    FileText,
    Loader2,
    Percent,
    Scale,
    ShieldAlert,
    Sparkles,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { MarketplaceAvatar } from '@/components/marketplace-avatar';
import {
    ReportHeader,
    type ConnectionItem,
} from '@/components/reports/report-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { desiAudit } from '@/routes/ai/logistics';
import { index as reportsRoute, penalties as penaltiesRoute } from '@/routes/reports';

type Kpis = {
    commissionTotal: string;
    shippingTotal: string;
    cargoPenaltyTotal: string;
    latePenaltyTotal: string;
    totalPenalties: string;
    totalDeductions: string;
};

type PenalizedOrder = {
    id: number;
    orderNumber: string;
    connectionName: string;
    marketplace: string;
    cargoProvider: string;
    deci: number | null;
    cargoPenalty: string;
    latePenalty: string;
    totalPenalty: string;
    rawTotalPenalty: number;
    reasons: string;
    placedAt: string;
};

type AuditDiscrepancy = {
    package_id?: number;
    order_id: string;
    tracking_number?: string;
    cargo_provider?: string;
    expected_desi: number;
    billed_desi: number;
    desi_overcharge?: number;
    financial_loss: number;
};

type AuditResponseData = {
    total_detected_loss: number;
    currency: string;
    discrepancies: AuditDiscrepancy[];
    dispute_summary: string;
    formal_dispute_letter: string;
    scanned_packages_count: number;
    discrepancy_count: number;
};

type Props = {
    range: { from: string; to: string };
    filters: { connection: number | null };
    connections: ConnectionItem[];
    kpis: Kpis;
    penalizedOrders: PenalizedOrder[];
};

export default function ReportsPenalties({
    range,
    filters,
    connections,
    kpis,
    penalizedOrders,
}: Props) {
    const [auditModalOpen, setAuditModalOpen] = useState(false);
    const [isAuditing, setIsAuditing] = useState(false);
    const [auditData, setAuditData] = useState<AuditResponseData | null>(null);
    const [copied, setCopied] = useState(false);

    async function handleRunAiAudit() {
        setAuditModalOpen(true);
        setIsAuditing(true);

        try {
            const searchParams = new URLSearchParams({
                from: range.from,
                to: range.to,
            });
            if (filters.connection) {
                searchParams.set('connection', String(filters.connection));
            }

            const res = await fetch(`${desiAudit.url()}?${searchParams.toString()}`, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!res.ok) {
                throw new Error('Denetim gerçekleştirilemedi.');
            }

            const data = await res.json();
            if (data.success && data.audit) {
                setAuditData(data.audit);
                toast.success('AI Desi & Tahkim denetimi tamamlandı.');
            } else {
                throw new Error('Veri çözümlenemedi.');
            }
        } catch (error) {
            toast.error('AI Kargo denetimi sırasında bir hata oluştu.');
        } finally {
            setIsAuditing(false);
        }
    }

    async function handleCopyLetter() {
        if (!auditData?.formal_dispute_letter) return;
        try {
            await navigator.clipboard.writeText(auditData.formal_dispute_letter);
            setCopied(true);
            toast.success('İtiraz dilekçesi panoya kopyalandı.');
            setTimeout(() => setCopied(false), 2500);
        } catch {
            toast.error('Dilekçe metni kopyalanamadı.');
        }
    }

    function handleDownloadLetter() {
        if (!auditData?.formal_dispute_letter) return;
        const blob = new Blob([auditData.formal_dispute_letter], {
            type: 'text/plain;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `kargo-itiraz-dilekcesi-${range.from}-${range.to}.txt`;
        link.click();
        URL.revokeObjectURL(url);
        toast.success('Dilekçe dosyası indirildi.');
    }

    return (
        <>
            <Head title="Kargo ve Cezalar Raporu" />

            <div className="flex flex-col gap-6 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <ReportHeader
                        title="Kargo Kesintileri ve Cezalar"
                        description="Pazaryeri tarafından yansıtılan kargo barem/desi aşım farkları ve operasyonel gecikme cezaları."
                        activeTab="penalties"
                        range={range}
                        filters={filters}
                        connections={connections}
                    />
                </div>

                {/* Deductions & Penalties Breakdown Bar */}
                <Card className="gap-0 border-border bg-card py-4">
                    <CardContent className="px-4 py-0 flex flex-col gap-3">
                        <div className="flex items-center justify-between">
                            <h3 className="text-sm font-semibold flex items-center gap-2">
                                <ShieldAlert className="size-4 text-amber-500" />
                                Kesinti ve Ceza Kalemleri Özeti
                            </h3>
                            <span className="text-xs text-muted-foreground">
                                Toplam Kesinti: <strong className="font-mono text-foreground">{kpis.totalDeductions}</strong>
                            </span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div className="rounded-lg border border-border bg-muted/30 p-3 flex flex-col justify-between">
                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>Pazaryeri Komisyonu</span>
                                    <Percent className="size-3.5 text-rose-500" />
                                </div>
                                <div className="mt-2 text-lg font-bold font-mono text-rose-500 tabular-nums">
                                    {kpis.commissionTotal}
                                </div>
                                <span className="text-[11px] text-muted-foreground mt-0.5">Kategori ve barem bazlı komisyon</span>
                            </div>

                            <div className="rounded-lg border border-border bg-muted/30 p-3 flex flex-col justify-between">
                                <div className="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>Standart Kargo Gideri</span>
                                    <Truck className="size-3.5 text-sky-500" />
                                </div>
                                <div className="mt-2 text-lg font-bold font-mono text-sky-500 tabular-nums">
                                    {kpis.shippingTotal}
                                </div>
                                <span className="text-[11px] text-muted-foreground mt-0.5">Taşıyıcı anlaşmalı gönderim bedeli</span>
                            </div>

                            <div className="rounded-lg border border-amber-500/5 border-amber-500/20 p-3 flex flex-col justify-between">
                                <div className="flex items-center justify-between text-xs text-amber-600 dark:text-amber-400">
                                    <span className="font-medium">Kargo Desi Aşım Cezası</span>
                                    <Scale className="size-3.5 text-amber-500" />
                                </div>
                                <div className="mt-2 text-lg font-bold font-mono text-amber-500 tabular-nums">
                                    {kpis.cargoPenaltyTotal}
                                </div>
                                <span className="text-[11px] text-muted-foreground mt-0.5">Ölçüm & tartım barem farkı</span>
                            </div>

                            <div className="rounded-lg border border-rose-500/5 border-rose-500/20 p-3 flex flex-col justify-between">
                                <div className="flex items-center justify-between text-xs text-rose-600 dark:text-rose-400">
                                    <span className="font-medium">Tedarik / Gecikme Cezası</span>
                                    <AlertTriangle className="size-3.5 text-rose-500" />
                                </div>
                                <div className="mt-2 text-lg font-bold font-mono text-rose-500 tabular-nums">
                                    {kpis.latePenaltyTotal}
                                </div>
                                <span className="text-[11px] text-muted-foreground mt-0.5">Gecikmeli kargo ve iptal bedelleri</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Penalized Orders Table */}
                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border px-4 py-3">
                        <div className="space-y-1">
                            <h3 className="text-sm font-semibold flex items-center gap-2">
                                <AlertTriangle className="size-4 text-amber-500" />
                                Ceza Kesintisi Uygulanan Siparişler
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Desi/baremi uyuşmazlığı veya tedarik gecikmesi sebebiyle ek ücret kesilen sipariş dökümü.
                            </p>
                        </div>
                        <div className="flex items-center gap-2 self-start sm:self-auto">
                            <Button
                                size="sm"
                                onClick={handleRunAiAudit}
                                disabled={isAuditing}
                                className="gap-1.5 h-8 text-xs font-medium bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white shadow-xs"
                            >
                                {isAuditing ? (
                                    <Loader2 className="size-3.5 animate-spin" />
                                ) : (
                                    <Sparkles className="size-3.5 text-amber-300" />
                                )}
                                <span>{isAuditing ? 'AI Denetliyor...' : 'AI Desi & Tahkim Denetimi'}</span>
                            </Button>
                            <Badge variant="outline" className="font-mono text-xs tabular-nums h-8 px-2.5">
                                {penalizedOrders.length} kayıt
                            </Badge>
                        </div>
                    </div>

                    <Table>
                        <TableHeader>
                            <TableRow className="border-b border-border hover:bg-transparent">
                                <TableHead className="w-[160px]">Sipariş No</TableHead>
                                <TableHead>Kanal</TableHead>
                                <TableHead>Kargo Firması & Desi</TableHead>
                                <TableHead>Kesinti Sebebi</TableHead>
                                <TableHead className="text-right">Desi Cezası</TableHead>
                                <TableHead className="text-right">Gecikme Cezası</TableHead>
                                <TableHead className="text-right">Toplam Ceza</TableHead>
                                <TableHead className="text-right">Tarih</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {penalizedOrders.length > 0 ? (
                                penalizedOrders.map((order) => (
                                    <TableRow key={order.id}>
                                        <TableCell className="font-mono font-medium text-xs">
                                            {order.orderNumber}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <MarketplaceAvatar
                                                    marketplace={order.marketplace}
                                                    className="size-4"
                                                />
                                                <span className="text-xs">{order.connectionName}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <div className="flex items-center gap-1.5">
                                                <Truck className="size-3 text-muted-foreground" />
                                                <span>{order.cargoProvider}</span>
                                                {order.deci !== null && (
                                                    <Badge variant="secondary" className="font-mono text-[10px] px-1 py-0">
                                                        {order.deci} desi
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground">
                                            {order.reasons}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-right text-amber-500 tabular-nums">
                                            {order.cargoPenalty}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-right text-rose-500 tabular-nums">
                                            {order.latePenalty}
                                        </TableCell>
                                        <TableCell className="font-mono font-semibold text-xs text-right text-rose-600 dark:text-rose-400 tabular-nums">
                                            {order.totalPenalty}
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground text-right font-mono tabular-nums">
                                            {order.placedAt}
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="py-12 text-center text-xs text-muted-foreground"
                                    >
                                        Seçilen tarih aralığında ceza veya kesinti uygulanan sipariş bulunmuyor.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* AI Desi & Dispute Audit Dialog */}
            <Dialog open={auditModalOpen} onOpenChange={setAuditModalOpen}>
                <DialogContent className="sm:max-w-3xl max-h-[90vh] flex flex-col p-0 overflow-hidden">
                    <DialogHeader className="p-5 pb-3 border-b border-border bg-muted/20">
                        <div className="flex items-center gap-1.5 text-indigo-600 dark:text-indigo-400 font-semibold text-xs uppercase tracking-wider">
                            <Sparkles className="size-3.5" />
                            <span>AI Lojistik & Tahkim Denetçisi</span>
                        </div>
                        <DialogTitle className="text-base font-semibold">
                            Kargo Desi Aşımı ve Resmi İtiraz Raporu
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            Ürün ebatları/ağırlığı ile taşıyıcı tarafından faturaya yansıtılan desi baremleri denetlenir ve itiraz dilekçesi üretilir.
                        </DialogDescription>
                    </DialogHeader>

                    {isAuditing ? (
                        <div className="p-12 flex flex-col items-center justify-center gap-3 text-center">
                            <Loader2 className="size-8 animate-spin text-indigo-600" />
                            <p className="text-sm font-medium">Kargo gönderileri taranıyor ve desi uyuşmazlıkları hesaplanıyor...</p>
                            <p className="text-xs text-muted-foreground">Ürün ebatları ve taşıyıcı desi ölçümleri karşılaştırılıyor.</p>
                        </div>
                    ) : auditData ? (
                        <div className="flex-1 overflow-y-auto p-5 flex flex-col gap-4">
                            {/* Summary Metrics */}
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 flex flex-col">
                                    <span className="text-xs text-muted-foreground">Toplam Haksız Kesinti</span>
                                    <span className="text-lg font-bold font-mono text-amber-600 dark:text-amber-400 mt-0.5 tabular-nums">
                                        {auditData.total_detected_loss.toLocaleString('tr-TR', { minimumFractionDigits: 2 })} {auditData.currency}
                                    </span>
                                    <span className="text-[10px] text-muted-foreground mt-0.5">Tazmin talep edilecek tutar</span>
                                </div>
                                <div className="rounded-lg border border-border bg-muted/30 p-3 flex flex-col">
                                    <span className="text-xs text-muted-foreground">Taranan Paket</span>
                                    <span className="text-lg font-bold font-mono text-foreground mt-0.5 tabular-nums">
                                        {auditData.scanned_packages_count} Paket
                                    </span>
                                    <span className="text-[10px] text-muted-foreground mt-0.5">Seçilen dönemdeki gönderiler</span>
                                </div>
                                <div className="rounded-lg border border-rose-500/30 bg-rose-500/5 p-3 flex flex-col">
                                    <span className="text-xs text-muted-foreground">Desi Uyuşmazlığı</span>
                                    <span className="text-lg font-bold font-mono text-rose-600 dark:text-rose-400 mt-0.5 tabular-nums">
                                        {auditData.discrepancy_count} Gönderi
                                    </span>
                                    <span className="text-[10px] text-muted-foreground mt-0.5">Fahiş ölçüm yapılan paket</span>
                                </div>
                            </div>

                            {/* Discrepancies Table */}
                            {auditData.discrepancies.length > 0 && (
                                <div className="flex flex-col gap-2">
                                    <h4 className="text-xs font-semibold text-foreground uppercase tracking-wide flex items-center gap-1.5">
                                        <Scale className="size-3.5 text-indigo-500" />
                                        Tespit Edilen Desi Uyuşmazlıkları
                                    </h4>
                                    <div className="rounded-lg border border-border overflow-hidden max-h-48 overflow-y-auto">
                                        <Table>
                                            <TableHeader className="bg-muted/40">
                                                <TableRow className="border-b border-border">
                                                    <TableHead className="text-xs h-7">Sipariş / Takip No</TableHead>
                                                    <TableHead className="text-xs h-7">Kargo Firması</TableHead>
                                                    <TableHead className="text-xs h-7 text-center">Beklenen</TableHead>
                                                    <TableHead className="text-xs h-7 text-center">Faturalanan</TableHead>
                                                    <TableHead className="text-xs h-7 text-center">Fark</TableHead>
                                                    <TableHead className="text-xs h-7 text-right">Maddi Kayıp</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {auditData.discrepancies.map((item, idx) => (
                                                    <TableRow key={idx} className="border-b border-border text-xs">
                                                        <TableCell className="font-mono font-medium">
                                                            {item.order_id || item.tracking_number}
                                                        </TableCell>
                                                        <TableCell>{item.cargo_provider || 'Kargo'}</TableCell>
                                                        <TableCell className="text-center font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                                                            {item.expected_desi} desi
                                                        </TableCell>
                                                        <TableCell className="text-center font-mono text-rose-600 dark:text-rose-400 font-semibold">
                                                            {item.billed_desi} desi
                                                        </TableCell>
                                                        <TableCell className="text-center font-mono font-medium text-amber-500">
                                                            +{item.desi_overcharge ? item.desi_overcharge : (item.billed_desi - item.expected_desi).toFixed(1)} desi
                                                        </TableCell>
                                                        <TableCell className="text-right font-mono font-bold text-rose-600 dark:text-rose-400 tabular-nums">
                                                            {item.financial_loss.toLocaleString('tr-TR', { minimumFractionDigits: 2 })} TL
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                            )}

                            {/* Formal Letter */}
                            <div className="flex flex-col gap-2">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-xs font-semibold text-foreground uppercase tracking-wide flex items-center gap-1.5">
                                        <FileText className="size-3.5 text-indigo-500" />
                                        Resmi İtiraz & Tahkim Dilekçesi
                                    </h4>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={handleCopyLetter}
                                        className="h-7 text-xs gap-1 px-2.5"
                                    >
                                        {copied ? <Check className="size-3 text-emerald-500" /> : <Copy className="size-3" />}
                                        <span>{copied ? 'Kopyalandı' : 'Dilekçeyi Kopyala'}</span>
                                    </Button>
                                </div>
                                <div className="rounded-lg border border-border bg-muted/30 p-3.5 font-mono text-xs text-foreground whitespace-pre-wrap leading-relaxed max-h-52 overflow-y-auto">
                                    {auditData.formal_dispute_letter}
                                </div>
                            </div>
                        </div>
                    ) : null}

                    <DialogFooter className="p-3.5 border-t border-border bg-muted/20 flex sm:justify-between items-center gap-2">
                        <span className="text-[11px] text-muted-foreground hidden sm:inline">
                            Dilekçe metnini kargo firması cari itiraz veya pazaryeri destek kanalına iletebilirsiniz.
                        </span>
                        <div className="flex items-center gap-2">
                            {auditData && (
                                <Button size="sm" variant="outline" onClick={handleDownloadLetter} className="gap-1.5 text-xs h-8">
                                    <Download className="size-3.5" />
                                    <span>İndir (.txt)</span>
                                </Button>
                            )}
                            <Button size="sm" variant="secondary" onClick={() => setAuditModalOpen(false)} className="text-xs h-8">
                                Kapat
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ReportsPenalties.layout = {
    breadcrumbs: [
        {
            title: 'Raporlar',
            href: reportsRoute(),
        },
        {
            title: 'Kargo & Cezalar',
            href: penaltiesRoute(),
        },
    ],
};
