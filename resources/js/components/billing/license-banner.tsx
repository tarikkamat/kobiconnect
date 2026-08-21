import { Link, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { index as licenseScreen } from '@/routes/license';

/**
 * Grace period uyari cubugu — FRONTEND-PLAN §5.
 *
 * Kalici olarak durur: salt-okunur modda yazma aksiyonlari zaten
 * `PermissionButton` ile kilitli, ama kilidin SEBEBI yalnizca tooltip'te
 * kaliyordu. Kullanici verisinin durdugunu, kaybolmadigini gormeli.
 *
 * Layout'a eklenmesi gereken yer raporda; bu bilesen kendi kendine karar verir
 * ve gosterilecek bir sey yoksa hicbir sey cizmez.
 */
type LicenseProp = {
    status: string;
    endsAt: string | null;
    readOnly: boolean;
    /**
     * Paylasilan prop'a HENUZ eklenmedi (HandleInertiaRequests bugun ham enum
     * degerini paylasiyor, `grace` turetilmis bir durum). Alan geldiginde
     * banner kalan gunu kendiliginden gosterir — raporda tam kod var.
     */
    graceDaysLeft?: number | null;
};

export function LicenseBanner() {
    // Genisletilmis (yalnizca opsiyonel alan eklenmis) bir gorunum; global.d.ts
    // bu is kumesinin disinda.
    const license = usePage().props.license as LicenseProp | null;

    if (license === null) {
        return null;
    }

    // `status === 'grace'` plandaki turetilmis durum, `readOnly` bugun ayni
    // sarti tasiyan alan. Ikisi de kabul ediliyor ki paylasilan prop
    // duzeltildiginde burasi degismesin.
    if (!license.readOnly && license.status !== 'grace') {
        return null;
    }

    const days = license.graceDaysLeft;

    return (
        <div
            role="status"
            className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-amber-500/40 bg-amber-500/10 px-4 py-2 text-sm text-amber-900 dark:text-amber-200"
        >
            <TriangleAlert className="size-4 shrink-0" aria-hidden />

            <span className="font-medium">
                Ödeme bekleniyor: hesabınız salt-okunur modda.
            </span>

            <span className="text-amber-900/80 dark:text-amber-200/80">
                Verileriniz duruyor ve senkron durakladı; değişiklik yapma
                yetkiniz ödemeniz tamamlandığında geri açılır.
                {typeof days === 'number' &&
                    ` Ödeme için ${days} gününüz kaldı.`}
            </span>

            <Link
                href={licenseScreen()}
                className="ml-auto font-medium underline underline-offset-4"
            >
                Lisans & Kullanım
            </Link>
        </div>
    );
}
