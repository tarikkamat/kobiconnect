import { Head } from '@inertiajs/react';
import { ExternalLink, TriangleAlert } from 'lucide-react';
import type { Quota } from '@/components/billing/quota-bar';
import { QuotaBar } from '@/components/billing/quota-bar';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { index } from '@/routes/license';

type LicenseSummary = {
    plan: string;
    planCode: string;
    price: string;
    billingPeriod: string;
    status: string;
    statusLabel: string;
    startsAt: string;
    endsAt: string | null;
    graceUntil: string | null;
    inGracePeriod: boolean;
    graceDaysLeft: number | null;
    readOnly: boolean;
    hasAccess: boolean;
};

type Props = {
    license: LicenseSummary | null;
    quotas: Quota[];
    contactEmail: string;
};

/**
 * Bu ekran lisans korumasi ALTINDA DEGILDIR (routes/tenant/settings.php):
 * suresi dolmus musteri de buraya girebilmeli. Dolayisiyla `license === null`
 * ve "erisim yok" durumlari normal, bos durum degil.
 */
export default function LicenseSettings({
    license,
    quotas,
    contactEmail,
}: Props) {
    const upgradeHref = `mailto:${contactEmail}?subject=${encodeURIComponent(
        license === null
            ? 'KobiConnect — plan talebi'
            : `KobiConnect — plan yükseltme (${license.planCode})`,
    )}`;

    return (
        <>
            <Head title="Lisans & Kullanım" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Lisans & Kullanım"
                    description="Planınız, abonelik durumunuz ve kota kullanımınız."
                />

                {license === null ? (
                    <div className="rounded-xl border border-dashed p-6 text-sm">
                        <p className="font-medium">
                            Hesabınıza tanımlı bir lisans yok.
                        </p>
                        <p className="mt-1 text-muted-foreground">
                            Panelin tamamı lisansa bağlıdır. Plan tanımlanması
                            için bizimle iletişime geçin.
                        </p>
                    </div>
                ) : (
                    <div className="rounded-xl border p-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="text-lg font-medium">
                                        {license.plan}
                                    </span>
                                    <Badge
                                        variant={
                                            license.hasAccess
                                                ? 'secondary'
                                                : 'destructive'
                                        }
                                    >
                                        {license.statusLabel}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {license.price} / ay
                                </p>
                            </div>

                            <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                                <dt className="text-muted-foreground">
                                    Başlangıç
                                </dt>
                                <dd className="tabular-nums">
                                    {license.startsAt}
                                </dd>
                                <dt className="text-muted-foreground">Bitiş</dt>
                                <dd className="tabular-nums">
                                    {license.endsAt ?? '—'}
                                </dd>
                            </dl>
                        </div>

                        {license.inGracePeriod && (
                            <div className="mt-4 flex items-start gap-2 rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 text-sm text-amber-900 dark:text-amber-200">
                                <TriangleAlert
                                    className="mt-0.5 size-4 shrink-0"
                                    aria-hidden
                                />
                                <div>
                                    <p className="font-medium">
                                        Ödeme bekleniyor — hesabınız salt-okunur
                                        modda.
                                    </p>
                                    <p>
                                        {license.graceDaysLeft !== null
                                            ? `Ödeme için ${license.graceDaysLeft} gününüz kaldı (${license.graceUntil}).`
                                            : `Ödeme süresi ${license.graceUntil} tarihinde doluyor.`}{' '}
                                        Bu süre boyunca verileriniz durur ve
                                        senkron duraklar; hiçbir şey silinmez.
                                    </p>
                                </div>
                            </div>
                        )}

                        {!license.hasAccess && (
                            <div className="mt-4 rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-sm">
                                <p className="font-medium">
                                    Aboneliğiniz aktif değil.
                                </p>
                                <p>
                                    Panelin geri kalanı kapalı, verileriniz
                                    duruyor. Ödemeniz tamamlandığında kaldığınız
                                    yerden devam edersiniz.
                                </p>
                            </div>
                        )}
                    </div>
                )}

                <Separator />

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title="Kota kullanımı"
                        description="Kotanın %80'inde uyarı, %100'ünde yeni kayıt engellenir."
                    />

                    <div className="grid gap-4">
                        {quotas.map((quota) => (
                            <QuotaBar key={quota.key} quota={quota} />
                        ))}
                    </div>
                </section>

                <div className="rounded-xl border p-4">
                    <p className="text-sm font-medium">
                        Plan yükseltme veya yenileme
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Ödeme işlemleri şu an panel üzerinden yapılmıyor.
                        Planınızı değiştirmek için bize yazın, aynı gün dönüş
                        yapıyoruz.
                    </p>
                    <Button asChild className="mt-3">
                        <a href={upgradeHref}>
                            Bizimle iletişime geçin
                            <ExternalLink />
                        </a>
                    </Button>
                </div>
            </div>
        </>
    );
}

LicenseSettings.layout = {
    breadcrumbs: [
        {
            title: 'Lisans & Kullanım',
            href: index(),
        },
    ],
};
