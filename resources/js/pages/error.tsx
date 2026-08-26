import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Home, RefreshCw } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

interface ErrorPageProps {
    status: number;
    message?: string;
}

const ERROR_DETAILS: Record<
    number,
    {
        title: string;
        description: string;
        isRetryable?: boolean;
    }
> = {
    401: {
        title: 'Yetkisiz Erişim',
        description: 'Bu sayfayı görüntülemek için lütfen önce giriş yapın.',
    },
    403: {
        title: 'Erişim Engellendi',
        description:
            'Bu sayfayı veya kaynağı görüntülemek için gerekli izne sahip değilsiniz.',
    },
    404: {
        title: 'Sayfa Bulunamadı',
        description:
            'Aradığınız sayfa silinmiş, adı değiştirilmiş veya geçici olarak kullanım dışı kalmış olabilir.',
    },
    419: {
        title: 'Oturum Süresi Doldu',
        description:
            'Güvenliğiniz nedeniyle oturumunuzun süresi doldu. Lütfen sayfayı yenileyip tekrar deneyin.',
        isRetryable: true,
    },
    429: {
        title: 'İstek Limiti Aşıldı',
        description:
            'Kısa süre içerisinde çok fazla işlem yaptınız. Lütfen biraz bekleyip tekrar deneyin.',
        isRetryable: true,
    },
    500: {
        title: 'Sunucu Hatası',
        description:
            'Beklenmeyen bir hata oluştu. Mühendislerimiz bu durumdan haberdar edildi ve üzerinde çalışıyor.',
        isRetryable: true,
    },
    503: {
        title: 'Bakım Modu',
        description:
            'Sistemimiz şu anda planlı bir bakım veya güncelleme çalışması nedeniyle geçici olarak hizmet verememektedir.',
        isRetryable: true,
    },
};

export default function ErrorPage({ status = 404, message }: ErrorPageProps) {
    const { props } = usePage<{
        tenant?: { id: string; host: string } | null;
    }>();

    const info = ERROR_DETAILS[status] ?? {
        title: 'Bir Hata Oluştu',
        description:
            message ||
            'İsteğiniz işlenirken beklenmeyen bir hata meydana geldi. Lütfen tekrar deneyin.',
        isRetryable: status >= 500,
    };

    const targetHome = props.tenant?.id
        ? `/${props.tenant.id}/dashboard`
        : home.url();

    const handleBack = () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = targetHome;
        }
    };

    const handleReload = () => {
        window.location.reload();
    };

    return (
        <>
            <Head title={`${status} - ${info.title}`} />

            <div className="relative flex min-h-svh flex-col items-center justify-center bg-background p-6">
                <div className="flex w-full max-w-md flex-col items-center gap-6">
                    <Link
                        href={targetHome}
                        className="flex items-center gap-2 self-center transition-opacity hover:opacity-90"
                    >
                        <div className="flex size-9 items-center justify-center">
                            <AppLogoIcon className="size-9 fill-current text-foreground" />
                        </div>
                    </Link>

                    <div className="w-full rounded-[12px] border border-border bg-card p-8 text-center">
                        <div className="flex flex-col items-center gap-4">
                            <div className="inline-flex items-center gap-1.5 rounded-full border border-border bg-secondary px-3 py-1 font-mono text-xs font-semibold text-primary tabular-nums">
                                <span className="size-1.5 rounded-full bg-primary" />
                                <span>HTTP {status}</span>
                            </div>

                            <div className="space-y-2">
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    {info.title}
                                </h1>
                                <p className="text-sm text-balance text-muted-foreground">
                                    {message || info.description}
                                </p>
                            </div>
                        </div>

                        <div className="mt-8 flex flex-col gap-2.5 sm:flex-row">
                            {info.isRetryable ? (
                                <Button
                                    onClick={handleReload}
                                    className="w-full"
                                >
                                    <RefreshCw className="size-4" />
                                    Sayfayı Yenile
                                </Button>
                            ) : (
                                <Button asChild className="w-full">
                                    <Link href={targetHome}>
                                        <Home className="size-4" />
                                        Ana Sayfaya Dön
                                    </Link>
                                </Button>
                            )}

                            <Button
                                variant="outline"
                                onClick={handleBack}
                                className="w-full"
                            >
                                <ArrowLeft className="size-4" />
                                Geri Dön
                            </Button>
                        </div>
                    </div>

                    <p className="font-mono text-xs text-muted-foreground/60">
                        KobiConnect
                    </p>
                </div>
            </div>
        </>
    );
}
